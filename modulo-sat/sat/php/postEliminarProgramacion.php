<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
$id = (int)sat_post('id_programacion', 0);
$p = sat_row($con, "SELECT * FROM sat_programaciones WHERE id_programacion=?", 'i', array($id));
if (!$p) throw new Exception('Programación no encontrada.');
// Las solicitudes ya creadas se conservan (quedan como historial).
sat_exec($con, "DELETE FROM sat_programaciones WHERE id_programacion=?", 'i', array($id));
log_proceso($con, $p['id_empresa'], null, 'PROGRAMACION', 'OK', 'Programación #' . $id . ' eliminada: ' . $p['nombre']);
sat_json(true, 'Programación eliminada.');
