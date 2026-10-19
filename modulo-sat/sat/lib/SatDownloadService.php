<?php
/**
 * Flujo completo de una solicitud al SAT, compartido por las pantallas y el cron:
 *
 *   crearSolicitud -> verificar -> descargarPaquetes -> procesar
 *
 * avanzar() ejecuta el siguiente paso que corresponda según el estado.
 */
class SatDownloadService
{
    /** Estados en los que el SAT todavía está trabajando. */
    public static $estadosPendientes = array('SOLICITADA', 'SOLICITADA_REINTENTO', 'ACEPTADA', 'EN_PROCESO');

    /**
     * Crea una solicitud ante el SAT y la registra.
     * @param string $inicio 'Y-m-d' o 'Y-m-d H:i:s'
     * @param string $fin    'Y-m-d' o 'Y-m-d H:i:s'
     * @param array  $opts   tipo_comprobante, origen, id_programacion, auto_descargar, auto_procesar, desfase_segundos
     * @return array ['ok'=>bool, 'id_solicitud'=>int, 'mensaje'=>string]
     */
    public static function crearSolicitud($con, $idEmpresa, $tipoDescarga, $tipoArchivo, $inicio, $fin, $opts = array())
    {
        $tipoDescarga = strtoupper($tipoDescarga);
        $tipoArchivo = strtoupper($tipoArchivo);
        if (!in_array($tipoDescarga, array('EMITIDAS', 'RECIBIDAS'), true)) throw new Exception('Tipo de descarga inválido.');
        if (!in_array($tipoArchivo, array('CFDI', 'METADATA'), true)) throw new Exception('Tipo de archivo inválido.');

        list($empresa, $cert) = sat_empresa_y_certificado($con, $idEmpresa);
        list($dtIni, $dtFin) = self::normalizarRango($inicio, $fin, isset($opts['desfase_segundos']) ? (int)$opts['desfase_segundos'] : 0);

        $tipoComp = isset($opts['tipo_comprobante']) ? strtoupper(trim((string)$opts['tipo_comprobante'])) : '';
        $origen = !empty($opts['origen']) ? $opts['origen'] : 'MANUAL';
        $idProg = !empty($opts['id_programacion']) ? (int)$opts['id_programacion'] : null;
        $autoDesc = isset($opts['auto_descargar']) ? (int)(bool)$opts['auto_descargar'] : (int)(bool)sat_config('auto_descargar_manuales', true);
        $autoProc = isset($opts['auto_procesar']) ? (int)(bool)$opts['auto_procesar'] : $autoDesc;

        $res = array('ok' => false, 'request_id' => null, 'codigo_estado' => null, 'mensaje' => '');
        $errorComunicacion = false;
        try {
            $res = SatService::solicitarDescarga($empresa, $cert, $tipoDescarga, $tipoArchivo,
                $dtIni->format('Y-m-d\TH:i:s'), $dtFin->format('Y-m-d\TH:i:s'), array('tipo_comprobante' => $tipoComp));
        } catch (Exception $e) {
            // Falla antes de obtener respuesta del SAT (red, SAT caído, e.firma ilegible...).
            $res['mensaje'] = $e->getMessage();
            $errorComunicacion = true;
        }
        $cod = (string)$res['codigo_estado'];
        if (!empty($res['ok']) && ($cod === '5000' || $cod === '')) $estado = 'SOLICITADA';
        else $estado = $errorComunicacion ? 'ERROR' : 'RECHAZADA';
        if ($estado === 'RECHAZADA' && $res['mensaje'] === '') $res['mensaje'] = 'El SAT no aceptó la solicitud.';
        $mensaje = self::explicarCodigo((string)$res['codigo_estado'], $res['mensaje']);

        sat_exec($con, "INSERT INTO sat_solicitudes_descarga(id_empresa,id_programacion,origen,tipo_descarga,tipo_archivo,tipo_comprobante,fecha_inicial,fecha_final,
                fecha_hora_inicial,fecha_hora_final,request_id,estado_solicitud,codigo_estado,mensaje_sat,auto_descargar,auto_procesar,usuario_alta)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            'iissssssssssssiis', array((int)$idEmpresa, $idProg, $origen, $tipoDescarga, $tipoArchivo, $tipoComp !== '' ? $tipoComp : null,
                $dtIni->format('Y-m-d'), $dtFin->format('Y-m-d'), $dtIni->format('Y-m-d H:i:s'), $dtFin->format('Y-m-d H:i:s'),
                $res['request_id'] ?: null, $estado, $res['codigo_estado'], mb_substr($mensaje, 0, 500, 'UTF-8'), $autoDesc, $autoProc, sat_usuario()));
        $idSol = (int)mysqli_insert_id($con);
        log_proceso($con, $idEmpresa, $idSol, 'SOLICITAR_DESCARGA', $estado === 'SOLICITADA' ? 'OK' : 'ERROR',
            $tipoDescarga . ' ' . $tipoArchivo . ' ' . $dtIni->format('Y-m-d H:i:s') . ' a ' . $dtFin->format('Y-m-d H:i:s') . ' | ' . $mensaje);
        return array('ok' => $estado === 'SOLICITADA', 'id_solicitud' => $idSol, 'mensaje' => $mensaje, 'request_id' => $res['request_id'],
            'error_comunicacion' => $errorComunicacion);
    }

    /**
     * Rango válido para el SAT: inicio < fin y fin no mayor que ahora.
     * $desfase mueve el inicio unos segundos hacia atrás para que dos peticiones
     * del mismo periodo no tengan parámetros idénticos (el SAT limita las
     * solicitudes repetidas). Solo agrega traslape, nunca deja huecos.
     */
    public static function normalizarRango($inicio, $fin, $desfase = 0)
    {
        $dtIni = new DateTime(strlen($inicio) <= 10 ? $inicio . ' 00:00:00' : $inicio);
        $dtFin = new DateTime(strlen($fin) <= 10 ? $fin . ' 23:59:59' : $fin);
        $limite = new DateTime('-2 minutes');
        if ($dtFin > $limite) $dtFin = $limite;
        if ($desfase > 0) $dtIni->modify('-' . (int)$desfase . ' seconds');
        if ($dtIni >= $dtFin) throw new Exception('La fecha inicial debe ser menor que la final (y la final no puede ser futura).');
        return array($dtIni, $dtFin);
    }

    /* ------------------------------------------------------------------ */

    public static function verificar($con, $idSolicitud)
    {
        $s = sat_row($con, "SELECT * FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array((int)$idSolicitud));
        if (!$s) throw new Exception('Solicitud no encontrada.');
        if (!$s['request_id']) throw new Exception('La solicitud no tiene Request ID del SAT.');
        $idSol = (int)$s['id_solicitud'];
        $idEmpresa = (int)$s['id_empresa'];
        list($empresa, $cert) = sat_empresa_y_certificado($con, $idEmpresa);

        $res = SatService::verificarSolicitud($empresa, $cert, $s['request_id']);
        $estado = $res['estado_solicitud'];
        $cod = (string)$res['codigo_estado'];
        $msg = self::explicarCodigo($cod, $res['mensaje']);
        $total = (int)$res['total_cfdi'];

        // SAT devuelve EstadoSolicitud=0 con 404/5006 como error interno temporal
        // (frecuente en RECIBIDAS + CFDI). Se crea una nueva petición con el
        // inicio desplazado unos segundos para que no sea idéntica.
        if ((string)$res['estado_numero'] === '0' && in_array($cod, array('404', '5006'), true)) {
            if ((int)$s['reintentos'] < (int)sat_config('max_reintentos_recibidas', 2)) {
                $n = (int)$s['reintentos'] + 1;
                $ini = $s['fecha_hora_inicial'] ?: ($s['fecha_inicial'] . ' 00:00:00');
                $fin = $s['fecha_hora_final'] ?: ($s['fecha_final'] . ' 23:59:59');
                list($dtIni, $dtFin) = self::normalizarRango($ini, $fin, $n);
                $nuevo = SatService::solicitarDescarga($empresa, $cert, $s['tipo_descarga'], $s['tipo_archivo'],
                    $dtIni->format('Y-m-d\TH:i:s'), $dtFin->format('Y-m-d\TH:i:s'), array('tipo_comprobante' => $s['tipo_comprobante']));
                if (!empty($nuevo['ok']) && !empty($nuevo['request_id'])) {
                    $m = 'El SAT devolvió error interno ' . $cod . '. Reintento automático #' . $n . ' (Request ID ' . $nuevo['request_id'] . ').';
                    sat_exec($con, "UPDATE sat_solicitudes_descarga SET request_id=?, estado_solicitud='SOLICITADA_REINTENTO', codigo_estado=?, mensaje_sat=?,
                            reintentos=reintentos+1, total_cfdi=0, fecha_hora_inicial=?, verificaciones=verificaciones+1, fecha_ultima_verificacion=NOW() WHERE id_solicitud=?",
                        'ssssi', array($nuevo['request_id'], $nuevo['codigo_estado'], $m, $dtIni->format('Y-m-d H:i:s'), $idSol));
                    log_proceso($con, $idEmpresa, $idSol, 'REINTENTO_SOLICITUD', 'OK', $m);
                    return array('estado' => 'SOLICITADA_REINTENTO', 'mensaje' => $m, 'paquetes' => 0);
                }
            }
            $estado = 'ERROR_TEMPORAL_SAT';
            $msg = 'El SAT devolvió el error interno ' . $cod . ' y se agotaron los reintentos. Solicita de nuevo más tarde.';
        }

        if ($estado === 'TERMINADA' && empty($res['paquetes']) && ($total === 0 || $cod === '5004')) {
            $estado = 'SIN_INFORMACION';
            $msg = 'El SAT terminó la solicitud: no hay CFDI en el periodo.';
        }
        if ($cod === '5003') {
            // Tope máximo de CFDI por solicitud: se divide el periodo en dos.
            $estado = 'DIVIDIDA';
            $msg = self::dividir($con, $s) ?: $msg;
        }

        sat_exec($con, "UPDATE sat_solicitudes_descarga SET estado_solicitud=?, codigo_estado=?, mensaje_sat=?, total_cfdi=?, verificaciones=verificaciones+1,
                fecha_ultima_verificacion=NOW(), procesada=IF(?='SIN_INFORMACION',1,procesada) WHERE id_solicitud=?",
            'sssisi', array($estado, $cod, mb_substr($msg, 0, 500, 'UTF-8'), $total, $estado, $idSol));
        foreach ($res['paquetes'] as $paq) {
            sat_exec($con, "INSERT IGNORE INTO sat_paquetes_descarga(id_solicitud,id_paquete_sat) VALUES (?,?)", 'is', array($idSol, $paq));
        }
        log_proceso($con, $idEmpresa, $idSol, 'VERIFICAR_SOLICITUD', in_array($estado, array('ERROR', 'RECHAZADA', 'ERROR_TEMPORAL_SAT', 'VENCIDA'), true) ? 'ERROR' : 'OK',
            $estado . ': ' . $msg . ' | CFDI: ' . $total . ' | Paquetes: ' . count($res['paquetes']));
        return array('estado' => $estado, 'mensaje' => $msg, 'paquetes' => count($res['paquetes']), 'total_cfdi' => $total);
    }

    private static function dividir($con, $s)
    {
        $ini = new DateTime($s['fecha_hora_inicial'] ?: $s['fecha_inicial'] . ' 00:00:00');
        $fin = new DateTime($s['fecha_hora_final'] ?: $s['fecha_final'] . ' 23:59:59');
        $seg = $fin->getTimestamp() - $ini->getTimestamp();
        if ($seg < 3600) return null;
        $mitad = (new DateTime())->setTimestamp($ini->getTimestamp() + (int)floor($seg / 2));
        $opts = array('tipo_comprobante' => $s['tipo_comprobante'], 'origen' => $s['origen'], 'id_programacion' => $s['id_programacion'],
            'auto_descargar' => $s['auto_descargar'], 'auto_procesar' => $s['auto_procesar']);
        $a = self::crearSolicitud($con, $s['id_empresa'], $s['tipo_descarga'], $s['tipo_archivo'], $ini->format('Y-m-d H:i:s'), $mitad->format('Y-m-d H:i:s'), $opts);
        $mitad->modify('+1 second');
        $b = self::crearSolicitud($con, $s['id_empresa'], $s['tipo_descarga'], $s['tipo_archivo'], $mitad->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), $opts);
        return 'Excede el tope de CFDI por solicitud; se dividió en las solicitudes #' . $a['id_solicitud'] . ' y #' . $b['id_solicitud'] . '.';
    }

    /* ------------------------------------------------------------------ */

    public static function descargarPaquetes($con, $idSolicitud)
    {
        $s = sat_row($con, "SELECT * FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array((int)$idSolicitud));
        if (!$s) throw new Exception('Solicitud no encontrada.');
        $idSol = (int)$s['id_solicitud'];
        $idEmpresa = (int)$s['id_empresa'];
        list($empresa, $cert) = sat_empresa_y_certificado($con, $idEmpresa);
        if (!class_exists('ZipArchive')) throw new Exception('La extensión ZIP no está habilitada en PHP.');

        $dirZip = sat_path_datos('paquetes/solicitud_' . $idSol);
        $dirXml = CfdiProcessor::dirSolicitud($idSol);
        $paquetes = sat_rows($con, "SELECT * FROM sat_paquetes_descarga WHERE id_solicitud=? AND descargado=0 ORDER BY id_paquete", 'i', array($idSol));
        $ok = 0; $errores = array(); $archivos = 0;
        foreach ($paquetes as $p) {
            try {
                $res = SatService::descargarPaquete($empresa, $cert, $p['id_paquete_sat']);
                $zipPath = $dirZip . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $p['id_paquete_sat']) . '.zip';
                if (file_put_contents($zipPath, $res['zip_binary']) === false) throw new Exception('No se pudo guardar el ZIP.');
                $archivos += self::extraerZip($zipPath, $dirXml);
                sat_exec($con, "UPDATE sat_paquetes_descarga SET descargado=1, intentos=intentos+1, ultimo_error=NULL, archivo_zip=?, fecha_descarga=NOW() WHERE id_paquete=?",
                    'si', array(sat_path_relativa($zipPath), (int)$p['id_paquete']));
                $ok++;
            } catch (Exception $e) {
                $errores[] = $p['id_paquete_sat'] . ': ' . $e->getMessage();
                sat_exec($con, "UPDATE sat_paquetes_descarga SET intentos=intentos+1, ultimo_error=? WHERE id_paquete=?",
                    'si', array(mb_substr($e->getMessage(), 0, 500, 'UTF-8'), (int)$p['id_paquete']));
            }
        }
        if ($ok > 0) sat_exec($con, "UPDATE sat_solicitudes_descarga SET procesada=0 WHERE id_solicitud=?", 'i', array($idSol));
        $msg = 'Paquetes descargados: ' . $ok . ' de ' . count($paquetes) . '. Archivos extraídos: ' . $archivos . ($errores ? '. Errores: ' . implode(' | ', $errores) : '');
        log_proceso($con, $idEmpresa, $idSol, 'DESCARGAR_PAQUETES', $errores ? 'ERROR' : 'OK', $msg);
        return array('descargados' => $ok, 'errores' => count($errores), 'mensaje' => $msg);
    }

    /** Extrae solo .xml/.txt y sin rutas (evita "zip slip"). */
    private static function extraerZip($zipPath, $dirDestino)
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new Exception('No se pudo abrir el ZIP descargado.');
        $n = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $base = basename(str_replace('\\', '/', $name));
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if ($base === '' || !in_array($ext, array('xml', 'txt'), true)) continue;
            $data = $zip->getFromIndex($i);
            if ($data === false) continue;
            file_put_contents($dirDestino . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $base), $data);
            $n++;
        }
        $zip->close();
        return $n;
    }

    public static function procesar($con, $idSolicitud)
    {
        return CfdiProcessor::procesarSolicitud($con, $idSolicitud);
    }

    /**
     * Ejecuta el siguiente paso pendiente de la solicitud.
     * $forzar = true ignora los flags auto_descargar/auto_procesar (botón "Actualizar").
     */
    public static function avanzar($con, $idSolicitud, $forzar = false)
    {
        $pasos = array();
        $s = sat_row($con, "SELECT * FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array((int)$idSolicitud));
        if (!$s) throw new Exception('Solicitud no encontrada.');

        if (in_array($s['estado_solicitud'], self::$estadosPendientes, true) && $s['request_id']) {
            $v = self::verificar($con, $s['id_solicitud']);
            $pasos[] = 'Verificación: ' . $v['estado'] . ' - ' . $v['mensaje'];
            $s = sat_row($con, "SELECT * FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array((int)$idSolicitud));
        }
        if ($s['estado_solicitud'] !== 'TERMINADA') return $pasos;

        $pend = sat_row($con, "SELECT COUNT(*) n FROM sat_paquetes_descarga WHERE id_solicitud=? AND descargado=0 AND intentos<5", 'i', array((int)$idSolicitud));
        if ((int)$pend['n'] > 0 && ($forzar || (int)$s['auto_descargar'])) {
            $d = self::descargarPaquetes($con, $s['id_solicitud']);
            $pasos[] = $d['mensaje'];
            $s = sat_row($con, "SELECT * FROM sat_solicitudes_descarga WHERE id_solicitud=?", 'i', array((int)$idSolicitud));
        }
        $desc = sat_row($con, "SELECT COUNT(*) n FROM sat_paquetes_descarga WHERE id_solicitud=? AND descargado=1", 'i', array((int)$idSolicitud));
        if ((int)$desc['n'] > 0 && !(int)$s['procesada'] && ($forzar || (int)$s['auto_procesar'])) {
            $p = self::procesar($con, $s['id_solicitud']);
            $pasos[] = 'Procesados: ' . $p['procesados'] . ($p['errores'] ? ' (errores: ' . $p['errores'] . ')' : '');
        }
        return $pasos;
    }

    /** Traduce los códigos más comunes del SAT a un mensaje entendible. */
    public static function explicarCodigo($cod, $mensajeSat)
    {
        $map = array(
            '5002' => 'Se agotaron las solicitudes permitidas con estos mismos parámetros. Cambia el periodo.',
            '5003' => 'La solicitud excede el tope máximo de CFDI; se debe dividir el periodo.',
            '5004' => 'No se encontró información para los parámetros solicitados.',
            '5005' => 'Ya existe una solicitud vigente con los mismos parámetros.',
            '5011' => 'Se alcanzó el límite de descargas por folio por día.',
            '300' => 'Usuario no válido.',
            '301' => 'XML mal formado o parámetros incorrectos.',
            '302' => 'Sello mal formado.',
            '303' => 'El sello no corresponde con el RFC solicitante.',
            '304' => 'Certificado revocado o caduco.',
            '305' => 'Certificado inválido.',
        );
        $mensajeSat = trim((string)$mensajeSat);
        if (isset($map[$cod]) && stripos($mensajeSat, $map[$cod]) === false) {
            return $map[$cod] . ($mensajeSat !== '' ? ' (SAT ' . $cod . ': ' . $mensajeSat . ')' : '');
        }
        return $mensajeSat;
    }
}
