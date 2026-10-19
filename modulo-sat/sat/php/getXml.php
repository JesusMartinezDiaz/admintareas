<?php
/** Descarga el XML original de un CFDI. */
require_once __DIR__ . '/../lib/bootstrap.php';
include_once rtrim(sat_config('ruta_assets'), '/') . '/dbConection.php';
include_once rtrim(sat_config('ruta_assets'), '/') . '/control.php';
$con = sat_db();
$c = sat_row($con, "SELECT uuid, archivo_xml FROM sat_cfdi WHERE id_cfdi=?", 'i', array((int)sat_get('id_cfdi', 0)));
$path = $c ? sat_path_absoluta($c['archivo_xml']) : '';
$base = realpath(sat_path_datos());
$real = $path ? realpath($path) : false;
if (!$c || !$real || strpos($real, $base) !== 0 || !is_file($real)) { http_response_code(404); exit('XML no disponible.'); }
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: ' . (sat_get('ver') === '1' ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9-]/', '', $c['uuid']) . '.xml"');
readfile($real);
