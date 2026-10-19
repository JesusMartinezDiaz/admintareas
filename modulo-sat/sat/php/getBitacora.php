<?php
require_once __DIR__ . '/_init.php';
$where = ' WHERE 1=1 ';
$types = '';
$p = array();
if ((int)sat_get('id_empresa', 0) > 0) { $where .= ' AND l.id_empresa=? '; $types .= 'i'; $p[] = (int)sat_get('id_empresa'); }
if (sat_get('estatus') !== '') { $where .= ' AND l.estatus=? '; $types .= 's'; $p[] = sat_get('estatus'); }
if (sat_get('proceso') !== '') { $where .= ' AND l.proceso=? '; $types .= 's'; $p[] = sat_get('proceso'); }
if (sat_fecha_valida(sat_get('desde'))) { $where .= ' AND l.fecha>=? '; $types .= 's'; $p[] = sat_get('desde') . ' 00:00:00'; }
if (sat_fecha_valida(sat_get('hasta'))) { $where .= ' AND l.fecha<=? '; $types .= 's'; $p[] = sat_get('hasta') . ' 23:59:59'; }
$rows = sat_rows($con, "SELECT l.*, e.rfc FROM sat_log_procesos l LEFT JOIN sat_empresas e ON e.id_empresa=l.id_empresa $where ORDER BY l.id_log DESC LIMIT 500", $types, $p);
$procesos = sat_rows($con, "SELECT DISTINCT proceso FROM sat_log_procesos ORDER BY proceso");
sat_json(true, '', array('log' => $rows, 'procesos' => array_column($procesos, 'proceso')));
