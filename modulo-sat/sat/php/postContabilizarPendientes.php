<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
$id=(int)($_GET['id_empresa']??0);$fi=$_GET['fecha_inicial']??date('Y-m-01');$ff=$_GET['fecha_final']??date('Y-m-t');
if($id<=0)die('Empresa inválida');AccountingService::bootstrapEmpresa($con,$id);
$rs=mysqli_query($con,"SELECT c.id_cfdi FROM sat_cfdi c LEFT JOIN sat_polizas p ON p.id_empresa=c.id_empresa AND p.id_cfdi=c.id_cfdi WHERE c.id_empresa=$id AND DATE(c.fecha_emision) BETWEEN '".mysqli_real_escape_string($con,$fi)."' AND '".mysqli_real_escape_string($con,$ff)."' AND UPPER(COALESCE(c.estatus_sat,'VIGENTE')) NOT LIKE '%CANCEL%' AND c.tipo_comprobante IN ('I','E') AND p.id_poliza IS NULL");
$n=0;while($r=mysqli_fetch_assoc($rs)){AccountingService::contabilizarCfdi($con,(int)$r['id_cfdi'],true);$n++;}
header('Location: ../contabilidad.php?id_empresa='.$id.'&fecha_inicial='.urlencode($fi).'&fecha_final='.urlencode($ff).'&msg='.urlencode("Se procesaron $n CFDI."));
