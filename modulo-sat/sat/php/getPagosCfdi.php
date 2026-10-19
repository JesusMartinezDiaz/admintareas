<?php
/** Pagos aplicados a una factura PPD (o documentos que paga un complemento). */
require_once __DIR__ . '/_init.php';
$c = sat_row($con, "SELECT * FROM sat_cfdi WHERE id_cfdi=?", 'i', array((int)sat_get('id_cfdi', 0)));
if (!$c) throw new Exception('CFDI no encontrado.');
if ($c['tipo_comprobante'] === 'P') {
    $rows = sat_rows($con, "SELECT p.fecha_pago, p.forma_pago, p.moneda, p.monto, d.uuid_documento uuid, d.serie, d.folio, d.num_parcialidad, d.imp_saldo_ant, d.imp_pagado, d.imp_saldo_insoluto, d.iva_mxn, d.pagado_mxn, d.iva_estimado
        FROM sat_cfdi_pagos p JOIN sat_cfdi_pagos_doctos d ON d.id_pago=p.id_pago WHERE p.id_cfdi=? ORDER BY p.fecha_pago", 'i', array((int)$c['id_cfdi']));
} else {
    $rows = sat_rows($con, "SELECT p.fecha_pago, p.forma_pago, p.moneda, p.monto, cp.uuid, cp.serie, cp.folio, d.num_parcialidad, d.imp_saldo_ant, d.imp_pagado, d.imp_saldo_insoluto, d.iva_mxn, d.pagado_mxn, d.iva_estimado, cp.estatus_sat
        FROM sat_cfdi_pagos_doctos d JOIN sat_cfdi_pagos p ON p.id_pago=d.id_pago JOIN sat_cfdi cp ON cp.id_cfdi=d.id_cfdi
        WHERE cp.id_empresa=? AND d.uuid_documento=? ORDER BY p.fecha_pago", 'is', array((int)$c['id_empresa'], $c['uuid']));
}
$rel = sat_rows($con, "SELECT r.tipo_relacion, r.uuid_relacionado, x.id_cfdi, x.tipo_comprobante, x.total FROM sat_cfdi_relacionados r LEFT JOIN sat_cfdi x ON x.uuid=r.uuid_relacionado AND x.id_empresa=? WHERE r.id_cfdi=?", 'ii', array((int)$c['id_empresa'], (int)$c['id_cfdi']));
$ncs = sat_rows($con, "SELECT x.id_cfdi, x.uuid, x.serie, x.folio, x.fecha_emision, x.total, r.tipo_relacion FROM sat_cfdi_relacionados r JOIN sat_cfdi x ON x.id_cfdi=r.id_cfdi WHERE x.id_empresa=? AND r.uuid_relacionado=?", 'is', array((int)$c['id_empresa'], $c['uuid']));
sat_json(true, '', array('cfdi' => $c, 'pagos' => $rows, 'relacionados' => $rel, 'relacionado_por' => $ncs));
