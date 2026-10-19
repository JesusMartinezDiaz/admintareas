<?php
/** Solicitud manual al SAT. tipo_descarga = EMITIDAS | RECIBIDAS | AMBAS. */
require_once __DIR__ . '/_init.php';

$id = (int)sat_post('id_empresa', 0);
$tipo = strtoupper(sat_post('tipo_descarga', 'EMITIDAS'));
$archivo = strtoupper(sat_post('tipo_archivo', 'CFDI'));
$tipoComp = strtoupper(sat_post('tipo_comprobante', ''));
$fi = sat_post('fecha_inicial');
$ff = sat_post('fecha_final');
if ($id <= 0) throw new Exception('Selecciona la empresa.');
if (!sat_fecha_valida($fi) || !sat_fecha_valida($ff)) throw new Exception('Fechas inválidas.');
if ($fi > $ff) throw new Exception('La fecha inicial no puede ser mayor que la final.');

$tipos = $tipo === 'AMBAS' ? array('EMITIDAS', 'RECIBIDAS') : array($tipo);
$res = array();
$ok = 0;
foreach ($tipos as $t) {
    $r = SatDownloadService::crearSolicitud($con, $id, $t, $archivo, $fi, $ff, array(
        'tipo_comprobante' => $tipoComp, 'origen' => 'MANUAL',
        'auto_descargar' => (int)sat_post('auto', 1), 'auto_procesar' => (int)sat_post('auto', 1)));
    $res[] = $t . ': ' . ($r['ok'] ? 'aceptada (solicitud #' . $r['id_solicitud'] . ')' : 'rechazada - ' . $r['mensaje']);
    if ($r['ok']) $ok++;
}
sat_json($ok > 0, implode("\n", $res) . ($ok ? "\n\nEl SAT tarda de minutos a horas en preparar los paquetes. El cron los verificará, descargará y procesará automáticamente; también puedes usar «Actualizar»." : ''));
