<?php
require_once __DIR__ . '/_init.php';
$id = (int)sat_get('id_empresa', 0);
$fi = sat_get('fecha_inicial');
$ff = sat_get('fecha_final');
if ($id <= 0) throw new Exception('Selecciona la empresa.');
if (!sat_fecha_valida($fi) || !sat_fecha_valida($ff) || $fi > $ff) throw new Exception('Periodo inválido.');
$rep = TaxReportService::reporte($con, $id, $fi, $ff);
if (sat_get('tendencia') === '1') $rep['tendencia'] = TaxReportService::tendencia($con, $id, $ff, 12);
sat_json(true, '', array('reporte' => $rep));
