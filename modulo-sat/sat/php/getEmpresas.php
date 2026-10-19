<?php
require_once __DIR__ . '/_init.php';

$rows = sat_rows($con, "SELECT e.*,
        c.id_certificado, c.no_certificado, c.rfc_certificado, c.fecha_inicio, c.fecha_vencimiento, c.fecha_alta fecha_carga,
        (SELECT COUNT(*) FROM sat_programaciones p WHERE p.id_empresa=e.id_empresa AND p.activo=1) programaciones,
        (SELECT MAX(s.fecha_alta) FROM sat_solicitudes_descarga s WHERE s.id_empresa=e.id_empresa) ultima_solicitud,
        (SELECT COUNT(*) FROM sat_cfdi x WHERE x.id_empresa=e.id_empresa) cfdi
    FROM sat_empresas e
    LEFT JOIN sat_certificados c ON c.id_certificado=(SELECT MAX(c2.id_certificado) FROM sat_certificados c2 WHERE c2.id_empresa=e.id_empresa AND c2.activo=1)
    ORDER BY e.activo DESC, e.razon_social");
foreach ($rows as &$r) {
    $r['dias_vigencia'] = $r['fecha_vencimiento'] ? (int)floor((strtotime($r['fecha_vencimiento']) - time()) / 86400) : null;
}
sat_json(true, '', array('empresas' => $rows, 'admin' => sat_es_admin()));
