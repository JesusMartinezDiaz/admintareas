<?php
// Cron del módulo SAT. Programarlo cada 10-15 minutos, por ejemplo en cPanel:
//
//   */15 * * * * /usr/local/bin/php -q /home/USUARIO/public_html/sat/cron/cron_sat.php >> /home/USUARIO/sat_cron.log 2>&1
//
// En cada corrida:
//  1. Ejecuta las programaciones cuya hora ya llegó (crea las peticiones al SAT).
//  2. Verifica las solicitudes pendientes (respetando 'verificar_cada_minutos').
//  3. Descarga los paquetes de las solicitudes terminadas (si tienen auto_descargar).
//  4. Procesa los XML descargados (si tienen auto_procesar) y contabiliza.
//  5. Marca como VENCIDA lo que lleva demasiados días sin terminar.
if (PHP_SAPI !== 'cli' && !defined('SAT_CRON_WEB')) {
    http_response_code(403);
    exit('Solo por línea de comandos.');
}
require_once dirname(__DIR__) . '/lib/bootstrap.php';
set_time_limit(0);
ignore_user_abort(true);

function cron_log($msg) { echo date('Y-m-d H:i:s') . ' ' . $msg . "\n"; }

// Evita dos corridas simultáneas.
$lock = fopen(sat_path_logs() . '/cron_sat.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    cron_log('Otra ejecución del cron sigue en curso; se omite esta corrida.');
    exit(0);
}

try {
    $con = sat_db();
} catch (Exception $e) {
    cron_log('ERROR BD: ' . $e->getMessage());
    exit(1);
}

$inicio = microtime(true);
$limiteSegundos = 50 * 60;
$tiempoAgotado = function () use ($inicio, $limiteSegundos) { return (microtime(true) - $inicio) > $limiteSegundos; };

// 1. Programaciones
try {
    $n = SchedulerService::ejecutarPendientes($con, 'cron_log');
    cron_log("Programaciones ejecutadas: $n");
} catch (Exception $e) {
    cron_log('ERROR programaciones: ' . $e->getMessage());
}

// 5. Vencidas (antes de verificar para no gastar peticiones)
$dias = max(1, (int)sat_config('dias_vencimiento_solicitud', 3));
mysqli_query($con, "UPDATE sat_solicitudes_descarga SET estado_solicitud='VENCIDA', mensaje_sat=CONCAT('Sin terminar después de $dias días. ', COALESCE(mensaje_sat,''))
    WHERE estado_solicitud IN ('" . implode("','", SatDownloadService::$estadosPendientes) . "') AND fecha_alta < DATE_SUB(NOW(), INTERVAL $dias DAY)");

// 2-4. Avanzar solicitudes
$min = max(1, (int)sat_config('verificar_cada_minutos', 10));
$pend = sat_rows($con, "SELECT s.id_solicitud FROM sat_solicitudes_descarga s JOIN sat_empresas e ON e.id_empresa=s.id_empresa AND e.activo=1
    WHERE (s.estado_solicitud IN ('" . implode("','", SatDownloadService::$estadosPendientes) . "')
           AND s.request_id IS NOT NULL AND s.request_id<>''
           AND (s.fecha_ultima_verificacion IS NULL OR s.fecha_ultima_verificacion < DATE_SUB(NOW(), INTERVAL $min MINUTE)))
       OR (s.estado_solicitud='TERMINADA' AND s.auto_descargar=1 AND EXISTS (SELECT 1 FROM sat_paquetes_descarga p WHERE p.id_solicitud=s.id_solicitud AND p.descargado=0 AND p.intentos<5))
       OR (s.estado_solicitud='TERMINADA' AND s.auto_procesar=1 AND s.procesada=0 AND EXISTS (SELECT 1 FROM sat_paquetes_descarga p WHERE p.id_solicitud=s.id_solicitud AND p.descargado=1))
    ORDER BY s.id_solicitud LIMIT 50");

foreach ($pend as $r) {
    if ($tiempoAgotado()) { cron_log('Límite de tiempo alcanzado; se continúa en la próxima corrida.'); break; }
    $id = (int)$r['id_solicitud'];
    try {
        $pasos = SatDownloadService::avanzar($con, $id, false);
        cron_log("Solicitud #$id: " . ($pasos ? implode(' | ', $pasos) : 'sin cambios'));
    } catch (Exception $e) {
        $s = sat_row($con, "SELECT id_empresa FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array($id));
        log_proceso($con, $s ? $s['id_empresa'] : null, $id, 'CRON', 'ERROR', $e->getMessage());
        // Se registra la verificación fallida para no reintentar en cada corrida.
        mysqli_query($con, "UPDATE sat_solicitudes_descarga SET fecha_ultima_verificacion=NOW() WHERE id_solicitud=$id");
        cron_log("Solicitud #$id ERROR: " . $e->getMessage());
    }
}

@file_put_contents(sat_path_logs() . '/cron_ultima_ejecucion.txt', date('Y-m-d H:i:s'));
cron_log('Fin (' . round(microtime(true) - $inicio, 1) . ' s).');
flock($lock, LOCK_UN);
