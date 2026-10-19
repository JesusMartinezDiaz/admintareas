<?php
/**
 * Reporte fiscal a partir de los CFDI descargados.
 *
 * Criterio principal: FLUJO DE EFECTIVO (como se determina el IVA mensual y el
 * ISR de personas físicas / RESICO):
 *  - Facturas PUE: cuentan en el mes de su emisión.
 *  - Facturas PPD: cuentan en el mes del pago, tomando el complemento de pago
 *    (pagos 2.0 trae el IVA por documento; en pagos 1.0 se prorratea).
 *  - Notas de crédito (tipo E): disminuyen en el mes de su emisión.
 * También se muestra el criterio devengado (lo facturado) como referencia.
 *
 * Todos los importes se convierten a MXN con el tipo de cambio del CFDI/pago.
 * Es una estimación de apoyo: no sustituye la determinación del contador.
 */
class TaxReportService
{
    const VIGENTE = " UPPER(COALESCE(c.estatus_sat,'VIGENTE')) NOT LIKE 'CANCEL%' AND COALESCE(c.origen_dato,'XML')='XML' ";
    const TC = " IF(c.tipo_cambio>0, c.tipo_cambio, 1) ";

    public static function reporte($con, $idEmpresa, $fi, $ff)
    {
        $idEmpresa = (int)$idEmpresa;
        $ini = $fi . ' 00:00:00';
        $fin = $ff . ' 23:59:59';
        $emp = sat_row($con, "SELECT * FROM sat_empresas WHERE id_empresa=?", 'i', array($idEmpresa));
        if (!$emp) throw new Exception('Empresa no encontrada.');

        $iva = self::iva($con, $idEmpresa, $ini, $fin);
        $isr = self::isr($con, $idEmpresa, $ini, $fin, $iva);

        return array(
            'empresa' => array('rfc' => $emp['rfc'], 'razon_social' => $emp['razon_social'], 'tipo_persona' => isset($emp['tipo_persona']) ? $emp['tipo_persona'] : 'MORAL', 'regimen_fiscal' => $emp['regimen_fiscal']),
            'periodo' => array('inicio' => $fi, 'fin' => $ff),
            'resumen_tipos' => self::resumenTipos($con, $idEmpresa, $ini, $fin),
            'iva' => $iva,
            'isr' => $isr,
            'nomina' => self::nomina($con, $idEmpresa, $ini, $fin),
            'top_clientes' => self::top($con, $idEmpresa, $ini, $fin, 'EMITIDA'),
            'top_proveedores' => self::top($con, $idEmpresa, $ini, $fin, 'RECIBIDA'),
            'ppd_pendientes' => self::ppdPendientes($con, $idEmpresa, $fin),
            'alertas' => self::alertas($con, $idEmpresa, $ini, $fin),
        );
    }

    /* ------------------------------------------------------------------ */

    private static function resumenTipos($con, $id, $ini, $fin)
    {
        $tc = self::TC;
        return sat_rows($con, "SELECT c.tipo_relacion_empresa rel, c.tipo_comprobante tipo,
                SUM(UPPER(COALESCE(c.estatus_sat,'')) NOT LIKE 'CANCEL%') vigentes,
                SUM(UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%') cancelados,
                ROUND(SUM(IF(UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%',0,(c.subtotal-c.descuento)*$tc)),2) base,
                ROUND(SUM(IF(UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%',0,c.iva*$tc)),2) iva,
                ROUND(SUM(IF(UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%',0,(c.iva_retenido+c.isr_retenido)*$tc)),2) retenciones,
                ROUND(SUM(IF(UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%',0,IF(c.tipo_comprobante='P',c.total_pagos,c.total*$tc))),2) total
            FROM sat_cfdi c
            WHERE c.id_empresa=? AND c.fecha_emision BETWEEN ? AND ? AND COALESCE(c.origen_dato,'XML')='XML'
            GROUP BY c.tipo_relacion_empresa, c.tipo_comprobante
            ORDER BY c.tipo_relacion_empresa, FIELD(c.tipo_comprobante,'I','E','P','N','T')", 'iss', array($id, $ini, $fin));
    }

    /** Suma de CFDI I/E por método de pago en el periodo (MXN). */
    private static function sumaCfdi($con, $id, $ini, $fin, $rel, $tipo, $soloPue)
    {
        $tc = self::TC;
        $v = self::VIGENTE;
        // Las notas de crédito y facturas sin MetodoPago se tratan como PUE.
        $condPue = $soloPue ? " AND COALESCE(NULLIF(c.metodo_pago,''),'PUE')<>'PPD' " : '';
        $condUso = $rel === 'RECIBIDA' ? " AND COALESCE(c.uso_cfdi,'')<>'S01' " : '';
        $r = sat_row($con, "SELECT COUNT(*) n,
                COALESCE(ROUND(SUM((c.subtotal-c.descuento)*$tc),2),0) base,
                COALESCE(ROUND(SUM(c.base_iva_16*$tc),2),0) b16,
                COALESCE(ROUND(SUM(c.base_iva_8*$tc),2),0) b8,
                COALESCE(ROUND(SUM(c.base_iva_0*$tc),2),0) b0,
                COALESCE(ROUND(SUM(c.base_iva_exento*$tc),2),0) bex,
                COALESCE(ROUND(SUM(c.iva*$tc),2),0) iva,
                COALESCE(ROUND(SUM(c.ieps*$tc),2),0) ieps,
                COALESCE(ROUND(SUM(c.iva_retenido*$tc),2),0) iva_ret,
                COALESCE(ROUND(SUM(c.isr_retenido*$tc),2),0) isr_ret,
                COALESCE(ROUND(SUM(c.total*$tc),2),0) total
            FROM sat_cfdi c
            WHERE c.id_empresa=? AND c.tipo_relacion_empresa=? AND c.tipo_comprobante=? AND c.fecha_emision BETWEEN ? AND ? AND $v $condPue $condUso",
            'issss', array($id, $rel, $tipo, $ini, $fin));
        foreach ($r as $k => $x) $r[$k] = (float)$x;
        return $r;
    }

    /** Pagos de facturas PPD (complementos) con FechaPago en el periodo (MXN). */
    private static function sumaPagos($con, $id, $ini, $fin, $rel)
    {
        $v = self::VIGENTE;
        $r = sat_row($con, "SELECT COUNT(DISTINCT c.id_cfdi) n, COUNT(*) doctos,
                COALESCE(SUM(d.pagado_mxn),0) pagado,
                COALESCE(SUM(d.base_iva_mxn),0) base_iva,
                COALESCE(SUM(d.iva_mxn),0) iva,
                COALESCE(SUM(d.ieps_mxn),0) ieps,
                COALESCE(SUM(d.iva_retenido_mxn),0) iva_ret,
                COALESCE(SUM(d.isr_retenido_mxn),0) isr_ret,
                COALESCE(SUM(d.iva_estimado),0) estimados
            FROM sat_cfdi c
            JOIN sat_cfdi_pagos p ON p.id_cfdi=c.id_cfdi
            JOIN sat_cfdi_pagos_doctos d ON d.id_pago=p.id_pago
            WHERE c.id_empresa=? AND c.tipo_relacion_empresa=? AND c.tipo_comprobante='P' AND p.fecha_pago BETWEEN ? AND ? AND $v",
            'isss', array($id, $rel, $ini, $fin));
        foreach ($r as $k => $x) $r[$k] = (float)$x;
        // Ingreso/gasto sin impuestos = pagado - traslados + retenciones.
        $r['base'] = round($r['pagado'] - $r['iva'] - $r['ieps'] + $r['iva_ret'] + $r['isr_ret'], 2);
        return $r;
    }

    public static function iva($con, $id, $ini, $fin)
    {
        $out = array();
        foreach (array('EMITIDA' => 'trasladado', 'RECIBIDA' => 'acreditable') as $rel => $k) {
            $pue = self::sumaCfdi($con, $id, $ini, $fin, $rel, 'I', true);
            $ppd = self::sumaPagos($con, $id, $ini, $fin, $rel);
            $nc = self::sumaCfdi($con, $id, $ini, $fin, $rel, 'E', false);
            $out[$k] = array(
                'pue' => $pue, 'ppd' => $ppd, 'notas_credito' => $nc,
                'iva_neto' => round($pue['iva'] + $ppd['iva'] - $nc['iva'], 2),
                'iva_retenido' => round($pue['iva_ret'] + $ppd['iva_ret'] - $nc['iva_ret'], 2),
                'isr_retenido' => round($pue['isr_ret'] + $ppd['isr_ret'] - $nc['isr_ret'], 2),
                'base_16' => round($pue['b16'] - $nc['b16'], 2),
                'base_8' => round($pue['b8'] - $nc['b8'], 2),
                'base_0' => round($pue['b0'] - $nc['b0'], 2),
                'base_exento' => round($pue['bex'] - $nc['bex'], 2),
                'base_ppd' => $ppd['base_iva'],
            );
        }
        $t = $out['trasladado'];
        $a = $out['acreditable'];
        // IVA a cargo = trasladado cobrado - IVA que nos retuvieron los clientes - IVA acreditable pagado.
        $neto = round($t['iva_neto'] - $t['iva_retenido'] - $a['iva_neto'], 2);
        $out['resultado'] = array(
            'iva_trasladado_cobrado' => $t['iva_neto'],
            'iva_retenido_por_clientes' => $t['iva_retenido'],
            'iva_acreditable_pagado' => $a['iva_neto'],
            'iva_a_cargo' => $neto > 0 ? $neto : 0,
            'iva_a_favor' => $neto < 0 ? abs($neto) : 0,
            // Retenciones que la empresa hizo a sus proveedores: se enteran aparte.
            'iva_retenido_a_enterar' => $a['iva_retenido'],
            'devengado_trasladado' => round(self::sumaCfdi($con, $id, $ini, $fin, 'EMITIDA', 'I', false)['iva'] - $t['notas_credito']['iva'], 2),
            'devengado_acreditable' => round(self::sumaCfdi($con, $id, $ini, $fin, 'RECIBIDA', 'I', false)['iva'] - $a['notas_credito']['iva'], 2),
        );
        return $out;
    }

    private static function isr($con, $id, $ini, $fin, $iva)
    {
        $t = $iva['trasladado'];
        $a = $iva['acreditable'];
        $ingCobrados = round($t['pue']['base'] + $t['ppd']['base'] - $t['notas_credito']['base'], 2);
        $dedPagadas = round($a['pue']['base'] + $a['ppd']['base'] - $a['notas_credito']['base'], 2);
        $ingFact = round(self::sumaCfdi($con, $id, $ini, $fin, 'EMITIDA', 'I', false)['base'] - $t['notas_credito']['base'], 2);
        $gasFact = round(self::sumaCfdi($con, $id, $ini, $fin, 'RECIBIDA', 'I', false)['base'] - $a['notas_credito']['base'], 2);
        return array(
            'ingresos_cobrados' => $ingCobrados,
            'deducciones_pagadas' => $dedPagadas,
            'utilidad_flujo' => round($ingCobrados - $dedPagadas, 2),
            'ingresos_facturados' => $ingFact,
            'gastos_facturados' => $gasFact,
            'utilidad_devengada' => round($ingFact - $gasFact, 2),
            'isr_retenido_por_clientes' => $t['isr_retenido'],
            'isr_retenido_a_enterar' => $a['isr_retenido'],
        );
    }

    private static function nomina($con, $id, $ini, $fin)
    {
        $v = self::VIGENTE;
        $r = sat_row($con, "SELECT COUNT(*) n, COALESCE(SUM(c.subtotal),0) percepciones, COALESCE(SUM(c.descuento),0) deducciones, COALESCE(SUM(c.total),0) neto
            FROM sat_cfdi c WHERE c.id_empresa=? AND c.tipo_relacion_empresa='EMITIDA' AND c.tipo_comprobante='N' AND c.fecha_emision BETWEEN ? AND ? AND $v",
            'iss', array($id, $ini, $fin));
        foreach ($r as $k => $x) $r[$k] = (float)$x;
        return $r;
    }

    private static function top($con, $id, $ini, $fin, $rel)
    {
        $tc = self::TC;
        $v = self::VIGENTE;
        $rfc = $rel === 'EMITIDA' ? 'c.rfc_receptor' : 'c.rfc_emisor';
        $nom = $rel === 'EMITIDA' ? 'c.nombre_receptor' : 'c.nombre_emisor';
        return sat_rows($con, "SELECT $rfc rfc, MAX($nom) nombre, COUNT(*) n,
                ROUND(SUM(IF(c.tipo_comprobante='E',-1,1)*(c.subtotal-c.descuento)*$tc),2) base,
                ROUND(SUM(IF(c.tipo_comprobante='E',-1,1)*c.iva*$tc),2) iva,
                ROUND(SUM(IF(c.tipo_comprobante='E',-1,1)*c.total*$tc),2) total
            FROM sat_cfdi c WHERE c.id_empresa=? AND c.tipo_relacion_empresa=? AND c.tipo_comprobante IN ('I','E') AND c.fecha_emision BETWEEN ? AND ? AND $v
            GROUP BY $rfc ORDER BY total DESC LIMIT 10", 'isss', array($id, $rel, $ini, $fin));
    }

    /**
     * Facturas PPD con saldo pendiente al cierre del periodo (cuentas por
     * cobrar/pagar según CFDI). Saldo = total - pagos registrados hasta el cierre.
     */
    public static function ppdPendientes($con, $id, $fin, $limite = 200)
    {
        $v = self::VIGENTE;
        $tc = self::TC;
        return sat_rows($con, "SELECT c.id_cfdi, c.tipo_relacion_empresa rel, c.uuid, c.serie, c.folio, c.fecha_emision,
                IF(c.tipo_relacion_empresa='EMITIDA', c.rfc_receptor, c.rfc_emisor) rfc,
                IF(c.tipo_relacion_empresa='EMITIDA', c.nombre_receptor, c.nombre_emisor) nombre,
                c.moneda, c.total, COALESCE(pg.pagado,0) pagado, COALESCE(pg.parcialidades,0) parcialidades,
                ROUND(c.total - COALESCE(pg.pagado,0), 2) saldo,
                ROUND((c.total - COALESCE(pg.pagado,0)) * $tc, 2) saldo_mxn,
                DATEDIFF(?, c.fecha_emision) dias
            FROM sat_cfdi c
            LEFT JOIN (
                SELECT d.uuid_documento, SUM(d.imp_pagado) pagado, COUNT(*) parcialidades
                FROM sat_cfdi_pagos_doctos d
                JOIN sat_cfdi_pagos p ON p.id_pago=d.id_pago
                JOIN sat_cfdi cp ON cp.id_cfdi=d.id_cfdi AND cp.id_empresa=? AND UPPER(COALESCE(cp.estatus_sat,'')) NOT LIKE 'CANCEL%'
                WHERE p.fecha_pago<=?
                GROUP BY d.uuid_documento
            ) pg ON pg.uuid_documento=c.uuid
            WHERE c.id_empresa=? AND c.tipo_comprobante='I' AND c.metodo_pago='PPD' AND c.fecha_emision<=? AND $v
              AND c.fecha_emision >= DATE_SUB(?, INTERVAL 24 MONTH)
            HAVING saldo > 0.5
            ORDER BY rel, saldo_mxn DESC
            LIMIT " . (int)$limite, 'sisiss', array($fin, $id, $fin, $id, $fin, $fin));
    }

    private static function alertas($con, $id, $ini, $fin)
    {
        $al = array();
        $r = sat_row($con, "SELECT COUNT(*) n FROM sat_cfdi c WHERE c.id_empresa=? AND c.fecha_emision BETWEEN ? AND ? AND UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%'", 'iss', array($id, $ini, $fin));
        if ((int)$r['n'] > 0) $al[] = array('nivel' => 'info', 'texto' => (int)$r['n'] . ' CFDI cancelados en el periodo (excluidos de los cálculos).');
        $r = sat_row($con, "SELECT COUNT(*) n FROM sat_cfdi c WHERE c.id_empresa=? AND c.fecha_emision BETWEEN ? AND ? AND c.origen_dato='METADATA' AND UPPER(COALESCE(c.estatus_sat,'')) NOT LIKE 'CANCEL%'", 'iss', array($id, $ini, $fin));
        if ((int)$r['n'] > 0) $al[] = array('nivel' => 'warning', 'texto' => (int)$r['n'] . ' CFDI vigentes aparecen en la metadata pero su XML no se ha descargado. Solicita la descarga XML del periodo para incluirlos.');
        $r = sat_row($con, "SELECT COUNT(DISTINCT d.uuid_documento) n FROM sat_cfdi c JOIN sat_cfdi_pagos p ON p.id_cfdi=c.id_cfdi JOIN sat_cfdi_pagos_doctos d ON d.id_pago=p.id_pago
            LEFT JOIN sat_cfdi f ON f.id_empresa=c.id_empresa AND f.uuid=d.uuid_documento
            WHERE c.id_empresa=? AND c.tipo_comprobante='P' AND p.fecha_pago BETWEEN ? AND ? AND f.id_cfdi IS NULL AND UPPER(COALESCE(c.estatus_sat,'')) NOT LIKE 'CANCEL%'", 'iss', array($id, $ini, $fin));
        if ((int)$r['n'] > 0) $al[] = array('nivel' => 'warning', 'texto' => (int)$r['n'] . ' facturas pagadas en complementos del periodo no están descargadas (descarga periodos anteriores para conciliarlas).');
        $r = sat_row($con, "SELECT COALESCE(SUM(d.iva_estimado),0) n FROM sat_cfdi c JOIN sat_cfdi_pagos p ON p.id_cfdi=c.id_cfdi JOIN sat_cfdi_pagos_doctos d ON d.id_pago=p.id_pago
            WHERE c.id_empresa=? AND c.tipo_comprobante='P' AND p.fecha_pago BETWEEN ? AND ?", 'iss', array($id, $ini, $fin));
        if ((int)$r['n'] > 0) $al[] = array('nivel' => 'info', 'texto' => (int)$r['n'] . ' pagos usan complemento 1.0: su IVA se prorrateó con la factura relacionada.');
        $r = sat_row($con, "SELECT COUNT(*) n FROM sat_cfdi c WHERE c.id_empresa=? AND c.fecha_emision BETWEEN ? AND ? AND c.tipo_relacion_empresa='RECIBIDA' AND c.uso_cfdi='S01' AND " . self::VIGENTE, 'iss', array($id, $ini, $fin));
        if ((int)$r['n'] > 0) $al[] = array('nivel' => 'info', 'texto' => (int)$r['n'] . ' CFDI recibidos con uso S01 (sin efectos fiscales) no se consideran deducibles ni acreditables.');
        $r = sat_row($con, "SELECT COUNT(*) n FROM sat_cfdi c WHERE c.id_empresa=? AND c.fecha_emision BETWEEN ? AND ? AND c.tipo_relacion_empresa='RECIBIDA' AND c.tipo_comprobante='I' AND c.metodo_pago='PUE' AND c.forma_pago='01' AND c.total*" . self::TC . ">2000 AND " . self::VIGENTE, 'iss', array($id, $ini, $fin));
        if ((int)$r['n'] > 0) $al[] = array('nivel' => 'warning', 'texto' => (int)$r['n'] . ' gastos mayores a $2,000 pagados en efectivo: no son deducibles (art. 27 fr. III LISR).');
        $r = sat_row($con, "SELECT MAX(s.fecha_procesado) f FROM sat_solicitudes_descarga s WHERE s.id_empresa=? AND s.procesada=1", 'i', array($id));
        if (empty($r['f'])) $al[] = array('nivel' => 'warning', 'texto' => 'Aún no hay descargas procesadas para esta empresa.');
        return $al;
    }

    /** Tendencia mensual (flujo de efectivo) de los últimos $meses meses hasta $ff. */
    public static function tendencia($con, $idEmpresa, $ff, $meses = 12)
    {
        $out = array();
        $fin = new DateTime(substr($ff, 0, 7) . '-01');
        for ($i = $meses - 1; $i >= 0; $i--) {
            $m = (clone $fin)->modify('-' . $i . ' month');
            $ini = $m->format('Y-m-01 00:00:00');
            $finM = $m->format('Y-m-t 23:59:59');
            $iva = self::iva($con, $idEmpresa, $ini, $finM);
            $isr = self::isr($con, $idEmpresa, $ini, $finM, $iva);
            $out[] = array(
                'mes' => $m->format('Y-m'),
                'ingresos' => $isr['ingresos_cobrados'],
                'gastos' => $isr['deducciones_pagadas'],
                'iva_trasladado' => $iva['resultado']['iva_trasladado_cobrado'],
                'iva_acreditable' => $iva['resultado']['iva_acreditable_pagado'],
                'iva_neto' => round($iva['resultado']['iva_a_cargo'] - $iva['resultado']['iva_a_favor'], 2),
            );
        }
        return $out;
    }

    /** Detalle de CFDI con filtros (para la tabla y la exportación). */
    public static function detalle($con, $idEmpresa, $fi, $ff, $f = array(), $limite = 1000)
    {
        $where = " WHERE c.id_empresa=? AND c.fecha_emision BETWEEN ? AND ? ";
        $types = 'iss';
        $params = array((int)$idEmpresa, $fi . ' 00:00:00', $ff . ' 23:59:59');
        if (!empty($f['rel']) && in_array($f['rel'], array('EMITIDA', 'RECIBIDA'), true)) { $where .= " AND c.tipo_relacion_empresa=? "; $types .= 's'; $params[] = $f['rel']; }
        if (!empty($f['tipo']) && in_array($f['tipo'], array('I', 'E', 'P', 'N', 'T'), true)) { $where .= " AND c.tipo_comprobante=? "; $types .= 's'; $params[] = $f['tipo']; }
        if (!empty($f['metodo']) && in_array($f['metodo'], array('PUE', 'PPD'), true)) { $where .= " AND c.metodo_pago=? "; $types .= 's'; $params[] = $f['metodo']; }
        if (!empty($f['estatus']) && $f['estatus'] === 'VIGENTE') $where .= " AND UPPER(COALESCE(c.estatus_sat,'')) NOT LIKE 'CANCEL%' ";
        if (!empty($f['estatus']) && $f['estatus'] === 'CANCELADO') $where .= " AND UPPER(COALESCE(c.estatus_sat,'')) LIKE 'CANCEL%' ";
        if (!empty($f['buscar'])) {
            $where .= " AND (c.rfc_emisor LIKE ? OR c.rfc_receptor LIKE ? OR c.nombre_emisor LIKE ? OR c.nombre_receptor LIKE ? OR c.uuid LIKE ? OR CONCAT(COALESCE(c.serie,''),COALESCE(c.folio,'')) LIKE ?) ";
            $q = '%' . $f['buscar'] . '%';
            $types .= 'ssssss';
            array_push($params, $q, $q, $q, $q, $q, $q);
        }
        $tc = self::TC;
        return sat_rows($con, "SELECT c.id_cfdi, c.uuid, c.tipo_relacion_empresa rel, c.tipo_comprobante tipo, c.fecha_emision, c.serie, c.folio,
                c.rfc_emisor, c.nombre_emisor, c.rfc_receptor, c.nombre_receptor, c.metodo_pago, c.forma_pago, c.uso_cfdi, c.moneda, c.tipo_cambio,
                c.subtotal, c.descuento, c.iva, c.ieps, c.iva_retenido, c.isr_retenido, c.total, c.total_pagos,
                ROUND(c.total*$tc,2) total_mxn, c.estatus_sat, c.fecha_cancelacion, c.origen_dato,
                (SELECT GROUP_CONCAT(CONCAT(r.tipo_relacion,':',r.uuid_relacionado) SEPARATOR ' ') FROM sat_cfdi_relacionados r WHERE r.id_cfdi=c.id_cfdi) relacionados
            FROM sat_cfdi c $where ORDER BY c.fecha_emision DESC LIMIT " . (int)$limite, $types, $params);
    }
}
