<?php
class AccountingService
{
    public static function normalizar($txt)
    {
        $txt = trim(mb_strtolower((string)$txt, 'UTF-8'));
        $txt = strtr($txt, array('á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'));
        $txt = preg_replace('/[^a-z0-9 ]+/',' ', $txt);
        return trim(preg_replace('/\s+/',' ', $txt));
    }

    public static function bootstrapEmpresa($con, $idEmpresa)
    {
        $idEmpresa = (int)$idEmpresa;
        $q = mysqli_query($con, "SELECT COUNT(*) n FROM sat_cuentas_contables WHERE id_empresa=$idEmpresa");
        $n = $q ? (int)mysqli_fetch_assoc($q)['n'] : 0;
        if ($n === 0) {
            $defs = array(
                array('105-01','Clientes nacionales','105.01','D','ACTIVO'),
                array('201-01','Proveedores nacionales','201.01','A','PASIVO'),
                array('401-01','Ventas / servicios','401.01','A','INGRESO'),
                array('601-84','Gastos generales por clasificar','601.84','D','GASTO'),
                array('209-01','IVA trasladado no cobrado','209.01','A','PASIVO'),
                array('119-01','IVA acreditable pendiente de pago','119.01','D','ACTIVO'),
                array('118-90','ISR retenido a favor',NULL,'D','ACTIVO'),
                array('118-91','IVA retenido a favor',NULL,'D','ACTIVO'),
                array('216-04','ISR retenido por pagar','216.04','A','PASIVO'),
                array('216-10','IVA retenido por pagar','216.10','A','PASIVO')
            );
            $ids = array();
            foreach ($defs as $d) {
                $num = mysqli_real_escape_string($con,$d[0]);
                $nom = mysqli_real_escape_string($con,$d[1]);
                $agr = $d[2] === NULL ? 'NULL' : "'".mysqli_real_escape_string($con,$d[2])."'";
                mysqli_query($con,"INSERT INTO sat_cuentas_contables(id_empresa,numero_cuenta,nombre,codigo_agrupador,naturaleza,tipo_cuenta) VALUES($idEmpresa,'$num','$nom',$agr,'{$d[3]}','{$d[4]}')");
                $ids[$d[0]] = (int)mysqli_insert_id($con);
            }
            mysqli_query($con,"INSERT IGNORE INTO sat_config_contable(id_empresa,id_cuenta_clientes,id_cuenta_proveedores,id_cuenta_ingresos_default,id_cuenta_gastos_default,id_cuenta_iva_trasladado,id_cuenta_iva_acreditable,id_cuenta_isr_retenido_favor,id_cuenta_iva_retenido_favor,id_cuenta_isr_retenido_pagar,id_cuenta_iva_retenido_pagar) VALUES($idEmpresa,{$ids['105-01']},{$ids['201-01']},{$ids['401-01']},{$ids['601-84']},{$ids['209-01']},{$ids['119-01']},{$ids['118-90']},{$ids['118-91']},{$ids['216-04']},{$ids['216-10']})");
        } else {
            mysqli_query($con,"INSERT IGNORE INTO sat_config_contable(id_empresa) VALUES($idEmpresa)");
        }
    }

    private static function cfg($con,$idEmpresa)
    {
        self::bootstrapEmpresa($con,$idEmpresa);
        $q=mysqli_query($con,"SELECT * FROM sat_config_contable WHERE id_empresa=".(int)$idEmpresa);
        return $q ? mysqli_fetch_assoc($q) : null;
    }

    private static function buscarRegla($con,$cfdi,$concepto)
    {
        $idEmpresa=(int)$cfdi['id_empresa'];
        $rel=mysqli_real_escape_string($con,$cfdi['tipo_relacion_empresa']);
        $rfc=mysqli_real_escape_string($con,$cfdi['tipo_relacion_empresa']==='EMITIDA'?$cfdi['rfc_receptor']:$cfdi['rfc_emisor']);
        $clave=mysqli_real_escape_string($con,(string)$concepto['clave_prod_serv']);
        $desc=self::normalizar($concepto['descripcion']);
        $sql="SELECT * FROM sat_reglas_contables WHERE id_empresa=$idEmpresa AND activo=1 AND (tipo_relacion='$rel' OR tipo_relacion='AMBAS') AND (tipo_comprobante IS NULL OR tipo_comprobante='' OR tipo_comprobante='".mysqli_real_escape_string($con,$cfdi['tipo_comprobante'])."') AND (rfc_tercero IS NULL OR rfc_tercero='' OR rfc_tercero='$rfc') AND (clave_prod_serv IS NULL OR clave_prod_serv='' OR clave_prod_serv='$clave') ORDER BY prioridad DESC, confianza DESC, usos DESC";
        $rs=mysqli_query($con,$sql);
        if (!$rs) return null;
        while($r=mysqli_fetch_assoc($rs)) {
            $pat=self::normalizar($r['patron_descripcion']);
            if ($pat==='' || strpos($desc,$pat)!==false) return $r;
        }
        return null;
    }

    private static function buscarReglaDiferencia($con,$cfdi,$ladoNecesario)
    {
        $idEmpresa=(int)$cfdi['id_empresa'];
        $rel=mysqli_real_escape_string($con,$cfdi['tipo_relacion_empresa']);
        $tipo=mysqli_real_escape_string($con,$cfdi['tipo_comprobante']);
        $rfc=mysqli_real_escape_string($con,$cfdi['tipo_relacion_empresa']==='EMITIDA'?$cfdi['rfc_receptor']:$cfdi['rfc_emisor']);
        $lado=mysqli_real_escape_string($con,$ladoNecesario);
        $sql="SELECT * FROM sat_reglas_diferencias WHERE id_empresa=$idEmpresa AND activo=1 AND tipo_relacion='$rel' AND tipo_comprobante='$tipo' AND rfc_tercero='$rfc' AND lado='$lado' ORDER BY confianza DESC, usos DESC, id_regla_diferencia DESC LIMIT 1";
        $q=mysqli_query($con,$sql);
        return $q ? mysqli_fetch_assoc($q) : null;
    }

    private static function addDetalle($con,$idPoliza,$idCuenta,$idConcepto,$concepto,$cargo,$abono,$idRegla,$confianza,$clasif)
    {
        $st=mysqli_prepare($con,"INSERT INTO sat_poliza_detalle(id_poliza,id_cuenta,id_concepto,concepto,cargo,abono,id_regla,confianza,clasificacion) VALUES(?,?,?,?,?,?,?,?,?)");
        $idConcepto = $idConcepto ? (int)$idConcepto : null;
        $idRegla = $idRegla ? (int)$idRegla : null;
        mysqli_stmt_bind_param($st,'iiisddids',$idPoliza,$idCuenta,$idConcepto,$concepto,$cargo,$abono,$idRegla,$confianza,$clasif);
        mysqli_stmt_execute($st);
    }

    private static function impuestoTotalSeguro($con,$idCfdi,$mov,$imp)
    {
        $idCfdi=(int)$idCfdi;
        $mov=mysqli_real_escape_string($con,$mov);
        $imp=mysqli_real_escape_string($con,$imp);
        $q=mysqli_query($con,"SELECT COUNT(*) n,COALESCE(SUM(importe),0) t FROM sat_cfdi_impuestos_detalle WHERE id_cfdi=$idCfdi AND nivel='CFDI' AND movimiento='$mov' AND impuesto='$imp'");
        if($q && ($r=mysqli_fetch_assoc($q)) && (int)$r['n']>0) return (float)$r['t'];
        $q=mysqli_query($con,"SELECT COALESCE(SUM(importe),0) t FROM sat_cfdi_impuestos_detalle WHERE id_cfdi=$idCfdi AND nivel='CONCEPTO' AND movimiento='$mov' AND impuesto='$imp'");
        if($q && ($r=mysqli_fetch_assoc($q))) return (float)$r['t'];
        return 0;
    }

    private static function totalesPoliza($con,$idPoliza)
    {
        $q=mysqli_query($con,"SELECT COALESCE(SUM(cargo),0)c,COALESCE(SUM(abono),0)a FROM sat_poliza_detalle WHERE id_poliza=".(int)$idPoliza);
        $r=$q?mysqli_fetch_assoc($q):array('c'=>0,'a'=>0);
        $c=(float)$r['c']; $a=(float)$r['a'];
        return array('cargos'=>$c,'abonos'=>$a,'diferencia'=>abs($c-$a),'firmada'=>$c-$a);
    }

    public static function actualizarEstadoPoliza($con,$idPoliza)
    {
        $idPoliza=(int)$idPoliza;
        $q=mysqli_query($con,"SELECT p.*,c.id_empresa FROM sat_polizas p JOIN sat_cfdi c ON c.id_cfdi=p.id_cfdi WHERE p.id_poliza=$idPoliza");
        if(!$q || !($p=mysqli_fetch_assoc($q))) return false;
        $cfg=self::cfg($con,(int)$p['id_empresa']);
        $t=self::totalesPoliza($con,$idPoliza);
        $tol=(float)$cfg['tolerancia'];
        $qDef=mysqli_query($con,"SELECT COUNT(*) n FROM sat_poliza_detalle WHERE id_poliza=$idPoliza AND clasificacion='DEFAULT'");
        $def=$qDef?(int)mysqli_fetch_assoc($qDef)['n']:0;
        $status=($t['diferencia']<=$tol && $def===0)?'CONTABILIZADA':'REVISAR';
        $msg='';
        if($t['diferencia']>$tol) $msg='Póliza descuadrada por $'.number_format($t['diferencia'],2).'.';
        elseif($def>0) $msg='Hay conceptos usando la cuenta por defecto; clasifícalos para mejorar las reglas.';
        $m=mysqli_real_escape_string($con,$msg);
        mysqli_query($con,"UPDATE sat_polizas SET total_cargos={$t['cargos']},total_abonos={$t['abonos']},estatus='$status',mensaje_revision='$m',fecha_contabilizacion=".($status==='CONTABILIZADA'?'NOW()':'NULL')." WHERE id_poliza=$idPoliza");
        return array_merge($t,array('estatus'=>$status,'mensaje'=>$msg));
    }

    public static function contabilizarCfdi($con,$idCfdi,$forzar=false)
    {
        $idCfdi=(int)$idCfdi;
        $q=mysqli_query($con,"SELECT * FROM sat_cfdi WHERE id_cfdi=$idCfdi");
        if(!$q || !($c=mysqli_fetch_assoc($q))) return array('ok'=>false,'msg'=>'CFDI no encontrado');
        $cfg=self::cfg($con,(int)$c['id_empresa']);
        if(!$cfg) return array('ok'=>false,'msg'=>'Falta configuración contable');
        if(!$forzar && !(int)$cfg['auto_contabilizar']) return array('ok'=>false,'msg'=>'Contabilización automática desactivada');
        if(stripos((string)$c['estatus_sat'],'CANCEL')===0) return array('ok'=>false,'msg'=>'CFDI cancelado');
        $tipo=strtoupper((string)$c['tipo_comprobante']);
        if(!in_array($tipo,array('I','E'),true)) return array('ok'=>false,'msg'=>'Tipo CFDI no automatizado: '.$tipo);

        // Se borra también el detalle (la versión anterior lo dejaba huérfano).
        mysqli_query($con,"DELETE d FROM sat_poliza_detalle d JOIN sat_polizas p ON p.id_poliza=d.id_poliza WHERE p.id_empresa=".(int)$c['id_empresa']." AND p.id_cfdi=$idCfdi");
        mysqli_query($con,"DELETE FROM sat_polizas WHERE id_empresa=".(int)$c['id_empresa']." AND id_cfdi=$idCfdi");
        // Las pólizas se registran en MXN: CFDI en otra moneda se convierten con su TipoCambio.
        $tc=(isset($c['tipo_cambio']) && (float)$c['tipo_cambio']>0)?(float)$c['tipo_cambio']:1.0;
        $fecha=substr($c['fecha_emision'],0,10);
        $tercero=$c['tipo_relacion_empresa']==='EMITIDA'?$c['nombre_receptor']:$c['nombre_emisor'];
        $conceptoPol=($c['tipo_relacion_empresa']==='EMITIDA'?'CFDI emitido a ':'CFDI recibido de ').$tercero.' UUID '.$c['uuid'];
        $st=mysqli_prepare($con,"INSERT INTO sat_polizas(id_empresa,id_cfdi,tipo_poliza,fecha,concepto,uuid,estatus,origen) VALUES(?,?,?,?,?,?,'BORRADOR','AUTOMATICA')");
        $tp=$c['tipo_relacion_empresa']==='EMITIDA'?'INGRESO':'EGRESO';
        mysqli_stmt_bind_param($st,'iissss',$c['id_empresa'],$idCfdi,$tp,$fecha,$conceptoPol,$c['uuid']);
        mysqli_stmt_execute($st);
        $idPol=(int)mysqli_insert_id($con);

        $requiereRevision=false;
        $rs=mysqli_query($con,"SELECT * FROM sat_cfdi_conceptos WHERE id_cfdi=$idCfdi ORDER BY id_concepto");
        while($x=mysqli_fetch_assoc($rs)) {
            $importe=round(max(0,(float)$x['importe']-(float)$x['descuento'])*$tc,2);
            if($importe<=0) continue;
            $reg=self::buscarRegla($con,$c,$x);
            $idCuenta=$reg?(int)$reg['id_cuenta']:(int)($c['tipo_relacion_empresa']==='EMITIDA'?$cfg['id_cuenta_ingresos_default']:$cfg['id_cuenta_gastos_default']);
            $lado=$reg?$reg['lado']:'AUTO';
            if($lado==='AUTO') $lado=$c['tipo_relacion_empresa']==='EMITIDA'?'ABONO':'CARGO';
            if($tipo==='E') $lado=$lado==='CARGO'?'ABONO':'CARGO';
            $cargo=$lado==='CARGO'?$importe:0;
            $abono=$lado==='ABONO'?$importe:0;
            self::addDetalle($con,$idPol,$idCuenta,(int)$x['id_concepto'],$x['descripcion'],$cargo,$abono,$reg?(int)$reg['id_regla']:null,$reg?(float)$reg['confianza']:0.50,$reg?'REGLA':'DEFAULT');
            if($reg) mysqli_query($con,"UPDATE sat_reglas_contables SET usos=usos+1,fecha_ultimo_uso=NOW() WHERE id_regla=".(int)$reg['id_regla']);
            else $requiereRevision=true;
        }

        // SAT: 001=ISR, 002=IVA, 003=IEPS. Separamos al menos IVA e ISR de forma explícita.
        // Si el XML trae impuestos a nivel CFDI usamos ese total; si no, sumamos los impuestos por concepto.
        $ivaTras=round(self::impuestoTotalSeguro($con,$idCfdi,'TRASLADO','002')*$tc,2);
        $retIsr=round(self::impuestoTotalSeguro($con,$idCfdi,'RETENCION','001')*$tc,2);
        $retIva=round(self::impuestoTotalSeguro($con,$idCfdi,'RETENCION','002')*$tc,2);

        $emit=$c['tipo_relacion_empresa']==='EMITIDA';
        $reversa=$tipo==='E';

        if($ivaTras>0.000001) {
            $cuenta=(int)($emit?$cfg['id_cuenta_iva_trasladado']:$cfg['id_cuenta_iva_acreditable']);
            if($cuenta>0) {
                $lado=$emit?'ABONO':'CARGO';
                if($reversa)$lado=$lado==='CARGO'?'ABONO':'CARGO';
                self::addDetalle($con,$idPol,$cuenta,null,$emit?'IVA trasladado':'IVA acreditable',$lado==='CARGO'?$ivaTras:0,$lado==='ABONO'?$ivaTras:0,null,1,'IMPUESTO');
            }
        }
        if($retIsr>0.000001) {
            $cuenta=(int)($emit?$cfg['id_cuenta_isr_retenido_favor']:$cfg['id_cuenta_isr_retenido_pagar']);
            if($cuenta>0) {
                $lado=$emit?'CARGO':'ABONO';
                if($reversa)$lado=$lado==='CARGO'?'ABONO':'CARGO';
                self::addDetalle($con,$idPol,$cuenta,null,$emit?'ISR retenido a favor':'ISR retenido por pagar',$lado==='CARGO'?$retIsr:0,$lado==='ABONO'?$retIsr:0,null,1,'IMPUESTO');
            }
        }
        if($retIva>0.000001) {
            $cuenta=(int)($emit?$cfg['id_cuenta_iva_retenido_favor']:$cfg['id_cuenta_iva_retenido_pagar']);
            if($cuenta>0) {
                $lado=$emit?'CARGO':'ABONO';
                if($reversa)$lado=$lado==='CARGO'?'ABONO':'CARGO';
                self::addDetalle($con,$idPol,$cuenta,null,$emit?'IVA retenido a favor':'IVA retenido por pagar',$lado==='CARGO'?$retIva:0,$lado==='ABONO'?$retIva:0,null,1,'IMPUESTO');
            }
        }

        $total=round((float)$c['total']*$tc,2);
        $cuentaContr=(int)($emit?$cfg['id_cuenta_clientes']:$cfg['id_cuenta_proveedores']);
        $lado=$emit?'CARGO':'ABONO';
        if($reversa)$lado=$lado==='CARGO'?'ABONO':'CARGO';
        self::addDetalle($con,$idPol,$cuentaContr,null,$emit?'Cliente':'Proveedor',$lado==='CARGO'?$total:0,$lado==='ABONO'?$total:0,null,1,'AUTOMATICA');

        // Antes de dejar la póliza en revisión buscamos una regla aprendida para diferencias residuales.
        $tot=self::totalesPoliza($con,$idPol);
        $tol=(float)$cfg['tolerancia'];
        $ajusteAprendido=false;
        if($tot['diferencia']>$tol) {
            $ladoNecesario=$tot['firmada']>0?'ABONO':'CARGO';
            $regDif=self::buscarReglaDiferencia($con,$c,$ladoNecesario);
            if($regDif) {
                $monto=$tot['diferencia'];
                self::addDetalle($con,$idPol,(int)$regDif['id_cuenta'],null,'Ajuste aprendido por diferencia',$ladoNecesario==='CARGO'?$monto:0,$ladoNecesario==='ABONO'?$monto:0,null,(float)$regDif['confianza'],'AJUSTE_APRENDIDO');
                mysqli_query($con,"UPDATE sat_reglas_diferencias SET usos=usos+1,fecha_ultimo_uso=NOW() WHERE id_regla_diferencia=".(int)$regDif['id_regla_diferencia']);
                $ajusteAprendido=true;
            }
        }

        $estado=self::actualizarEstadoPoliza($con,$idPol);
        if($ajusteAprendido && $estado && $estado['estatus']==='CONTABILIZADA') {
            $msg='Póliza cuadrada con una regla aprendida de diferencia.';
            mysqli_query($con,"UPDATE sat_polizas SET mensaje_revision='".mysqli_real_escape_string($con,$msg)."' WHERE id_poliza=$idPol");
            $estado['mensaje']=$msg;
        }
        return array('ok'=>true,'id_poliza'=>$idPol,'estatus'=>$estado['estatus'],'cargos'=>$estado['cargos'],'abonos'=>$estado['abonos'],'diferencia'=>$estado['diferencia'],'mensaje'=>$estado['mensaje']);
    }

    public static function guardarAjusteDiferencia($con,$idPoliza,$idCuenta,$recordar=true)
    {
        $idPoliza=(int)$idPoliza; $idCuenta=(int)$idCuenta;
        $q=mysqli_query($con,"SELECT p.*,c.tipo_relacion_empresa,c.tipo_comprobante,c.rfc_emisor,c.rfc_receptor FROM sat_polizas p JOIN sat_cfdi c ON c.id_cfdi=p.id_cfdi WHERE p.id_poliza=$idPoliza");
        if(!$q || !($p=mysqli_fetch_assoc($q))) return array('ok'=>false,'msg'=>'Póliza no encontrada');
        $q=mysqli_query($con,"SELECT id_cuenta FROM sat_cuentas_contables WHERE id_cuenta=$idCuenta AND id_empresa=".(int)$p['id_empresa']." AND activo=1 LIMIT 1");
        if(!$q || mysqli_num_rows($q)===0) return array('ok'=>false,'msg'=>'Cuenta contable inválida');
        $cfg=self::cfg($con,(int)$p['id_empresa']);
        $tot=self::totalesPoliza($con,$idPoliza);
        if($tot['diferencia']<=(float)$cfg['tolerancia']) return array('ok'=>false,'msg'=>'La póliza ya está cuadrada');
        $lado=$tot['firmada']>0?'ABONO':'CARGO';
        $monto=$tot['diferencia'];
        self::addDetalle($con,$idPoliza,$idCuenta,null,'Ajuste manual por diferencia',$lado==='CARGO'?$monto:0,$lado==='ABONO'?$monto:0,null,1,'AJUSTE');

        if($recordar) {
            $rel=mysqli_real_escape_string($con,$p['tipo_relacion_empresa']);
            $tipo=mysqli_real_escape_string($con,$p['tipo_comprobante']);
            $rfc=mysqli_real_escape_string($con,$p['tipo_relacion_empresa']==='EMITIDA'?$p['rfc_receptor']:$p['rfc_emisor']);
            $ladoSql=mysqli_real_escape_string($con,$lado);
            $sql="INSERT INTO sat_reglas_diferencias(id_empresa,tipo_relacion,tipo_comprobante,rfc_tercero,id_cuenta,lado,confianza,usos,activo,fecha_alta,fecha_ultimo_uso) VALUES(".(int)$p['id_empresa'].",'$rel','$tipo','$rfc',$idCuenta,'$ladoSql',1.0000,1,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE id_cuenta=VALUES(id_cuenta),confianza=1.0000,usos=usos+1,activo=1,fecha_ultimo_uso=NOW()";
            mysqli_query($con,$sql);
        }
        $estado=self::actualizarEstadoPoliza($con,$idPoliza);
        return array('ok'=>true,'id_poliza'=>$idPoliza,'lado'=>$lado,'monto'=>$monto,'estatus'=>$estado['estatus'],'mensaje'=>'Ajuste aplicado correctamente'.($recordar?' y regla aprendida.':'.'));
    }
}
