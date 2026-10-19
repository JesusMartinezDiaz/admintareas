<?php
require_once __DIR__ . '/_init.php';
$id = (int)sat_get('id_empresa', 0);
$fi = sat_get('fecha_inicial');
$ff = sat_get('fecha_final');
if ($id <= 0 || !sat_fecha_valida($fi) || !sat_fecha_valida($ff)) throw new Exception('Parámetros inválidos.');
$filtros = array('rel' => sat_get('rel'), 'tipo' => sat_get('tipo'), 'metodo' => sat_get('metodo'), 'estatus' => sat_get('estatus'), 'buscar' => sat_get('buscar'));
$limite = sat_get('todo') === '1' ? 50000 : 1000;
$rows = TaxReportService::detalle($con, $id, $fi, $ff, $filtros, $limite);
sat_json(true, '', array('cfdi' => $rows, 'limite' => $limite));
