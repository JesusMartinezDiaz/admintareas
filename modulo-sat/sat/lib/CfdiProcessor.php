<?php
/**
 * Lee los XML descargados del SAT y los guarda en MySQL.
 *
 * Soporta CFDI 3.3 y 4.0 de cualquier tipo (I, E, P, N, T):
 *  - impuestos globales y por concepto (IVA 16/8/0/exento, IEPS, retenciones ISR/IVA)
 *  - CFDI relacionados (notas de crédito, sustituciones)
 *  - complemento de pagos 1.0 y 2.0 con sus documentos relacionados
 * Y los archivos de METADATA (para detectar cancelaciones y CFDI sin XML).
 */
class CfdiProcessor
{
    /**
     * Procesa todos los XML/TXT extraídos de una solicitud.
     * @return array ['procesados'=>n, 'errores'=>n, 'mensajes'=>[]]
     */
    public static function procesarSolicitud($con, $idSolicitud)
    {
        $s = sat_row($con, "SELECT s.*, e.rfc FROM sat_solicitudes_descarga s JOIN sat_empresas e ON e.id_empresa=s.id_empresa WHERE s.id_solicitud=?", 'i', array((int)$idSolicitud));
        if (!$s) throw new Exception('No existe la solicitud ' . (int)$idSolicitud);

        $dir = self::dirSolicitud($idSolicitud);
        $res = array('procesados' => 0, 'errores' => 0, 'mensajes' => array());
        $esMetadata = strtoupper($s['tipo_archivo']) === 'METADATA';
        $archivos = $esMetadata ? glob($dir . '/*.txt') : glob($dir . '/*.xml');
        if (!$archivos) $archivos = array();

        foreach ($archivos as $file) {
            try {
                if ($esMetadata) {
                    $n = self::procesarMetadata($con, $s, $file);
                    $res['procesados'] += $n;
                } else {
                    $idCfdi = self::procesarXml($con, (int)$s['id_empresa'], $s['rfc'], $file, (int)$idSolicitud, $s['tipo_descarga']);
                    if ($idCfdi) $res['procesados']++;
                }
            } catch (Exception $e) {
                $res['errores']++;
                if (count($res['mensajes']) < 20) $res['mensajes'][] = basename($file) . ': ' . $e->getMessage();
            }
        }
        self::recalcularPagosEstimados($con, (int)$s['id_empresa']);

        sat_exec($con, "UPDATE sat_solicitudes_descarga SET procesada=1, xml_procesados=?, fecha_procesado=NOW() WHERE id_solicitud=?",
            'ii', array($res['procesados'], (int)$idSolicitud));
        log_proceso($con, (int)$s['id_empresa'], (int)$idSolicitud, 'PROCESAR_XML', $res['errores'] ? 'ERROR' : 'OK',
            'Procesados: ' . $res['procesados'] . '. Errores: ' . $res['errores'] . ($res['mensajes'] ? ' | ' . implode(' | ', $res['mensajes']) : ''));
        return $res;
    }

    public static function dirSolicitud($idSolicitud)
    {
        return sat_path_datos('xml/solicitud_' . (int)$idSolicitud);
    }

    /* ==================================================================
       XML CFDI
       ================================================================== */

    /** @return int id_cfdi */
    public static function procesarXml($con, $idEmpresa, $rfcEmpresa, $file, $idSolicitud = null, $tipoDescarga = '')
    {
        $xmlText = file_get_contents($file);
        if ($xmlText === false || trim($xmlText) === '') throw new Exception('Archivo vacío');
        $dom = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xmlText, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        if (!$ok) throw new Exception('XML inválido');

        $c = self::leerComprobante($dom);
        if ($c['uuid'] === '') throw new Exception('Sin TimbreFiscalDigital/UUID');

        $rfcEmpresa = strtoupper($rfcEmpresa);
        if ($c['rfc_emisor'] === $rfcEmpresa) $rel = 'EMITIDA';
        elseif ($c['rfc_receptor'] === $rfcEmpresa) $rel = 'RECIBIDA';
        else $rel = strtoupper($tipoDescarga) === 'EMITIDAS' ? 'EMITIDA' : 'RECIBIDA';

        mysqli_begin_transaction($con);
        try {
            $archivo = sat_path_relativa($file);
            sat_exec($con, "INSERT INTO sat_cfdi(id_empresa,id_solicitud,uuid,version,tipo_relacion_empresa,rfc_emisor,nombre_emisor,regimen_emisor,rfc_receptor,nombre_receptor,regimen_receptor,
                    fecha_emision,fecha_timbrado,tipo_comprobante,serie,folio,moneda,tipo_cambio,subtotal,descuento,iva,ieps,iva_retenido,isr_retenido,
                    base_iva_16,base_iva_8,base_iva_0,base_iva_exento,total_impuestos_retenidos,total_impuestos_trasladados,total,total_pagos,
                    metodo_pago,forma_pago,uso_cfdi,lugar_expedicion,archivo_xml,origen_dato)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'XML')
                ON DUPLICATE KEY UPDATE id_solicitud=VALUES(id_solicitud), version=VALUES(version), tipo_relacion_empresa=VALUES(tipo_relacion_empresa),
                    rfc_emisor=VALUES(rfc_emisor), nombre_emisor=VALUES(nombre_emisor), regimen_emisor=VALUES(regimen_emisor),
                    rfc_receptor=VALUES(rfc_receptor), nombre_receptor=VALUES(nombre_receptor), regimen_receptor=VALUES(regimen_receptor),
                    fecha_emision=VALUES(fecha_emision), fecha_timbrado=VALUES(fecha_timbrado), tipo_comprobante=VALUES(tipo_comprobante),
                    serie=VALUES(serie), folio=VALUES(folio), moneda=VALUES(moneda), tipo_cambio=VALUES(tipo_cambio), subtotal=VALUES(subtotal),
                    descuento=VALUES(descuento), iva=VALUES(iva), ieps=VALUES(ieps), iva_retenido=VALUES(iva_retenido), isr_retenido=VALUES(isr_retenido),
                    base_iva_16=VALUES(base_iva_16), base_iva_8=VALUES(base_iva_8), base_iva_0=VALUES(base_iva_0), base_iva_exento=VALUES(base_iva_exento),
                    total_impuestos_retenidos=VALUES(total_impuestos_retenidos), total_impuestos_trasladados=VALUES(total_impuestos_trasladados),
                    total=VALUES(total), total_pagos=VALUES(total_pagos), metodo_pago=VALUES(metodo_pago), forma_pago=VALUES(forma_pago),
                    uso_cfdi=VALUES(uso_cfdi), lugar_expedicion=VALUES(lugar_expedicion), archivo_xml=VALUES(archivo_xml), origen_dato='XML',
                    fecha_actualizacion=NOW()",
                'iisssssssssssssssddddddddddddddsssss' . 's',
                array($idEmpresa, $idSolicitud, $c['uuid'], $c['version'], $rel, $c['rfc_emisor'], $c['nombre_emisor'], $c['regimen_emisor'],
                    $c['rfc_receptor'], $c['nombre_receptor'], $c['regimen_receptor'], $c['fecha'], $c['fecha_timbrado'], $c['tipo'],
                    $c['serie'], $c['folio'], $c['moneda'], $c['tipo_cambio'], $c['subtotal'], $c['descuento'], $c['iva'], $c['ieps'],
                    $c['iva_retenido'], $c['isr_retenido'], $c['base_16'], $c['base_8'], $c['base_0'], $c['base_exento'],
                    $c['total_retenidos'], $c['total_trasladados'], $c['total'], $c['total_pagos'],
                    $c['metodo_pago'], $c['forma_pago'], $c['uso_cfdi'], $c['lugar_expedicion'], $archivo));

            $row = sat_row($con, "SELECT id_cfdi, estatus_sat FROM sat_cfdi WHERE id_empresa=? AND uuid=?", 'is', array($idEmpresa, $c['uuid']));
            $idCfdi = (int)$row['id_cfdi'];

            foreach (array('sat_cfdi_impuestos_detalle', 'sat_cfdi_conceptos', 'sat_cfdi_relacionados', 'sat_cfdi_pagos_doctos', 'sat_cfdi_pagos') as $t) {
                mysqli_query($con, "DELETE FROM $t WHERE id_cfdi=$idCfdi");
            }

            foreach ($c['conceptos'] as $cp) {
                sat_exec($con, "INSERT INTO sat_cfdi_conceptos(id_cfdi,clave_prod_serv,no_identificacion,descripcion,cantidad,clave_unidad,unidad,valor_unitario,importe,descuento,objeto_imp) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    'isssdssddds', array($idCfdi, $cp['clave'], $cp['no_identificacion'], $cp['descripcion'], $cp['cantidad'], $cp['clave_unidad'], $cp['unidad'], $cp['valor_unitario'], $cp['importe'], $cp['descuento'], $cp['objeto_imp']));
                $idConcepto = (int)mysqli_insert_id($con);
                foreach ($cp['impuestos'] as $im) self::insertImpuesto($con, $idCfdi, $idConcepto, 'CONCEPTO', $im);
            }
            foreach ($c['impuestos_globales'] as $im) self::insertImpuesto($con, $idCfdi, null, 'CFDI', $im);

            foreach ($c['relacionados'] as $r) {
                sat_exec($con, "INSERT INTO sat_cfdi_relacionados(id_cfdi,tipo_relacion,uuid_relacionado) VALUES (?,?,?)", 'iss', array($idCfdi, $r['tipo_relacion'], $r['uuid']));
            }

            foreach ($c['pagos'] as $p) {
                sat_exec($con, "INSERT INTO sat_cfdi_pagos(id_cfdi,version_pago,fecha_pago,forma_pago,moneda,tipo_cambio,monto,num_operacion) VALUES (?,?,?,?,?,?,?,?)",
                    'issssdds', array($idCfdi, $p['version'], $p['fecha_pago'], $p['forma_pago'], $p['moneda'], $p['tipo_cambio'], $p['monto'], $p['num_operacion']));
                $idPago = (int)mysqli_insert_id($con);
                foreach ($p['doctos'] as $d) {
                    sat_exec($con, "INSERT INTO sat_cfdi_pagos_doctos(id_pago,id_cfdi,uuid_documento,serie,folio,moneda_dr,equivalencia_dr,num_parcialidad,imp_saldo_ant,imp_pagado,imp_saldo_insoluto,objeto_imp_dr,
                            base_iva_mxn,iva_mxn,ieps_mxn,iva_retenido_mxn,isr_retenido_mxn,pagado_mxn,iva_estimado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        'iissssdidddsddddddi', array($idPago, $idCfdi, $d['uuid'], $d['serie'], $d['folio'], $d['moneda'], $d['equivalencia'], $d['num_parcialidad'],
                            $d['saldo_ant'], $d['pagado'], $d['saldo_insoluto'], $d['objeto_imp'], $d['base_iva_mxn'], $d['iva_mxn'], $d['ieps_mxn'],
                            $d['iva_ret_mxn'], $d['isr_ret_mxn'], $d['pagado_mxn'], $d['iva_estimado']));
                }
            }
            mysqli_commit($con);
        } catch (Exception $e) {
            mysqli_rollback($con);
            throw $e;
        }

        if (strtoupper((string)$row['estatus_sat']) !== 'CANCELADO') {
            try { AccountingService::contabilizarCfdi($con, $idCfdi, false); } catch (Exception $e) { /* la póliza se puede recalcular después */ }
        }
        return $idCfdi;
    }

    private static function insertImpuesto($con, $idCfdi, $idConcepto, $nivel, $im)
    {
        sat_exec($con, "INSERT INTO sat_cfdi_impuestos_detalle(id_cfdi,id_concepto,nivel,movimiento,impuesto,tipo_factor,tasa_cuota,base,importe) VALUES (?,?,?,?,?,?,?,?,?)",
            'iissssddd', array($idCfdi, $idConcepto, $nivel, $im['movimiento'], $im['impuesto'], $im['tipo_factor'], $im['tasa'], $im['base'], $im['importe']));
    }

    /** Extrae la información del comprobante. Público para poder probarlo sin BD. */
    public static function leerComprobante(DOMDocument $dom)
    {
        $root = $dom->documentElement;
        if (!$root || $root->localName !== 'Comprobante') throw new Exception('No es un CFDI (nodo Comprobante)');
        $a = function ($n, $k) { return ($n && $n->hasAttribute($k)) ? trim($n->getAttribute($k)) : ''; };
        $f = function ($n, $k) use ($a) { $v = $a($n, $k); return $v === '' ? 0.0 : (float)$v; };

        $em = self::hijo($root, 'Emisor');
        $re = self::hijo($root, 'Receptor');
        $comp = self::hijo($root, 'Complemento');
        $tfd = null;
        foreach (self::hijos($root, 'Complemento') as $cx) {
            $tfd = $tfd ?: self::hijo($cx, 'TimbreFiscalDigital');
        }

        $moneda = $a($root, 'Moneda');
        $tc = $f($root, 'TipoCambio');
        if ($tc <= 0 || $moneda === 'MXN' || $moneda === 'XXX' || $moneda === '') $tc = 1.0;

        $c = array(
            'uuid' => strtoupper($a($tfd, 'UUID')),
            'version' => $a($root, 'Version') ?: $a($root, 'version'),
            'tipo' => strtoupper($a($root, 'TipoDeComprobante')),
            'fecha' => self::fechaSql($a($root, 'Fecha')),
            'fecha_timbrado' => self::fechaSql($a($tfd, 'FechaTimbrado')),
            'serie' => $a($root, 'Serie'), 'folio' => $a($root, 'Folio'),
            'moneda' => $moneda, 'tipo_cambio' => $tc,
            'subtotal' => $f($root, 'SubTotal'), 'descuento' => $f($root, 'Descuento'), 'total' => $f($root, 'Total'),
            'metodo_pago' => $a($root, 'MetodoPago'), 'forma_pago' => $a($root, 'FormaPago'),
            'lugar_expedicion' => $a($root, 'LugarExpedicion'),
            'rfc_emisor' => strtoupper($a($em, 'Rfc')), 'nombre_emisor' => $a($em, 'Nombre'), 'regimen_emisor' => $a($em, 'RegimenFiscal'),
            'rfc_receptor' => strtoupper($a($re, 'Rfc')), 'nombre_receptor' => $a($re, 'Nombre'), 'regimen_receptor' => $a($re, 'RegimenFiscalReceptor'),
            'uso_cfdi' => $a($re, 'UsoCFDI'),
            'conceptos' => array(), 'impuestos_globales' => array(), 'relacionados' => array(), 'pagos' => array(),
            'iva' => 0.0, 'ieps' => 0.0, 'iva_retenido' => 0.0, 'isr_retenido' => 0.0,
            'base_16' => 0.0, 'base_8' => 0.0, 'base_0' => 0.0, 'base_exento' => 0.0,
            'total_trasladados' => 0.0, 'total_retenidos' => 0.0, 'total_pagos' => 0.0,
        );

        // Conceptos e impuestos por concepto
        $impConceptos = array();
        foreach (self::hijos(self::hijo($root, 'Conceptos'), 'Concepto') as $cn) {
            $cp = array(
                'clave' => $a($cn, 'ClaveProdServ'), 'no_identificacion' => $a($cn, 'NoIdentificacion'),
                'descripcion' => $a($cn, 'Descripcion'), 'cantidad' => $f($cn, 'Cantidad'),
                'clave_unidad' => $a($cn, 'ClaveUnidad'), 'unidad' => $a($cn, 'Unidad'),
                'valor_unitario' => $f($cn, 'ValorUnitario'), 'importe' => $f($cn, 'Importe'),
                'descuento' => $f($cn, 'Descuento'), 'objeto_imp' => $a($cn, 'ObjetoImp'),
                'impuestos' => self::leerImpuestos(self::hijo($cn, 'Impuestos'), '')
            );
            foreach ($cp['impuestos'] as $im) $impConceptos[] = $im;
            $c['conceptos'][] = $cp;
        }
        $impGlobalNode = self::hijo($root, 'Impuestos');
        $c['impuestos_globales'] = self::leerImpuestos($impGlobalNode, '');

        // Importes: se prefiere el nivel CFDI para no duplicar; bases desde conceptos
        // (en CFDI 3.3 el nivel CFDI no trae Base).
        $fuenteImportes = $impGlobalNode ? $c['impuestos_globales'] : $impConceptos;
        foreach ($fuenteImportes as $im) {
            if ($im['movimiento'] === 'TRASLADO') {
                if ($im['impuesto'] === '002') $c['iva'] += $im['importe'];
                if ($im['impuesto'] === '003') $c['ieps'] += $im['importe'];
            } else {
                if ($im['impuesto'] === '002') $c['iva_retenido'] += $im['importe'];
                if ($im['impuesto'] === '001') $c['isr_retenido'] += $im['importe'];
            }
        }
        if ($impGlobalNode) {
            $c['total_trasladados'] = $f($impGlobalNode, 'TotalImpuestosTrasladados');
            $c['total_retenidos'] = $f($impGlobalNode, 'TotalImpuestosRetenidos');
        } else {
            foreach ($impConceptos as $im) {
                if ($im['movimiento'] === 'TRASLADO') $c['total_trasladados'] += $im['importe'];
                else $c['total_retenidos'] += $im['importe'];
            }
        }
        $fuenteBases = $impConceptos ? $impConceptos : $c['impuestos_globales'];
        foreach ($fuenteBases as $im) {
            if ($im['movimiento'] !== 'TRASLADO' || $im['impuesto'] !== '002') continue;
            if (strtoupper($im['tipo_factor']) === 'EXENTO') { $c['base_exento'] += $im['base']; continue; }
            $t = round((float)$im['tasa'], 2);
            if ($t >= 0.155) $c['base_16'] += $im['base'];
            elseif ($t >= 0.075) $c['base_8'] += $im['base'];
            else $c['base_0'] += $im['base'];
        }

        // CFDI relacionados (4.0 permite varios nodos CfdiRelacionados)
        foreach (self::hijos($root, 'CfdiRelacionados') as $grp) {
            $tipoRel = $a($grp, 'TipoRelacion');
            foreach (self::hijos($grp, 'CfdiRelacionado') as $r) {
                $u = strtoupper($a($r, 'UUID'));
                if ($u !== '') $c['relacionados'][] = array('tipo_relacion' => $tipoRel, 'uuid' => $u);
            }
        }

        // Complemento de pagos
        foreach (self::hijos($root, 'Complemento') as $cx) {
            foreach (self::hijos($cx, 'Pagos') as $pagos) {
                $verPago = $a($pagos, 'Version');
                foreach (self::hijos($pagos, 'Pago') as $p) {
                    $c['pagos'][] = self::leerPago($p, $verPago, $a, $f);
                }
            }
        }
        foreach ($c['pagos'] as $p) $c['total_pagos'] += round($p['monto'] * $p['tipo_cambio'], 2);

        return $c;
    }

    private static function leerPago($p, $verPago, $a, $f)
    {
        $monedaP = $a($p, 'MonedaP');
        $tcP = $f($p, 'TipoCambioP');
        if ($tcP <= 0 || $monedaP === 'MXN' || $monedaP === '') $tcP = 1.0;
        $pago = array(
            'version' => $verPago,
            'fecha_pago' => self::fechaSql($a($p, 'FechaPago')),
            'forma_pago' => $a($p, 'FormaDePagoP'),
            'moneda' => $monedaP, 'tipo_cambio' => $tcP,
            'monto' => $f($p, 'Monto'), 'num_operacion' => $a($p, 'NumOperacion'),
            'doctos' => array()
        );
        foreach (self::hijos($p, 'DoctoRelacionado') as $d) {
            // EquivalenciaDR (2.0) / TipoCambioDR (1.0): unidades de la moneda del
            // documento por cada unidad de la moneda del pago.
            $eq = $f($d, 'EquivalenciaDR');
            if ($eq <= 0) $eq = $f($d, 'TipoCambioDR');
            if ($eq <= 0) $eq = 1.0;
            $aMxn = function ($importeDr) use ($eq, $tcP) { return round($importeDr / $eq * $tcP, 2); };

            $doc = array(
                'uuid' => strtoupper($a($d, 'IdDocumento')),
                'serie' => $a($d, 'Serie'), 'folio' => $a($d, 'Folio'),
                'moneda' => $a($d, 'MonedaDR'), 'equivalencia' => $eq,
                'num_parcialidad' => $a($d, 'NumParcialidad') === '' ? null : (int)$a($d, 'NumParcialidad'),
                'saldo_ant' => $f($d, 'ImpSaldoAnt'), 'pagado' => $f($d, 'ImpPagado'), 'saldo_insoluto' => $f($d, 'ImpSaldoInsoluto'),
                'objeto_imp' => $a($d, 'ObjetoImpDR'),
                'base_iva_mxn' => 0.0, 'iva_mxn' => 0.0, 'ieps_mxn' => 0.0, 'iva_ret_mxn' => 0.0, 'isr_ret_mxn' => 0.0,
                'pagado_mxn' => 0.0, 'iva_estimado' => 0
            );
            // Pagos 1.0 a veces omite ImpPagado cuando hay un solo documento.
            if ($doc['pagado'] <= 0 && count(self::hijos($p, 'DoctoRelacionado')) === 1) $doc['pagado'] = $pago['monto'] * $eq;
            $doc['pagado_mxn'] = $aMxn($doc['pagado']);

            $impDr = self::hijo($d, 'ImpuestosDR');
            if ($impDr) {
                foreach (self::hijos(self::hijo($impDr, 'TrasladosDR'), 'TrasladoDR') as $t) {
                    $imp = $a($t, 'ImpuestoDR');
                    if ($imp === '002') {
                        $doc['iva_mxn'] += $aMxn($f($t, 'ImporteDR'));
                        if (strtoupper($a($t, 'TipoFactorDR')) !== 'EXENTO') $doc['base_iva_mxn'] += $aMxn($f($t, 'BaseDR'));
                    } elseif ($imp === '003') {
                        $doc['ieps_mxn'] += $aMxn($f($t, 'ImporteDR'));
                    }
                }
                foreach (self::hijos(self::hijo($impDr, 'RetencionesDR'), 'RetencionDR') as $r) {
                    $imp = $a($r, 'ImpuestoDR');
                    if ($imp === '002') $doc['iva_ret_mxn'] += $aMxn($f($r, 'ImporteDR'));
                    if ($imp === '001') $doc['isr_ret_mxn'] += $aMxn($f($r, 'ImporteDR'));
                }
            } elseif ($verPago === '1.0' || $verPago === '') {
                // Pagos 1.0 no desglosa impuestos: se prorratea con la factura (ver recalcularPagosEstimados).
                $doc['iva_estimado'] = 1;
            }
            $pago['doctos'][] = $doc;
        }
        return $pago;
    }

    /** Pagos 1.0: IVA proporcional = pagado * IVA factura / total factura. */
    public static function recalcularPagosEstimados($con, $idEmpresa)
    {
        $idEmpresa = (int)$idEmpresa;
        mysqli_query($con, "UPDATE sat_cfdi_pagos_doctos d
            JOIN sat_cfdi p ON p.id_cfdi=d.id_cfdi AND p.id_empresa=$idEmpresa
            JOIN sat_cfdi f ON f.id_empresa=p.id_empresa AND f.uuid=d.uuid_documento AND f.total>0
            SET d.iva_mxn = ROUND(d.pagado_mxn * f.iva / f.total, 2),
                d.base_iva_mxn = ROUND(d.pagado_mxn * (f.base_iva_16 + f.base_iva_8 + f.base_iva_0) / f.total, 2),
                d.iva_retenido_mxn = ROUND(d.pagado_mxn * f.iva_retenido / f.total, 2),
                d.isr_retenido_mxn = ROUND(d.pagado_mxn * f.isr_retenido / f.total, 2)
            WHERE d.iva_estimado=1");
    }

    private static function leerImpuestos($node, $sufijo)
    {
        $out = array();
        if (!$node) return $out;
        $a = function ($n, $k) { return ($n && $n->hasAttribute($k)) ? trim($n->getAttribute($k)) : ''; };
        foreach (array('Traslados' => array('Traslado', 'TRASLADO'), 'Retenciones' => array('Retencion', 'RETENCION')) as $grp => $def) {
            foreach (self::hijos(self::hijo($node, $grp), $def[0]) as $t) {
                $out[] = array(
                    'movimiento' => $def[1],
                    'impuesto' => $a($t, 'Impuesto'),
                    'tipo_factor' => $a($t, 'TipoFactor'),
                    'tasa' => $a($t, 'TasaOCuota') === '' ? null : (float)$a($t, 'TasaOCuota'),
                    'base' => (float)($a($t, 'Base') ?: 0),
                    'importe' => (float)($a($t, 'Importe') ?: 0),
                );
            }
        }
        return $out;
    }

    private static function hijos($node, $localName)
    {
        $out = array();
        if (!$node) return $out;
        foreach ($node->childNodes as $ch) {
            if ($ch->nodeType === XML_ELEMENT_NODE && $ch->localName === $localName) $out[] = $ch;
        }
        return $out;
    }

    private static function hijo($node, $localName)
    {
        $h = self::hijos($node, $localName);
        return $h ? $h[0] : null;
    }

    private static function fechaSql($iso)
    {
        if ($iso === '') return null;
        $ts = strtotime(substr($iso, 0, 19));
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /* ==================================================================
       METADATA
       Uuid~RfcEmisor~NombreEmisor~RfcReceptor~NombreReceptor~RfcPac~FechaEmision~
       FechaCertificacionSat~Monto~EfectoComprobante~Estatus~FechaCancelacion
       ================================================================== */
    public static function procesarMetadata($con, $s, $file)
    {
        $idEmpresa = (int)$s['id_empresa'];
        $rfcEmpresa = strtoupper($s['rfc']);
        $fh = fopen($file, 'r');
        if (!$fh) throw new Exception('No se pudo abrir');
        $header = null;
        $n = 0;
        while (($line = fgets($fh)) !== false) {
            $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));
            if ($line === '') continue;
            $cols = explode('~', $line);
            if ($header === null) {
                $header = array_map(function ($h) { return strtolower(trim($h)); }, $cols);
                continue;
            }
            $r = array();
            foreach ($header as $i => $h) $r[$h] = isset($cols[$i]) ? trim($cols[$i]) : '';
            $uuid = strtoupper(isset($r['uuid']) ? $r['uuid'] : '');
            if ($uuid === '') continue;

            $estatus = (isset($r['estatus']) && $r['estatus'] === '0') ? 'CANCELADO' : 'VIGENTE';
            $fechaCanc = !empty($r['fechacancelacion']) ? self::fechaSql($r['fechacancelacion']) : null;
            $rfcEm = strtoupper(isset($r['rfcemisor']) ? $r['rfcemisor'] : '');
            $rel = $rfcEm === $rfcEmpresa ? 'EMITIDA' : 'RECIBIDA';
            $tipo = strtoupper(substr(isset($r['efectocomprobante']) ? $r['efectocomprobante'] : '', 0, 1));
            $monto = isset($r['monto']) ? (float)$r['monto'] : 0;

            sat_exec($con, "INSERT INTO sat_cfdi(id_empresa,id_solicitud,uuid,tipo_relacion_empresa,rfc_emisor,nombre_emisor,rfc_receptor,nombre_receptor,
                    fecha_emision,fecha_timbrado,tipo_comprobante,total,estatus_sat,fecha_cancelacion,origen_dato)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'METADATA')
                ON DUPLICATE KEY UPDATE estatus_sat=VALUES(estatus_sat), fecha_cancelacion=VALUES(fecha_cancelacion), fecha_actualizacion=NOW()",
                'iisssssssssdss', array($idEmpresa, (int)$s['id_solicitud'], $uuid, $rel, $rfcEm, isset($r['nombreemisor']) ? $r['nombreemisor'] : '',
                    strtoupper(isset($r['rfcreceptor']) ? $r['rfcreceptor'] : ''), isset($r['nombrereceptor']) ? $r['nombrereceptor'] : '',
                    self::fechaSql(isset($r['fechaemision']) ? $r['fechaemision'] : ''), self::fechaSql(isset($r['fechacertificacionsat']) ? $r['fechacertificacionsat'] : ''),
                    $tipo, $monto, $estatus, $fechaCanc));
            if ($estatus === 'CANCELADO') {
                $row = sat_row($con, "SELECT id_cfdi FROM sat_cfdi WHERE id_empresa=? AND uuid=?", 'is', array($idEmpresa, $uuid));
                if ($row) {
                    mysqli_query($con, "DELETE d FROM sat_poliza_detalle d JOIN sat_polizas p ON p.id_poliza=d.id_poliza WHERE p.id_cfdi=" . (int)$row['id_cfdi']);
                    mysqli_query($con, "DELETE FROM sat_polizas WHERE id_cfdi=" . (int)$row['id_cfdi']);
                }
            }
            $n++;
        }
        fclose($fh);
        return $n;
    }
}
