<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
$con = sat_db();
sat_page_inicio('contabilidad', 'Póliza');
$id=(int)($_GET['id_poliza']??0);
$p=mysqli_fetch_assoc(mysqli_query($con,"SELECT p.*,c.id_cfdi,c.tipo_relacion_empresa,c.tipo_comprobante,c.rfc_emisor,c.nombre_emisor,c.rfc_receptor,c.nombre_receptor FROM sat_polizas p JOIN sat_cfdi c ON c.id_cfdi=p.id_cfdi WHERE p.id_poliza=$id"));
if(!$p)die('Póliza no encontrada');
AccountingService::bootstrapEmpresa($con,(int)$p['id_empresa']);
$d=mysqli_query($con,"SELECT d.*,c.numero_cuenta,c.nombre cuenta,r.nombre regla FROM sat_poliza_detalle d JOIN sat_cuentas_contables c ON c.id_cuenta=d.id_cuenta LEFT JOIN sat_reglas_contables r ON r.id_regla=d.id_regla WHERE d.id_poliza=$id ORDER BY d.id_detalle");
$cuentas=mysqli_query($con,"SELECT id_cuenta,numero_cuenta,nombre FROM sat_cuentas_contables WHERE id_empresa=".(int)$p['id_empresa']." AND activo=1 AND permite_movimientos=1 ORDER BY numero_cuenta");
$imp=mysqli_query($con,"SELECT movimiento,impuesto,COALESCE(SUM(importe),0) importe FROM sat_cfdi_impuestos_detalle WHERE id_cfdi=".(int)$p['id_cfdi']." AND nivel='CFDI' GROUP BY movimiento,impuesto");
$impuestos=array();while($imp && ($r=mysqli_fetch_assoc($imp)))$impuestos[]=$r;
$diferencia=abs((float)$p['total_cargos']-(float)$p['total_abonos']);
$ladoSugerido=((float)$p['total_cargos']>(float)$p['total_abonos'])?'ABONO':'CARGO';
$msg=$_GET['msg']??'';

?>
<?php if($msg!==''):?><div class="alert alert-info"><?=e($msg)?></div><?php endif?>
<div class="d-flex justify-content-between align-items-start mb-3"><div><h3>Póliza #<?=$id?></h3><p class="text-muted mb-0"><?=e($p['fecha'].' · '.$p['uuid'])?></p></div><div><a href="php/postRecalcularPoliza.php?id_poliza=<?=$id?>" class="btn btn-warning" onclick="return confirm('¿Recalcular esta póliza con las reglas e impuestos actuales?')">Recalcular póliza</a> <a href="contabilidad.php?id_empresa=<?=$p['id_empresa']?>" class="btn btn-outline-secondary">Regresar</a></div></div>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="sat-card p-3"><small class="text-muted">Debe</small><h4 class="mb-0">$<?=number_format($p['total_cargos'],2)?></h4></div></div>
  <div class="col-md-4"><div class="sat-card p-3"><small class="text-muted">Haber</small><h4 class="mb-0">$<?=number_format($p['total_abonos'],2)?></h4></div></div>
  <div class="col-md-4"><div class="sat-card p-3 border <?=$diferencia>0.02?'border-danger':'border-success'?>"><small class="text-muted">Diferencia</small><h4 class="mb-0 <?=$diferencia>0.02?'text-danger':'text-success'?>">$<?=number_format($diferencia,2)?></h4></div></div>
</div>
<div class="sat-card p-4 mb-3"><div class="mb-3"><strong><?=e($p['concepto'])?></strong><br><span class="<?=$p['estatus']==='CONTABILIZADA'?'sat-badge ok':'sat-badge warn'?>"><?=e($p['estatus'])?></span> <?=e($p['mensaje_revision'])?></div>
<?php if(count($impuestos)>0):?><div class="mb-3"><strong>Impuestos detectados en el CFDI:</strong> <?php foreach($impuestos as $x): $nom=$x['impuesto']==='001'?'ISR':($x['impuesto']==='002'?'IVA':($x['impuesto']==='003'?'IEPS':'Impuesto '.$x['impuesto'])); ?><span class="sat-badge gris"><?=e($x['movimiento'].' '.$nom)?>: $<?=number_format($x['importe'],2)?></span><?php endforeach?></div><?php endif?>
<div class="table-responsive"><table class="sat-tabla"><thead><tr><th>Cuenta</th><th>Concepto</th><th>Origen</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead><tbody><?php while($x=mysqli_fetch_assoc($d)):?><tr><td><?=e($x['numero_cuenta'].' - '.$x['cuenta'])?></td><td><?=e($x['concepto'])?></td><td><?=e($x['clasificacion'].($x['regla']?' · '.$x['regla']:''))?></td><td class="text-end"><?=number_format($x['cargo'],2)?></td><td class="text-end"><?=number_format($x['abono'],2)?></td></tr><?php endwhile?><tr class="fw-bold"><td colspan="3">Totales</td><td class="text-end"><?=number_format($p['total_cargos'],2)?></td><td class="text-end"><?=number_format($p['total_abonos'],2)?></td></tr></tbody></table></div></div>
<?php if($diferencia>0.02):?>
<div class="sat-card p-4 border-danger"><h5 class="text-danger">Resolver diferencia de $<?=number_format($diferencia,2)?></h5><p class="text-muted">Para cuadrar la póliza hace falta un <strong><?=e($ladoSugerido)?></strong> por este importe. Selecciona la cuenta correcta. Si marcas “recordar”, el sistema aprenderá esta asignación para futuras diferencias del mismo RFC y tipo de CFDI.</p><form method="post" action="php/postGuardarAjustePoliza.php" class="row g-3"><input type="hidden" name="id_poliza" value="<?=$id?>"><div class="col-md-7"><label class="form-label">Cuenta para la diferencia</label><select name="id_cuenta" class="form-control" required><option value="">Seleccione una cuenta...</option><?php while($c=mysqli_fetch_assoc($cuentas)):?><option value="<?=$c['id_cuenta']?>"><?=e($c['numero_cuenta'].' - '.$c['nombre'])?></option><?php endwhile?></select></div><div class="col-md-2"><label class="form-label">Movimiento</label><input class="form-control" value="<?=e($ladoSugerido)?>" readonly></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100">Aplicar diferencia</button></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="recordar" name="recordar" checked><label class="form-check-label" for="recordar">Recordar esta decisión y aplicarla automáticamente cuando vuelva a ocurrir con este RFC.</label></div></div></form></div>
<?php endif?>
<?php sat_page_fin(); include('../assets/footer.php');?>
