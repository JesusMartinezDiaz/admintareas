<?php
/** "Ejecutar ahora": crea las peticiones al SAT sin mover la próxima ejecución programada. */
require_once __DIR__ . '/_init.php';
$id = (int)sat_post('id_programacion', 0);
$p = sat_row($con, "SELECT * FROM sat_programaciones WHERE id_programacion=?", 'i', array($id));
if (!$p) throw new Exception('Programación no encontrada.');
$r = SchedulerService::ejecutar($con, $p, true);
sat_json($r['ok'], $r['mensaje']);
