<?php
/**
 * Carga la e.firma (.cer + .key + contraseña). Antes de guardarla valida que:
 * el .cer sea un certificado, la contraseña abra el .key, ambos correspondan,
 * el RFC coincida con la empresa y esté vigente.
 */
require_once __DIR__ . '/_init.php';
sat_requiere_admin();

$id = (int)sat_post('id_empresa', 0);
$emp = sat_row($con, "SELECT * FROM sat_empresas WHERE id_empresa=?", 'i', array($id));
if (!$emp) throw new Exception('Empresa inválida.');
$pass = isset($_POST['password_key']) ? (string)$_POST['password_key'] : '';
if ($pass === '') throw new Exception('Escribe la contraseña de la llave privada.');
foreach (array('archivo_cer' => 'cer', 'archivo_key' => 'key') as $campo => $ext) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) throw new Exception('Falta el archivo .' . $ext);
    if (strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION)) !== $ext) throw new Exception('El archivo ' . $_FILES[$campo]['name'] . ' no es .' . $ext);
    if ($_FILES[$campo]['size'] > 20000) throw new Exception('El archivo .' . $ext . ' es demasiado grande para ser una e.firma.');
}

$info = SatService::leerCertificado($_FILES['archivo_cer']['tmp_name']);
SatService::validarLlave($_FILES['archivo_key']['tmp_name'], $pass);
$cerPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode(file_get_contents($_FILES['archivo_cer']['tmp_name'])), 64, "\n") . "-----END CERTIFICATE-----\n";
$keyDer = file_get_contents($_FILES['archivo_key']['tmp_name']);
$keyPem = "-----BEGIN ENCRYPTED PRIVATE KEY-----\n" . chunk_split(base64_encode($keyDer), 64, "\n") . "-----END ENCRYPTED PRIVATE KEY-----\n";
$pk = @openssl_pkey_get_private($keyPem, $pass);
if (!$pk) $pk = @openssl_pkey_get_private($keyDer, $pass);
if ($pk && !openssl_x509_check_private_key($cerPem, $pk)) throw new Exception('El .key no corresponde al .cer.');
if ($info['rfc'] !== '' && $info['rfc'] !== strtoupper($emp['rfc'])) throw new Exception('El certificado es del RFC ' . $info['rfc'] . ', no de ' . $emp['rfc'] . '.');
if ($info['valid_to'] && strtotime($info['valid_to']) < time()) throw new Exception('El certificado venció el ' . $info['valid_to'] . '.');

$dir = sat_path_datos('certificados/empresa_' . $id);
if (!is_file(sat_path_datos('certificados') . '/.htaccess')) @file_put_contents(sat_path_datos('certificados') . '/.htaccess', "Require all denied\nDeny from all\n");
$base = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$cerDest = $dir . '/' . $base . '.cer';
$keyDest = $dir . '/' . $base . '.key';
if (!move_uploaded_file($_FILES['archivo_cer']['tmp_name'], $cerDest) || !move_uploaded_file($_FILES['archivo_key']['tmp_name'], $keyDest)) {
    throw new Exception('No se pudieron guardar los archivos. Revisa permisos de ' . $dir);
}
@chmod($cerDest, 0600);
@chmod($keyDest, 0600);

mysqli_begin_transaction($con);
try {
    sat_exec($con, "UPDATE sat_certificados SET activo=0 WHERE id_empresa=?", 'i', array($id));
    sat_exec($con, "INSERT INTO sat_certificados(id_empresa,archivo_cer,archivo_key,password_key,no_certificado,rfc_certificado,fecha_inicio,fecha_vencimiento,activo,usuario_alta) VALUES (?,?,?,?,?,?,?,?,1,?)",
        'issssssss', array($id, sat_path_relativa($cerDest), sat_path_relativa($keyDest), encrypt_password_key($pass), substr($info['no_certificado'], 0, 40), $info['rfc'], $info['valid_from'], $info['valid_to'], sat_usuario()));
    mysqli_commit($con);
} catch (Exception $e) {
    mysqli_rollback($con);
    @unlink($cerDest);
    @unlink($keyDest);
    throw $e;
}
log_proceso($con, $id, null, 'CERTIFICADO', 'OK', 'e.firma cargada. No. ' . $info['no_certificado'] . ' vence ' . $info['valid_to']);

$aviso = $info['posible_csd'] ? ' Aviso: el certificado parece un CSD (sello digital). La descarga masiva requiere la e.firma (FIEL); usa "Probar conexión".' : '';
sat_json(true, 'e.firma guardada. Vence el ' . $info['valid_to'] . '.' . $aviso);
