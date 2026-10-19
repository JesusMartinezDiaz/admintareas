<?php
/**
 * Arranque común del módulo SAT (páginas, endpoints php/ y cron).
 * Compatible con PHP 7.1+.
 */
if (defined('SAT_BOOTSTRAP')) return;
define('SAT_BOOTSTRAP', 1);
define('SAT_ROOT', dirname(__DIR__));

require_once __DIR__ . '/SatService.php';
require_once __DIR__ . '/AccountingService.php';
require_once __DIR__ . '/CfdiProcessor.php';
require_once __DIR__ . '/SatDownloadService.php';
require_once __DIR__ . '/SchedulerService.php';
require_once __DIR__ . '/TaxReportService.php';
require_once __DIR__ . '/SatSchema.php';

function sat_config($key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = SAT_ROOT . '/config/sat_config.php';
        if (!is_file($file)) $file = SAT_ROOT . '/config/sat_config.example.php';
        $cfg = require $file;
        if (!is_array($cfg)) $cfg = array();
    }
    if ($key === null) return $cfg;
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

date_default_timezone_set(sat_config('zona_horaria', 'America/Mexico_City'));

/**
 * Conexión a BD. Usa $con del sistema si existe (head.php / dbConection.php
 * ya lo crearon) o la conexión propia definida en sat_config.php.
 */
function sat_db()
{
    static $db = null;
    if ($db instanceof mysqli) return $db;
    $cfg = sat_config('db');
    if (is_array($cfg)) {
        $db = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], isset($cfg['port']) ? (int)$cfg['port'] : 3306);
        if (!$db) throw new Exception('No se pudo conectar a la BD del módulo SAT: ' . mysqli_connect_error());
    } else {
        global $con;
        if (!($con instanceof mysqli)) {
            $f = rtrim(sat_config('ruta_assets'), '/') . '/dbConection.php';
            if (!is_file($f)) throw new Exception('No se encontró ' . $f . '. Configura ruta_assets o db en config/sat_config.php');
            include_once $f;
        }
        if (!($con instanceof mysqli)) throw new Exception('dbConection.php no definió la conexión $con.');
        $db = $con;
    }
    mysqli_set_charset($db, sat_config('charset', 'utf8mb4'));
    // Las fechas del SAT se manejan en hora local de México.
    @mysqli_query($db, "SET time_zone='" . date('P') . "'");
    return $db;
}

/* --------------------------------------------------------------------
   Rutas de datos
   -------------------------------------------------------------------- */
function sat_path_datos($sub = '')
{
    $base = rtrim(sat_config('dir_datos', SAT_ROOT . '/uploads'), '/');
    $dir = $sub === '' ? $base : $base . '/' . trim($sub, '/');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function sat_path_logs()
{
    $dir = SAT_ROOT . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

/** Guarda rutas relativas a la raíz del módulo cuando es posible (portabilidad). */
function sat_path_relativa($abs)
{
    $root = SAT_ROOT . '/';
    return strpos($abs, $root) === 0 ? substr($abs, strlen($root)) : $abs;
}

function sat_path_absoluta($path)
{
    if ($path === null || $path === '') return '';
    if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) return $path;
    return SAT_ROOT . '/' . ltrim($path, '/');
}

/* --------------------------------------------------------------------
   Utilidades
   -------------------------------------------------------------------- */
if (!function_exists('e')) {
    function e($txt) { return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8'); }
}

function sat_json($ok, $msg = '', $extra = array())
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(array('ok' => (bool)$ok, 'msg' => $msg), $extra), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function sat_usuario()
{
    global $usuario_acceso;
    if (!empty($usuario_acceso)) return (string)$usuario_acceso;
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach (array('usuario', 'cve_usuario') as $k) {
            if (!empty($_SESSION[$k])) return (string)$_SESSION[$k];
        }
    }
    return PHP_SAPI === 'cli' ? 'cron' : '';
}

function sat_es_admin()
{
    $admins = sat_config('usuarios_admin', array());
    if (empty($admins)) return true;
    return in_array(sat_usuario(), $admins, true);
}

function sat_requiere_admin()
{
    if (!sat_es_admin()) sat_json(false, 'No tienes permiso para esta acción.');
}

function sat_post($k, $def = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $def; }
function sat_get($k, $def = '') { return isset($_GET[$k]) ? (is_string($_GET[$k]) ? trim($_GET[$k]) : $_GET[$k]) : $def; }

function sat_fecha_valida($f)
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$f);
    return $d && $d->format('Y-m-d') === $f;
}

/** Ejecuta una consulta preparada. $types estilo mysqli ('iss...'). */
function sat_exec($con, $sql, $types = '', $params = array())
{
    $st = mysqli_prepare($con, $sql);
    if (!$st) throw new Exception('Error SQL: ' . mysqli_error($con));
    if ($types !== '') {
        $refs = array($st, $types);
        foreach ($params as $i => $v) { $refs[] = &$params[$i]; }
        call_user_func_array('mysqli_stmt_bind_param', $refs);
    }
    if (!mysqli_stmt_execute($st)) {
        $err = mysqli_stmt_error($st);
        mysqli_stmt_close($st);
        throw new Exception('Error SQL: ' . $err);
    }
    return $st;
}

/** Devuelve todas las filas de una consulta preparada. */
function sat_rows($con, $sql, $types = '', $params = array())
{
    if (!function_exists('mysqli_stmt_get_result')) {
        // Hosting sin mysqlnd: se interpolan los parámetros ya escapados.
        $rs = mysqli_query($con, sat_sql_interpolar($con, $sql, $types, $params));
        if (!$rs) throw new Exception('Error SQL: ' . mysqli_error($con));
        $rows = array();
        while ($r = mysqli_fetch_assoc($rs)) $rows[] = $r;
        return $rows;
    }
    $st = sat_exec($con, $sql, $types, $params);
    $res = mysqli_stmt_get_result($st);
    $rows = array();
    if ($res) { while ($r = mysqli_fetch_assoc($res)) $rows[] = $r; }
    mysqli_stmt_close($st);
    return $rows;
}

function sat_sql_interpolar($con, $sql, $types, $params)
{
    $i = 0;
    return preg_replace_callback('/\?/', function () use ($con, $types, $params, &$i) {
        $v = $params[$i];
        $t = $types[$i];
        $i++;
        if ($v === null) return 'NULL';
        if ($t === 'i') return (string)(int)$v;
        if ($t === 'd') return (string)(float)$v;
        return "'" . mysqli_real_escape_string($con, (string)$v) . "'";
    }, $sql);
}

function sat_row($con, $sql, $types = '', $params = array())
{
    $rows = sat_rows($con, $sql, $types, $params);
    return $rows ? $rows[0] : null;
}

/* --------------------------------------------------------------------
   e.firma: cifrado de contraseña
   -------------------------------------------------------------------- */
define('SAT_LLAVE_LEGADA', 'CAMBIA_ESTA_LLAVE_SECRETA_32_CHARS');

function sat_secret()
{
    $s = (string)sat_config('secret_key', '');
    return $s !== '' ? $s : SAT_LLAVE_LEGADA;
}

function encrypt_password_key($plain)
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', hash('sha256', sat_secret(), true), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decrypt_password_key($encoded)
{
    $raw = base64_decode($encoded);
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', sat_secret(), true), OPENSSL_RAW_DATA, $iv);
}

/* --------------------------------------------------------------------
   Bitácora
   -------------------------------------------------------------------- */
function log_proceso($con, $idEmpresa, $idSolicitud, $proceso, $estatus, $mensaje)
{
    $idEmpresa = $idEmpresa ? (int)$idEmpresa : null;
    $idSolicitud = $idSolicitud ? (int)$idSolicitud : null;
    $usuario = sat_usuario();
    $mensaje = mb_substr((string)$mensaje, 0, 4000, 'UTF-8');
    try {
        sat_exec($con, "INSERT INTO sat_log_procesos(id_empresa,id_solicitud,proceso,estatus,mensaje,usuario) VALUES (?,?,?,?,?,?)",
            'iissss', array($idEmpresa, $idSolicitud, $proceso, $estatus, $mensaje, $usuario));
    } catch (Exception $e) {
        // La bitácora nunca debe interrumpir el proceso principal.
    }
}

/* --------------------------------------------------------------------
   Catálogos SAT usados en pantallas
   -------------------------------------------------------------------- */
function sat_catalogo_tipo_comprobante()
{
    return array('I' => 'Ingreso', 'E' => 'Egreso (nota de crédito)', 'P' => 'Pago (complemento)', 'N' => 'Nómina', 'T' => 'Traslado');
}

function sat_empresas_activas($con)
{
    return sat_rows($con, "SELECT id_empresa, rfc, razon_social FROM sat_empresas WHERE activo=1 ORDER BY razon_social");
}

function sat_empresa_y_certificado($con, $idEmpresa)
{
    $empresa = sat_row($con, "SELECT * FROM sat_empresas WHERE id_empresa=?", 'i', array((int)$idEmpresa));
    if (!$empresa) throw new Exception('Empresa no encontrada.');
    $cert = sat_row($con, "SELECT * FROM sat_certificados WHERE id_empresa=? AND activo=1 ORDER BY id_certificado DESC LIMIT 1", 'i', array((int)$idEmpresa));
    if (!$cert) throw new Exception('La empresa ' . $empresa['rfc'] . ' no tiene una e.firma activa.');
    if (!empty($cert['fecha_vencimiento']) && strtotime($cert['fecha_vencimiento']) < time()) {
        throw new Exception('La e.firma de ' . $empresa['rfc'] . ' venció el ' . $cert['fecha_vencimiento'] . '.');
    }
    return array($empresa, $cert);
}
