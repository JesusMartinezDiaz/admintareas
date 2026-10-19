<?php
require_once __DIR__ . '/_init.php';
$rows = sat_rows($con, "SELECT p.*, e.rfc, e.razon_social,
        (SELECT COUNT(*) FROM sat_solicitudes_descarga s WHERE s.id_programacion=p.id_programacion) solicitudes,
        (SELECT COUNT(*) FROM sat_solicitudes_descarga s WHERE s.id_programacion=p.id_programacion AND s.estado_solicitud IN ('ERROR','RECHAZADA','ERROR_TEMPORAL_SAT','VENCIDA')) con_error
    FROM sat_programaciones p JOIN sat_empresas e ON e.id_empresa=p.id_empresa
    ORDER BY p.activo DESC, p.proxima_ejecucion");
foreach ($rows as &$r) {
    $r['descripcion'] = SchedulerService::describir($r);
    $r['descripcion_periodo'] = SchedulerService::describirPeriodo($r);
}
$cron = @file_get_contents(sat_path_logs() . '/cron_ultima_ejecucion.txt');
sat_json(true, '', array('programaciones' => $rows, 'cron_ultima' => $cron ?: null, 'admin' => sat_es_admin()));
