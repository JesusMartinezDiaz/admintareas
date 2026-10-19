<?php
/** Crea/actualiza las tablas del módulo (botón en Inicio). */
require_once __DIR__ . '/../lib/bootstrap.php';
include_once rtrim(sat_config('ruta_assets'), '/') . '/dbConection.php';
include_once rtrim(sat_config('ruta_assets'), '/') . '/control.php';
set_exception_handler(function ($e) { sat_json(false, $e->getMessage()); });
sat_requiere_admin();
$con = sat_db();
$log = SatSchema::instalar($con);
sat_json(true, implode("\n", $log));
