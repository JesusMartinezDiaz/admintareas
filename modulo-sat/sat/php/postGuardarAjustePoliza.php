<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();
try {
    $idPoliza=(int)($_POST['id_poliza']??0);
    $idCuenta=(int)($_POST['id_cuenta']??0);
    $recordar=isset($_POST['recordar']) && $_POST['recordar']=='1';
    if($idPoliza<=0 || $idCuenta<=0) throw new Exception('Datos incompletos.');
    $r=AccountingService::guardarAjusteDiferencia($con,$idPoliza,$idCuenta,$recordar);
    if(!$r['ok']) throw new Exception($r['msg']);
    header('Location: ../poliza.php?id_poliza='.$idPoliza.'&msg='.urlencode($r['mensaje']));
} catch(Exception $e) {
    header('Location: ../poliza.php?id_poliza='.(int)($_POST['id_poliza']??0).'&msg='.urlencode('ERROR: '.$e->getMessage()));
}
