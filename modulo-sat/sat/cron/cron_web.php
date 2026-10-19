<?php
/**
 * Alternativa al cron por línea de comandos para hostings que solo permiten
 * llamadas por URL (p. ej. un servicio externo tipo cron-job.org):
 *
 *   https://tu-dominio/sat/cron/cron_web.php?token=EL_TOKEN_DE_sat_config.php
 *
 * Deshabilitado mientras 'cron_token' esté vacío.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
$token = (string)sat_config('cron_token', '');
$recibido = isset($_GET['token']) ? (string)$_GET['token'] : '';
if ($token === '' || !hash_equals($token, $recibido)) {
    http_response_code(403);
    exit('No autorizado.');
}
header('Content-Type: text/plain; charset=utf-8');
define('SAT_CRON_WEB', 1);
require __DIR__ . '/cron_sat.php';
