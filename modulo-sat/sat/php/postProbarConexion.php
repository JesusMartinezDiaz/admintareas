<?php
require_once __DIR__ . '/_init.php';
$id = (int)sat_post('id_empresa', 0);
list($empresa, $cert) = sat_empresa_y_certificado($con, $id);
try {
    $r = SatService::probarCredenciales($empresa, $cert);
} catch (Exception $e) {
    log_proceso($con, $id, null, 'PROBAR_CONEXION', 'ERROR', $e->getMessage());
    throw $e;
}
log_proceso($con, $id, null, 'PROBAR_CONEXION', 'OK', 'Autenticación correcta');
sat_json(true, 'Conexión correcta: el SAT aceptó la e.firma de ' . $r['rfc'] . ' (vigente hasta ' . $r['vigencia_hasta'] . ').');
