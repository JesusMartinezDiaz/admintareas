<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
$id = (int)sat_post('id_programacion', 0);
$activo = (int)sat_post('activo', 0) ? 1 : 0;
$p = sat_row($con, "SELECT * FROM sat_programaciones WHERE id_programacion=?", 'i', array($id));
if (!$p) throw new Exception('Programación no encontrada.');
$prox = $activo ? SchedulerService::calcularProxima($p)->format('Y-m-d H:i:s') : null;
sat_exec($con, "UPDATE sat_programaciones SET activo=?, proxima_ejecucion=?, fecha_modificacion=NOW() WHERE id_programacion=?", 'isi', array($activo, $prox, $id));
log_proceso($con, $p['id_empresa'], null, 'PROGRAMACION', 'OK', 'Programación #' . $id . ($activo ? ' activada' : ' pausada'));
sat_json(true, $activo ? 'Programación activada. Próxima ejecución: ' . date('d/m/Y H:i', strtotime($prox)) : 'Programación pausada.');
