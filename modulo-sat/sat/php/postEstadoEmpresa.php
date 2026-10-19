<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
$id = (int)sat_post('id_empresa', 0);
$activo = (int)sat_post('activo', 0) ? 1 : 0;
sat_exec($con, "UPDATE sat_empresas SET activo=? WHERE id_empresa=?", 'ii', array($activo, $id));
if (!$activo) sat_exec($con, "UPDATE sat_programaciones SET activo=0 WHERE id_empresa=?", 'i', array($id));
log_proceso($con, $id, null, 'EMPRESA', 'OK', $activo ? 'Empresa activada' : 'Empresa desactivada (y sus programaciones)');
sat_json(true, $activo ? 'Empresa activada.' : 'Empresa desactivada; sus programaciones se pausaron.');
