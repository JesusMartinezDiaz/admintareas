<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();

$id = (int)sat_post('id_programacion', 0);
$p = SchedulerService::normalizar($_POST);
$emp = sat_row($con, "SELECT id_empresa FROM sat_empresas WHERE id_empresa=? AND activo=1", 'i', array($p['id_empresa']));
if (!$emp) throw new Exception('Empresa inválida o inactiva.');
$cert = sat_row($con, "SELECT id_certificado FROM sat_certificados WHERE id_empresa=? AND activo=1", 'i', array($p['id_empresa']));
if (!$cert && $p['activo']) throw new Exception('La empresa no tiene e.firma cargada; cárgala antes de activar la programación.');
$prox = $p['activo'] ? SchedulerService::calcularProxima($p)->format('Y-m-d H:i:s') : null;

$campos = array('id_empresa' => 'i', 'nombre' => 's', 'tipo_descarga' => 's', 'tipo_archivo' => 's', 'tipo_comprobante' => 's', 'frecuencia' => 's',
    'intervalo_horas' => 'i', 'hora_ejecucion' => 's', 'dias_semana' => 's', 'dia_mes' => 'i', 'periodo' => 's', 'dias_atras' => 'i',
    'auto_descargar' => 'i', 'auto_procesar' => 'i', 'activo' => 'i');
$types = implode('', $campos) . 's';
$vals = array();
foreach ($campos as $c => $t) $vals[] = $p[$c];
$vals[] = $prox;

if ($id > 0) {
    $sets = implode(',', array_map(function ($c) { return "$c=?"; }, array_keys($campos)));
    $vals[] = $id;
    sat_exec($con, "UPDATE sat_programaciones SET $sets, proxima_ejecucion=?, fecha_modificacion=NOW() WHERE id_programacion=?", $types . 'i', $vals);
} else {
    $cols = implode(',', array_keys($campos));
    $q = implode(',', array_fill(0, count($campos) + 2, '?'));
    $vals[] = sat_usuario();
    sat_exec($con, "INSERT INTO sat_programaciones($cols,proxima_ejecucion,usuario_alta) VALUES ($q)", $types . 's', $vals);
    $id = (int)mysqli_insert_id($con);
}
log_proceso($con, $p['id_empresa'], null, 'PROGRAMACION', 'OK', 'Programación #' . $id . ' guardada: ' . $p['nombre'] . ' (' . SchedulerService::describir($p) . ')');
sat_json(true, 'Programación guardada.' . ($prox ? ' Próxima ejecución: ' . date('d/m/Y H:i', strtotime($prox)) . '.' : ' (inactiva)'), array('id_programacion' => $id));
