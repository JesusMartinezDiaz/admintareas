<?php
/** accion: actualizar (siguiente paso) | verificar | descargar | procesar */
require_once __DIR__ . '/_init.php';

$id = (int)sat_post('id_solicitud', 0);
$accion = sat_post('accion', 'actualizar');
$s = sat_row($con, "SELECT * FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array($id));
if (!$s) throw new Exception('Solicitud no encontrada.');

try {
    switch ($accion) {
        case 'verificar':
            $r = SatDownloadService::verificar($con, $id);
            $msg = $r['estado'] . ': ' . $r['mensaje'];
            break;
        case 'descargar':
            $r = SatDownloadService::descargarPaquetes($con, $id);
            $msg = $r['mensaje'];
            break;
        case 'procesar':
            $r = SatDownloadService::procesar($con, $id);
            $msg = 'XML procesados: ' . $r['procesados'] . ($r['errores'] ? '. Errores: ' . $r['errores'] . ' (' . implode('; ', array_slice($r['mensajes'], 0, 3)) . ')' : '');
            break;
        default:
            $pasos = SatDownloadService::avanzar($con, $id, true);
            $msg = $pasos ? implode("\n", $pasos) : 'Sin cambios. Estado actual: ' . $s['estado_solicitud'];
    }
} catch (Exception $e) {
    log_proceso($con, $s['id_empresa'], $id, strtoupper($accion), 'ERROR', $e->getMessage());
    throw $e;
}
sat_json(true, $msg);
