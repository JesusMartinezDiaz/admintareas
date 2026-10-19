<?php
/**
 * Programaciones: peticiones automáticas de emitidos/recibidos al SAT.
 *
 * Cada programación define CUÁNDO se ejecuta (frecuencia) y QUÉ periodo se le
 * pide al SAT. El cron (cron/cron_sat.php) llama a ejecutarPendientes() y
 * luego el flujo normal (verificar -> descargar -> procesar) se encarga del resto.
 */
class SchedulerService
{
    public static function frecuencias()
    {
        return array('CADA_HORAS' => 'Cada N horas', 'DIARIA' => 'Diaria', 'SEMANAL' => 'Semanal (días elegidos)', 'MENSUAL' => 'Mensual');
    }

    public static function periodos()
    {
        return array(
            'DIAS_ATRAS' => 'Últimos N días hasta hoy',
            'AYER' => 'Día anterior completo',
            'MES_ACTUAL' => 'Mes en curso',
            'MES_ANTERIOR' => 'Mes anterior completo',
            'MES_ACTUAL_Y_ANTERIOR' => 'Mes anterior + mes en curso',
        );
    }

    /** Valida y normaliza los datos de una programación (del formulario). */
    public static function normalizar($d)
    {
        $p = array();
        $p['id_empresa'] = (int)(isset($d['id_empresa']) ? $d['id_empresa'] : 0);
        if ($p['id_empresa'] <= 0) throw new Exception('Selecciona la empresa.');
        $p['nombre'] = trim(isset($d['nombre']) ? $d['nombre'] : '');
        if ($p['nombre'] === '') throw new Exception('Escribe un nombre para la programación.');
        $p['nombre'] = mb_substr($p['nombre'], 0, 120, 'UTF-8');

        $p['tipo_descarga'] = self::enumVal($d, 'tipo_descarga', array('EMITIDAS', 'RECIBIDAS', 'AMBAS'), 'AMBAS');
        $p['tipo_archivo'] = self::enumVal($d, 'tipo_archivo', array('CFDI', 'METADATA'), 'CFDI');
        $tc = strtoupper(trim(isset($d['tipo_comprobante']) ? $d['tipo_comprobante'] : ''));
        $p['tipo_comprobante'] = in_array($tc, array('I', 'E', 'P', 'N', 'T'), true) ? $tc : null;
        $p['frecuencia'] = self::enumVal($d, 'frecuencia', array_keys(self::frecuencias()), 'DIARIA');
        $p['intervalo_horas'] = max(1, min(168, (int)(isset($d['intervalo_horas']) ? $d['intervalo_horas'] : 6)));
        $hora = isset($d['hora_ejecucion']) ? trim($d['hora_ejecucion']) : '06:00';
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora)) throw new Exception('Hora de ejecución inválida.');
        $p['hora_ejecucion'] = substr($hora . ':00', 0, 8);
        $dias = isset($d['dias_semana']) ? $d['dias_semana'] : array();
        if (!is_array($dias)) $dias = explode(',', (string)$dias);
        $dias = array_values(array_unique(array_filter(array_map('intval', $dias), function ($x) { return $x >= 1 && $x <= 7; })));
        sort($dias);
        if ($p['frecuencia'] === 'SEMANAL' && !$dias) throw new Exception('Elige al menos un día de la semana.');
        $p['dias_semana'] = $dias ? implode(',', $dias) : null;
        $dm = (int)(isset($d['dia_mes']) ? $d['dia_mes'] : 1);
        $p['dia_mes'] = ($dm === 31) ? 31 : max(1, min(28, $dm));
        $p['periodo'] = self::enumVal($d, 'periodo', array_keys(self::periodos()), 'DIAS_ATRAS');
        $p['dias_atras'] = max(0, min(365, (int)(isset($d['dias_atras']) ? $d['dias_atras'] : 3)));
        $p['auto_descargar'] = !empty($d['auto_descargar']) ? 1 : 0;
        $p['auto_procesar'] = !empty($d['auto_procesar']) ? 1 : 0;
        $p['activo'] = !empty($d['activo']) ? 1 : 0;
        if ($p['tipo_archivo'] === 'METADATA' && $p['tipo_descarga'] !== 'EMITIDAS' && $p['tipo_comprobante'] !== null) {
            // Sin restricción especial; se deja la validación al SAT.
        }
        return $p;
    }

    private static function enumVal($d, $k, $vals, $def)
    {
        $v = strtoupper(trim(isset($d[$k]) ? (string)$d[$k] : ''));
        return in_array($v, $vals, true) ? $v : $def;
    }

    /**
     * Periodo a solicitar al SAT para una ejecución en $ahora.
     * @return DateTime[] [inicio, fin]
     */
    public static function calcularRango($p, DateTime $ahora = null)
    {
        $ahora = $ahora ? clone $ahora : new DateTime();
        $hoy = new DateTime($ahora->format('Y-m-d') . ' 00:00:00');
        switch ($p['periodo']) {
            case 'AYER':
                $ini = (clone $hoy)->modify('-1 day');
                $fin = (clone $hoy)->modify('-1 second');
                break;
            case 'MES_ACTUAL':
                $ini = new DateTime($ahora->format('Y-m-01') . ' 00:00:00');
                $fin = clone $ahora;
                break;
            case 'MES_ANTERIOR':
                $ini = (new DateTime($ahora->format('Y-m-01') . ' 00:00:00'))->modify('-1 month');
                $fin = (new DateTime($ahora->format('Y-m-01') . ' 00:00:00'))->modify('-1 second');
                break;
            case 'MES_ACTUAL_Y_ANTERIOR':
                $ini = (new DateTime($ahora->format('Y-m-01') . ' 00:00:00'))->modify('-1 month');
                $fin = clone $ahora;
                break;
            default: // DIAS_ATRAS
                $ini = (clone $hoy)->modify('-' . (int)$p['dias_atras'] . ' days');
                $fin = clone $ahora;
        }
        // Si "hoy" es el día 1, MES_ACTUAL apenas empieza: se pide al menos desde ayer.
        if ($ini >= $fin) $ini = (clone $fin)->modify('-1 day');
        return array($ini, $fin);
    }

    /** Siguiente fecha/hora de ejecución estrictamente posterior a $desde. */
    public static function calcularProxima($p, DateTime $desde = null)
    {
        $desde = $desde ? clone $desde : new DateTime();
        list($h, $m) = array_map('intval', explode(':', $p['hora_ejecucion']));

        if ($p['frecuencia'] === 'CADA_HORAS') {
            // Alineado a la hora de ejecución: hora_ejecucion + k*intervalo.
            $intervalo = max(1, (int)$p['intervalo_horas']) * 3600;
            $base = new DateTime($desde->format('Y-m-d') . sprintf(' %02d:%02d:00', $h, $m));
            if ($base > $desde) $base->modify('-1 day');
            $k = (int)floor(($desde->getTimestamp() - $base->getTimestamp()) / $intervalo) + 1;
            return (new DateTime())->setTimestamp($base->getTimestamp() + $k * $intervalo);
        }

        $c = new DateTime($desde->format('Y-m-d') . sprintf(' %02d:%02d:00', $h, $m));
        if ($c <= $desde) $c->modify('+1 day');
        for ($i = 0; $i < 400; $i++) {
            if (self::diaCoincide($p, $c)) return $c;
            $c->modify('+1 day');
        }
        throw new Exception('No se pudo calcular la próxima ejecución.');
    }

    private static function diaCoincide($p, DateTime $d)
    {
        if ($p['frecuencia'] === 'DIARIA') return true;
        if ($p['frecuencia'] === 'SEMANAL') {
            $dias = $p['dias_semana'] ? array_map('intval', explode(',', $p['dias_semana'])) : array(1);
            return in_array((int)$d->format('N'), $dias, true);
        }
        // MENSUAL
        $dm = (int)$p['dia_mes'];
        if ($dm === 31) return $d->format('j') === $d->format('t');
        return (int)$d->format('j') === $dm;
    }

    public static function describir($p)
    {
        $hora = substr($p['hora_ejecucion'], 0, 5);
        switch ($p['frecuencia']) {
            case 'CADA_HORAS': return 'Cada ' . (int)$p['intervalo_horas'] . ' h (desde las ' . $hora . ')';
            case 'SEMANAL':
                $n = array(1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom');
                $txt = array();
                foreach (explode(',', (string)$p['dias_semana']) as $x) if (isset($n[(int)$x])) $txt[] = $n[(int)$x];
                return implode(', ', $txt) . ' a las ' . $hora;
            case 'MENSUAL': return ((int)$p['dia_mes'] === 31 ? 'Último día' : 'Día ' . (int)$p['dia_mes']) . ' de cada mes a las ' . $hora;
            default: return 'Diario a las ' . $hora;
        }
    }

    public static function describirPeriodo($p)
    {
        if ($p['periodo'] === 'DIAS_ATRAS') return 'Últimos ' . (int)$p['dias_atras'] . ' días hasta el momento de ejecución';
        $per = self::periodos();
        return isset($per[$p['periodo']]) ? $per[$p['periodo']] : $p['periodo'];
    }

    /* ------------------------------------------------------------------ */

    /**
     * Ejecuta una programación: crea las solicitudes al SAT.
     * $manual = true (botón "Ejecutar ahora") no mueve la próxima ejecución.
     */
    public static function ejecutar($con, $p, $manual = false)
    {
        $ahora = new DateTime();
        list($ini, $fin) = self::calcularRango($p, $ahora);
        $tipos = $p['tipo_descarga'] === 'AMBAS' ? array('EMITIDAS', 'RECIBIDAS') : array($p['tipo_descarga']);
        // Desfase de segundos en el inicio para que ejecuciones del mismo periodo
        // no repitan parámetros idénticos ante el SAT (ver normalizarRango).
        $desfase = ((int)$p['ejecuciones'] % 3000) + 1;
        $resultados = array();
        $okTodos = true;
        $reintentar = false;
        foreach ($tipos as $tipo) {
            try {
                $r = SatDownloadService::crearSolicitud($con, $p['id_empresa'], $tipo, $p['tipo_archivo'],
                    $ini->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), array(
                        'tipo_comprobante' => $p['tipo_comprobante'], 'origen' => 'PROGRAMADA', 'id_programacion' => $p['id_programacion'],
                        'auto_descargar' => $p['auto_descargar'], 'auto_procesar' => $p['auto_procesar'], 'desfase_segundos' => $desfase));
                $resultados[] = $tipo . ': ' . ($r['ok'] ? 'solicitud #' . $r['id_solicitud'] : 'ERROR ' . $r['mensaje']);
                if (!$r['ok']) $okTodos = false;
                if (!empty($r['error_comunicacion']) && stripos($r['mensaje'], 'comunicación') !== false) $reintentar = true;
            } catch (Exception $e) {
                $resultados[] = $tipo . ': ERROR ' . $e->getMessage();
                $okTodos = false;
            }
        }
        $resumen = ($manual ? '[Manual] ' : '') . $ini->format('d/m/Y H:i') . ' - ' . $fin->format('d/m/Y H:i') . ' | ' . implode(' | ', $resultados);
        $idProg = (int)$p['id_programacion'];
        if ($manual) {
            sat_exec($con, "UPDATE sat_programaciones SET ultima_ejecucion=NOW(), ultimo_resultado=?, ejecuciones=ejecuciones+1 WHERE id_programacion=?",
                'si', array(mb_substr($resumen, 0, 500, 'UTF-8'), $idProg));
        } else {
            $prox = self::calcularProxima($p, $ahora);
            // Si el SAT no respondió (red/servicio caído) se reintenta en 30 min
            // en lugar de esperar al siguiente horario.
            $reintento = (new DateTime())->modify('+30 minutes');
            if ($reintentar && $prox > $reintento) {
                $prox = $reintento;
                $resumen .= ' | Sin comunicación con el SAT: se reintenta a las ' . $prox->format('H:i');
            }
            sat_exec($con, "UPDATE sat_programaciones SET ultima_ejecucion=NOW(), ultimo_resultado=?, ejecuciones=ejecuciones+1, proxima_ejecucion=? WHERE id_programacion=?",
                'ssi', array(mb_substr($resumen, 0, 500, 'UTF-8'), $prox->format('Y-m-d H:i:s'), $idProg));
        }
        log_proceso($con, $p['id_empresa'], null, 'PROGRAMACION', $okTodos ? 'OK' : 'ERROR', '#' . $idProg . ' ' . $p['nombre'] . ' ' . $resumen);
        return array('ok' => $okTodos, 'mensaje' => $resumen);
    }

    /** Llamado por el cron: ejecuta las programaciones vencidas. */
    public static function ejecutarPendientes($con, $salida = null)
    {
        $progs = sat_rows($con, "SELECT p.* FROM sat_programaciones p JOIN sat_empresas e ON e.id_empresa=p.id_empresa AND e.activo=1
            WHERE p.activo=1 AND (p.proxima_ejecucion IS NULL OR p.proxima_ejecucion<=NOW()) ORDER BY p.proxima_ejecucion");
        $n = 0;
        foreach ($progs as $p) {
            if ($p['proxima_ejecucion'] === null) {
                // Recién activada sin fecha: solo se agenda.
                $prox = self::calcularProxima($p);
                sat_exec($con, "UPDATE sat_programaciones SET proxima_ejecucion=? WHERE id_programacion=?", 'si', array($prox->format('Y-m-d H:i:s'), (int)$p['id_programacion']));
                continue;
            }
            $r = self::ejecutar($con, $p, false);
            if ($salida) call_user_func($salida, 'Programación #' . $p['id_programacion'] . ' ' . $p['nombre'] . ': ' . $r['mensaje']);
            $n++;
        }
        return $n;
    }
}
