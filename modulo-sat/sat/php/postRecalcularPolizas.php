<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
$id=(int)($_GET['id_empresa']??0);
$fi=$_GET['fecha_inicial']??date('Y-m-01');
$ff=$_GET['fecha_final']??date('Y-m-t');
if($id<=0) die('Empresa inválida');
$ids=array();
$rs=mysqli_query($con,"SELECT c.id_cfdi FROM sat_cfdi c WHERE c.id_empresa=$id AND DATE(c.fecha_emision) BETWEEN '".mysqli_real_escape_string($con,$fi)."' AND '".mysqli_real_escape_string($con,$ff)."' AND UPPER(COALESCE(c.estatus_sat,'VIGENTE')) NOT LIKE '%CANCEL%' AND c.tipo_comprobante IN ('I','E') ORDER BY c.fecha_emision,c.id_cfdi");
while($rs && ($r=mysqli_fetch_assoc($rs))) $ids[]=(int)$r['id_cfdi'];
$ok=0;$err=0;
foreach($ids as $idCfdi){$r=AccountingService::contabilizarCfdi($con,$idCfdi,true);if($r['ok'])$ok++;else$err++;}
header('Location: ../contabilidad.php?id_empresa='.$id.'&fecha_inicial='.urlencode($fi).'&fecha_final='.urlencode($ff).'&msg='.urlencode("Recalculadas: $ok. Errores: $err."));
