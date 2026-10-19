<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
$id=(int)($_POST['id_empresa']??0);if($id<=0)die('Empresa inválida');
$campos=array('id_cuenta_clientes','id_cuenta_proveedores','id_cuenta_ingresos_default','id_cuenta_gastos_default','id_cuenta_iva_trasladado','id_cuenta_iva_acreditable','id_cuenta_isr_retenido_favor','id_cuenta_iva_retenido_favor','id_cuenta_isr_retenido_pagar','id_cuenta_iva_retenido_pagar');
$vals=array();foreach($campos as $c){$v=(int)($_POST[$c]??0);if($v<=0)die('Falta configurar '.$c);$q=mysqli_query($con,"SELECT 1 FROM sat_cuentas_contables WHERE id_cuenta=$v AND id_empresa=$id LIMIT 1");if(!$q||mysqli_num_rows($q)===0)die('Cuenta inválida para '.$c);$vals[$c]=$v;}
$tol=(float)($_POST['tolerancia']??0.02);if($tol<0)$tol=0.02;
$sql="UPDATE sat_config_contable SET ";$parts=array();foreach($campos as $c)$parts[]="$c=".(int)$vals[$c];$sql.=implode(',',$parts).",tolerancia=$tol WHERE id_empresa=$id";mysqli_query($con,$sql);
header('Location: ../configuracion_contable.php?id_empresa='.$id);
