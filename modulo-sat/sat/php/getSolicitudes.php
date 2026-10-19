<?php
require_once __DIR__ . '/_init.php';

$where = ' WHERE 1=1 ';
$types = '';
$params = array();
if ((int)sat_get('id_empresa', 0) > 0) { $where .= ' AND s.id_empresa=? '; $types .= 'i'; $params[] = (int)sat_get('id_empresa'); }
if (sat_get('estado') === 'PENDIENTES') $where .= " AND s.estado_solicitud IN ('" . implode("','", SatDownloadService::$estadosPendientes) . "') ";
elseif (sat_get('estado') === 'TERMINADAS') $where .= " AND s.estado_solicitud IN ('TERMINADA','SIN_INFORMACION') ";
elseif (sat_get('estado') === 'ERRORES') $where .= " AND s.estado_solicitud IN ('ERROR','ERROR_SAT','ERROR_TEMPORAL_SAT','RECHAZADA','VENCIDA') ";
if (in_array(sat_get('origen'), array('MANUAL', 'PROGRAMADA'), true)) { $where .= ' AND s.origen=? '; $types .= 's'; $params[] = sat_get('origen'); }
if ((int)sat_get('id_programacion', 0) > 0) { $where .= ' AND s.id_programacion=? '; $types .= 'i'; $params[] = (int)sat_get('id_programacion'); }

$rows = sat_rows($con, "SELECT s.*, e.rfc, e.razon_social, p.nombre programacion,
        (SELECT COUNT(*) FROM sat_paquetes_descarga x WHERE x.id_solicitud=s.id_solicitud) total_paquetes,
        (SELECT COUNT(*) FROM sat_paquetes_descarga x WHERE x.id_solicitud=s.id_solicitud AND x.descargado=1) paquetes_descargados
    FROM sat_solicitudes_descarga s
    JOIN sat_empresas e ON e.id_empresa=s.id_empresa
    LEFT JOIN sat_programaciones p ON p.id_programacion=s.id_programacion
    $where ORDER BY s.id_solicitud DESC LIMIT 200", $types, $params);
sat_json(true, '', array('solicitudes' => $rows, 'pendientes' => SatDownloadService::$estadosPendientes));
