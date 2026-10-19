<?php
/**
 * Arranque de los endpoints AJAX (php/*.php), igual que el resto del sistema:
 * dbConection.php (conexión $con) + control.php (sesión/acceso).
 */
require_once __DIR__ . '/../lib/bootstrap.php';
include_once rtrim(sat_config('ruta_assets'), '/') . '/dbConection.php';
include_once rtrim(sat_config('ruta_assets'), '/') . '/control.php';

set_exception_handler(function ($e) {
    sat_json(false, $e->getMessage());
});
set_time_limit(600);
$con = sat_db();
