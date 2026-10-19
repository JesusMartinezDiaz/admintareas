<?php
/**
 * Instala/actualiza las tablas del módulo SAT por línea de comandos:
 *
 *   php sat/cron/instalar.php
 *
 * (También disponible para administradores desde la pantalla Inicio.)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por línea de comandos.'); }
require_once dirname(__DIR__) . '/lib/bootstrap.php';
try {
    $con = sat_db();
    foreach (SatSchema::instalar($con) as $l) echo $l . "\n";
    echo "Listo.\n";
} catch (Exception $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
