<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
try {
    $idPoliza=(int)($_GET['id_poliza']??0);
    if($idPoliza<=0) throw new Exception('Póliza inválida.');
    $q=mysqli_query($con,"SELECT id_cfdi FROM sat_polizas WHERE id_poliza=$idPoliza");
    if(!$q || !($p=mysqli_fetch_assoc($q))) throw new Exception('Póliza no encontrada.');
    $r=AccountingService::contabilizarCfdi($con,(int)$p['id_cfdi'],true);
    if(!$r['ok']) throw new Exception($r['msg']);
    header('Location: ../poliza.php?id_poliza='.(int)$r['id_poliza'].'&msg='.urlencode('Póliza recalculada correctamente. Diferencia: $'.number_format((float)$r['diferencia'],2)));
} catch(Exception $e) {
    die($e->getMessage());
}
