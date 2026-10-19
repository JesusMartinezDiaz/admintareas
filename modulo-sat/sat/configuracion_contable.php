<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
$con = sat_db();
sat_page_inicio('contabilidad', 'Configuración contable');
$id=(int)($_GET['id_empresa']??0);if($id<=0)die('Empresa inválida');AccountingService::bootstrapEmpresa($con,$id);
$emp=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM sat_empresas WHERE id_empresa=$id"));
$cfg=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM sat_config_contable WHERE id_empresa=$id"));
$cuentas=array();$rs=mysqli_query($con,"SELECT id_cuenta,numero_cuenta,nombre FROM sat_cuentas_contables WHERE id_empresa=$id AND activo=1 ORDER BY numero_cuenta");while($rs&&($r=mysqli_fetch_assoc($rs)))$cuentas[]=$r;
function selectCuentaCfg($name,$actual,$cuentas){echo '<select class="form-control" name="'.e($name).'" required><option value="">Seleccione...</option>';foreach($cuentas as $c){echo '<option value="'.(int)$c['id_cuenta'].'" '.((int)$actual===(int)$c['id_cuenta']?'selected':'').'>'.e($c['numero_cuenta'].' - '.$c['nombre']).'</option>';}echo '</select>';}

?>
<div class="d-flex justify-content-between mb-3"><div><h3>Configuración contable</h3><div class="text-muted"><?=e($emp['rfc'].' - '.$emp['razon_social'])?></div></div><a class="btn btn-outline-secondary align-self-start" href="contabilidad.php?id_empresa=<?=$id?>">Regresar</a></div>
<div class="alert alert-info">Aquí defines las cuentas exactas que usará el motor automático. Los impuestos 001 (ISR) y 002 (IVA) se separan de los conceptos y se contabilizan en estas cuentas.</div>
<form method="post" action="php/postGuardarConfigContable.php"><input type="hidden" name="id_empresa" value="<?=$id?>"><div class="sat-card p-4"><div class="row g-3">
<div class="col-md-6"><label class="form-label">Clientes</label><?php selectCuentaCfg('id_cuenta_clientes',$cfg['id_cuenta_clientes'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">Proveedores</label><?php selectCuentaCfg('id_cuenta_proveedores',$cfg['id_cuenta_proveedores'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">Ingreso por defecto</label><?php selectCuentaCfg('id_cuenta_ingresos_default',$cfg['id_cuenta_ingresos_default'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">Gasto por defecto</label><?php selectCuentaCfg('id_cuenta_gastos_default',$cfg['id_cuenta_gastos_default'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">IVA trasladado / por cobrar</label><?php selectCuentaCfg('id_cuenta_iva_trasladado',$cfg['id_cuenta_iva_trasladado'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">IVA acreditable / pendiente</label><?php selectCuentaCfg('id_cuenta_iva_acreditable',$cfg['id_cuenta_iva_acreditable'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">ISR retenido a favor</label><?php selectCuentaCfg('id_cuenta_isr_retenido_favor',$cfg['id_cuenta_isr_retenido_favor'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">ISR retenido por pagar</label><?php selectCuentaCfg('id_cuenta_isr_retenido_pagar',$cfg['id_cuenta_isr_retenido_pagar'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">IVA retenido a favor</label><?php selectCuentaCfg('id_cuenta_iva_retenido_favor',$cfg['id_cuenta_iva_retenido_favor'],$cuentas);?></div>
<div class="col-md-6"><label class="form-label">IVA retenido por pagar</label><?php selectCuentaCfg('id_cuenta_iva_retenido_pagar',$cfg['id_cuenta_iva_retenido_pagar'],$cuentas);?></div>
<div class="col-md-3"><label class="form-label">Tolerancia de diferencia</label><input class="form-control" type="number" min="0" step="0.01" name="tolerancia" value="<?=e($cfg['tolerancia'])?>"></div>
<div class="col-md-9 d-flex align-items-end"><button class="btn btn-primary">Guardar configuración</button></div>
</div></div></form>
<?php sat_page_fin(); include('../assets/footer.php');?>
