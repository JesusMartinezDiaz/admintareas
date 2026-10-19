<?php
require_once __DIR__ . '/_init.php';
sat_requiere_admin();

$id = (int)sat_post('id_empresa', 0);
$razon = sat_post('razon_social');
$rfc = strtoupper(sat_post('rfc'));
$regimen = sat_post('regimen_fiscal');
$tipo = strlen($rfc) === 13 ? 'FISICA' : 'MORAL';
$email = sat_post('email_contacto');
$tel = sat_post('telefono');
if ($razon === '' || $rfc === '') throw new Exception('Razón social y RFC son obligatorios.');
if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) throw new Exception('El RFC no tiene un formato válido.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido.');

if ($id > 0) {
    sat_exec($con, "UPDATE sat_empresas SET razon_social=?, rfc=?, regimen_fiscal=?, tipo_persona=?, email_contacto=?, telefono=? WHERE id_empresa=?",
        'ssssssi', array($razon, $rfc, $regimen, $tipo, $email, $tel, $id));
} else {
    $ex = sat_row($con, "SELECT id_empresa FROM sat_empresas WHERE rfc=?", 's', array($rfc));
    if ($ex) throw new Exception('Ya existe una empresa con el RFC ' . $rfc . '.');
    sat_exec($con, "INSERT INTO sat_empresas(razon_social,rfc,regimen_fiscal,tipo_persona,email_contacto,telefono) VALUES (?,?,?,?,?,?)",
        'ssssss', array($razon, $rfc, $regimen, $tipo, $email, $tel));
    $id = (int)mysqli_insert_id($con);
    AccountingService::bootstrapEmpresa($con, $id);
}
log_proceso($con, $id, null, 'EMPRESA', 'OK', 'Empresa guardada: ' . $rfc);
sat_json(true, 'Empresa guardada.', array('id_empresa' => $id));
