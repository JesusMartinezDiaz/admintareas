<?php
/**
 * Prueba de punta a punta del procesamiento de CFDI y del reporte fiscal con
 * XML sintéticos (tests/fixtures). ESCRIBE EN LA BD configurada: úsalo solo
 * contra una base de pruebas.
 *
 *   php sat/tests/test_procesamiento.php --confirmar
 */
if (PHP_SAPI !== 'cli') exit;
if (!in_array('--confirmar', $argv, true)) { echo "Escribe en la BD. Ejecuta con --confirmar (solo en BD de pruebas).\n"; exit(1); }
require_once dirname(__DIR__) . '/lib/bootstrap.php';

$con = sat_db();
SatSchema::instalar($con);
$fallos = 0;
function igual($desc, $esperado, $real) {
    global $fallos;
    $ok = abs((float)$esperado - (float)$real) < 0.011;
    if (!$ok) $fallos++;
    echo ($ok ? '  OK   ' : '  FALLA') . " $desc: esperado " . number_format($esperado, 2) . ', obtenido ' . number_format((float)$real, 2) . "\n";
}

$rfc = 'EKU9003173C9';
mysqli_query($con, "INSERT IGNORE INTO sat_empresas(razon_social,rfc) VALUES ('EMPRESA DEMO','$rfc')");
$emp = sat_row($con, "SELECT id_empresa FROM sat_empresas WHERE rfc=?", 's', array($rfc));
$id = (int)$emp['id_empresa'];
foreach (array('sat_cfdi_pagos_doctos', 'sat_cfdi_pagos', 'sat_cfdi_relacionados', 'sat_cfdi_impuestos_detalle', 'sat_cfdi_conceptos') as $t) {
    mysqli_query($con, "DELETE x FROM $t x JOIN sat_cfdi c ON c.id_cfdi=x.id_cfdi WHERE c.id_empresa=$id");
}
mysqli_query($con, "DELETE d FROM sat_poliza_detalle d JOIN sat_polizas p ON p.id_poliza=d.id_poliza WHERE p.id_empresa=$id");
mysqli_query($con, "DELETE FROM sat_polizas WHERE id_empresa=$id");
mysqli_query($con, "DELETE FROM sat_cfdi WHERE id_empresa=$id");

$crearSolicitud = function ($tipo, $archivo) use ($con, $id) {
    sat_exec($con, "INSERT INTO sat_solicitudes_descarga(id_empresa,tipo_descarga,tipo_archivo,fecha_inicial,fecha_final,request_id,estado_solicitud) VALUES (?,?,?,'2026-08-01','2026-09-30','TEST','TERMINADA')",
        'iss', array($id, $tipo, $archivo));
    return (int)mysqli_insert_id($con);
};
$sol = $crearSolicitud('EMITIDAS', 'CFDI');
$solMeta = $crearSolicitud('EMITIDAS', 'METADATA');
foreach (glob(__DIR__ . '/fixtures/*.xml') as $f) copy($f, CfdiProcessor::dirSolicitud($sol) . '/' . basename($f));
copy(__DIR__ . '/fixtures/metadata_ejemplo.txt', CfdiProcessor::dirSolicitud($solMeta) . '/metadata.txt');

echo "Procesando XML...\n";
$r = SatDownloadService::procesar($con, $sol);
igual('XML procesados', 7, $r['procesados']);
igual('Errores', 0, $r['errores']);
$r = SatDownloadService::procesar($con, $solMeta);
igual('Renglones de metadata', 2, $r['procesados']);
// Reprocesar no debe duplicar
SatDownloadService::procesar($con, $sol);
$n = sat_row($con, "SELECT COUNT(*) n FROM sat_cfdi WHERE id_empresa=?", 'i', array($id));
igual('CFDI sin duplicar tras reprocesar', 9, $n['n']);

echo "Reporte septiembre 2026...\n";
$rep = TaxReportService::reporte($con, $id, '2026-09-01', '2026-09-30');
$iva = $rep['iva'];
igual('IVA trasladado PUE', 160, $iva['trasladado']['pue']['iva']);
igual('IVA trasladado cobrado vía pago 2.0 (USD)', 720, $iva['trasladado']['ppd']['iva']);
igual('IVA nota de crédito emitida', 16, $iva['trasladado']['notas_credito']['iva']);
igual('IVA trasladado cobrado neto', 864, $iva['resultado']['iva_trasladado_cobrado']);
igual('IVA retenido por clientes', 106.67, $iva['resultado']['iva_retenido_por_clientes']);
igual('IVA acreditable PUE', 320, $iva['acreditable']['pue']['iva']);
igual('IVA acreditable pago 1.0 (prorrateado)', 800, $iva['acreditable']['ppd']['iva']);
igual('IVA acreditable pagado', 1120, $iva['resultado']['iva_acreditable_pagado']);
igual('IVA a cargo', 0, $iva['resultado']['iva_a_cargo']);
igual('IVA a favor', 362.67, $iva['resultado']['iva_a_favor']);
igual('Base IVA 0% recibida', 500, $iva['acreditable']['base_0']);
igual('Ingresos cobrados (ISR)', 5400, $rep['isr']['ingresos_cobrados']);
igual('Deducciones pagadas (ISR)', 7500, $rep['isr']['deducciones_pagadas']);
igual('ISR retenido por clientes', 100, $rep['isr']['isr_retenido_por_clientes']);
$ppd = array();
foreach ($rep['ppd_pendientes'] as $p) $ppd[$p['uuid']] = $p;
igual('Saldo PPD emitida (USD)', 290, $ppd['11111111-1111-1111-1111-000000000002']['saldo']);
igual('Saldo PPD emitida (MXN)', 5365, $ppd['11111111-1111-1111-1111-000000000002']['saldo_mxn']);
igual('Saldo PPD recibida', 5800, $ppd['22222222-2222-2222-2222-000000000002']['saldo']);
$canc = sat_row($con, "SELECT estatus_sat FROM sat_cfdi WHERE id_empresa=? AND uuid='33333333-3333-3333-3333-000000000001'", 'i', array($id));
igual('Metadata marcó cancelado', 1, $canc['estatus_sat'] === 'CANCELADO' ? 1 : 0);
igual('Alertas generadas (>=3)', 1, count($rep['alertas']) >= 3 ? 1 : 0);
$pol = sat_row($con, "SELECT COUNT(*) n, SUM(estatus='CONTABILIZADA') ok FROM sat_polizas WHERE id_empresa=?", 'i', array($id));
igual('Pólizas generadas (I y E)', 5, $pol['n']);
$usd = sat_row($con, "SELECT p.total_cargos FROM sat_polizas p JOIN sat_cfdi c ON c.id_cfdi=p.id_cfdi WHERE c.uuid='11111111-1111-1111-1111-000000000002' AND p.id_empresa=?", 'i', array($id));
igual('Póliza de factura USD en MXN', 580 * 18.5, $usd['total_cargos']);
$t = TaxReportService::tendencia($con, $id, '2026-09-30', 3);
igual('Tendencia: meses', 3, count($t));
$det = TaxReportService::detalle($con, $id, '2026-09-01', '2026-09-30', array('buscar' => 'CLIENTE DOS'));
igual('Detalle con búsqueda', 1, count($det));

echo "Programaciones...\n";
$p = SchedulerService::normalizar(array('id_empresa' => $id, 'nombre' => 'x', 'frecuencia' => 'SEMANAL', 'dias_semana' => array(1, 4), 'hora_ejecucion' => '07:30', 'periodo' => 'MES_ANTERIOR'));
$prox = SchedulerService::calcularProxima($p, new DateTime('2026-10-19 08:00:00')); // lunes
igual('Semanal lun/jue -> jueves 22', 22, (int)$prox->format('d'));
$p['frecuencia'] = 'CADA_HORAS'; $p['intervalo_horas'] = 6; $p['hora_ejecucion'] = '01:00:00';
$prox = SchedulerService::calcularProxima($p, new DateTime('2026-10-19 08:00:00'));
igual('Cada 6h desde 01:00 -> 13:00', 13, (int)$prox->format('H'));
$p['frecuencia'] = 'MENSUAL'; $p['dia_mes'] = 31; $p['hora_ejecucion'] = '23:00:00';
$prox = SchedulerService::calcularProxima($p, new DateTime('2026-02-10 08:00:00'));
igual('Mensual último día -> 28 feb', 28, (int)$prox->format('d'));
list($a, $b) = SchedulerService::calcularRango($p, new DateTime('2026-10-19 08:00:00'));
igual('Rango mes anterior inicia día 1', 1, (int)$a->format('d'));
igual('Rango mes anterior termina 30-sep', 30, (int)$b->format('d'));
$p['periodo'] = 'DIAS_ATRAS'; $p['dias_atras'] = 3;
list($a, $b) = SchedulerService::calcularRango($p, new DateTime('2026-10-19 08:00:00'));
igual('Últimos 3 días inicia el 16', 16, (int)$a->format('d'));

echo $fallos ? "\n$fallos FALLAS\n" : "\nTodo correcto.\n";
exit($fallos ? 1 : 0);
