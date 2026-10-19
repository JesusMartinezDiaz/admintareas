<?php
require_once __DIR__ . '/_init.php';
$id = (int)sat_get('id_solicitud', 0);
$s = sat_row($con, "SELECT s.*, e.rfc FROM sat_solicitudes_descarga s JOIN sat_empresas e ON e.id_empresa=s.id_empresa WHERE s.id_solicitud=?", 'i', array($id));
if (!$s) throw new Exception('Solicitud no encontrada.');
$paq = sat_rows($con, "SELECT id_paquete_sat, descargado, intentos, ultimo_error, fecha_descarga FROM sat_paquetes_descarga WHERE id_solicitud=? ORDER BY id_paquete", 'i', array($id));
$log = sat_rows($con, "SELECT fecha, proceso, estatus, mensaje, usuario FROM sat_log_procesos WHERE id_solicitud=? ORDER BY id_log DESC LIMIT 50", 'i', array($id));
$cfdi = sat_row($con, "SELECT COUNT(*) n, SUM(tipo_comprobante='I') i, SUM(tipo_comprobante='E') e, SUM(tipo_comprobante='P') p, SUM(tipo_comprobante='N') nn FROM sat_cfdi WHERE id_solicitud=?", 'i', array($id));
sat_json(true, '', array('solicitud' => $s, 'paquetes' => $paq, 'log' => $log, 'cfdi' => $cfdi));
