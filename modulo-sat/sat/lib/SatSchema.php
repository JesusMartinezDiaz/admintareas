<?php
/**
 * Instala o actualiza el esquema del módulo a partir de sql/sat_install.sql.
 *
 * 1. Ejecuta cada sentencia del archivo (todas las tablas usan IF NOT EXISTS).
 * 2. Para las tablas que ya existían (instalación anterior del módulo) agrega
 *    las columnas que falten, leyendo su definición del mismo archivo.
 * No borra ni modifica columnas existentes.
 */
class SatSchema
{
    public static function instalar($con)
    {
        $sqlFile = SAT_ROOT . '/sql/sat_install.sql';
        $sql = file_get_contents($sqlFile);
        if ($sql === false) throw new Exception('No se encontró ' . $sqlFile);
        $log = array();

        $sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", $sinComentarios))) as $stmt) {
            if (!mysqli_query($con, $stmt)) {
                throw new Exception('Error ejecutando: ' . substr($stmt, 0, 80) . '... ' . mysqli_error($con));
            }
        }
        $log[] = 'Tablas verificadas.';

        foreach (self::columnasDeclaradas($sql) as $tabla => $cols) {
            $existentes = array();
            $rs = mysqli_query($con, "SHOW COLUMNS FROM `$tabla`");
            while ($rs && ($r = mysqli_fetch_assoc($rs))) $existentes[strtolower($r['Field'])] = true;
            foreach ($cols as $col => $def) {
                if (isset($existentes[strtolower($col)])) continue;
                if (stripos($def, 'PRIMARY KEY') !== false) continue;
                if (!mysqli_query($con, "ALTER TABLE `$tabla` ADD COLUMN `$col` $def")) {
                    $log[] = "No se pudo agregar $tabla.$col: " . mysqli_error($con);
                } else {
                    $log[] = "Columna agregada: $tabla.$col";
                }
            }
        }

        // Índices que la versión anterior pudo no tener.
        self::asegurarIndice($con, 'sat_paquetes_descarga', 'uk_sat_paquete', 'UNIQUE KEY uk_sat_paquete (id_solicitud, id_paquete_sat)', $log);
        self::asegurarIndice($con, 'sat_cfdi', 'idx_sat_cfdi_fecha', 'KEY idx_sat_cfdi_fecha (id_empresa, fecha_emision)', $log);
        self::asegurarIndice($con, 'sat_programaciones', 'idx_sat_prog_proxima', 'KEY idx_sat_prog_proxima (activo, proxima_ejecucion)', $log);
        self::asegurarIndice($con, 'sat_cfdi_pagos_doctos', 'idx_sat_pdoc_uuid', 'KEY idx_sat_pdoc_uuid (uuid_documento)', $log);

        // La versión anterior tenía UNIQUE(uuid): un CFDI entre dos empresas
        // registradas (emisor y receptor) no podía guardarse para ambas.
        $rs = mysqli_query($con, "SHOW INDEX FROM sat_cfdi WHERE Non_unique=0 AND Key_name<>'PRIMARY'");
        $unicos = array();
        while ($rs && ($r = mysqli_fetch_assoc($rs))) $unicos[$r['Key_name']][] = $r['Column_name'];
        if (!isset($unicos['uk_sat_cfdi_empresa_uuid'])) {
            if (mysqli_query($con, "ALTER TABLE sat_cfdi ADD UNIQUE KEY uk_sat_cfdi_empresa_uuid (id_empresa, uuid)")) $log[] = 'Índice agregado: sat_cfdi.uk_sat_cfdi_empresa_uuid';
            else $log[] = 'No se pudo agregar uk_sat_cfdi_empresa_uuid: ' . mysqli_error($con);
        }
        foreach ($unicos as $k => $cols) {
            if ($cols === array('uuid')) {
                if (mysqli_query($con, "ALTER TABLE sat_cfdi DROP INDEX `$k`")) $log[] = "Índice único anterior sat_cfdi.$k (solo uuid) reemplazado por (id_empresa, uuid).";
            }
        }
        self::asegurarIndice($con, 'sat_cfdi', 'idx_sat_cfdi_uuid', 'KEY idx_sat_cfdi_uuid (uuid)', $log);

        // CFDI existentes que solo tenían "VIGENTE" en mayúsculas/minúsculas mixtas.
        mysqli_query($con, "UPDATE sat_cfdi SET estatus_sat='VIGENTE' WHERE estatus_sat IS NULL OR estatus_sat=''");
        return $log;
    }

    private static function asegurarIndice($con, $tabla, $nombre, $def, &$log)
    {
        $rs = mysqli_query($con, "SHOW INDEX FROM `$tabla` WHERE Key_name='" . mysqli_real_escape_string($con, $nombre) . "'");
        if ($rs && mysqli_num_rows($rs) > 0) return;
        if (mysqli_query($con, "ALTER TABLE `$tabla` ADD $def")) $log[] = "Índice agregado: $tabla.$nombre";
        else $log[] = "No se pudo agregar índice $tabla.$nombre: " . mysqli_error($con);
    }

    /** @return array tabla => [columna => definición] */
    private static function columnasDeclaradas($sql)
    {
        $out = array();
        if (!preg_match_all('/CREATE TABLE IF NOT EXISTS\s+(\w+)\s*\((.*?)\)\s*ENGINE/s', $sql, $m, PREG_SET_ORDER)) return $out;
        foreach ($m as $t) {
            $cols = array();
            foreach (preg_split('/\r?\n/', $t[2]) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '--') === 0) continue;
                if (preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT)\b/i', $line)) continue;
                if (!preg_match('/^(\w+)\s+(.+?),?$/', $line, $c)) continue;
                $cols[$c[1]] = rtrim($c[2], ',');
            }
            $out[$t[1]] = $cols;
        }
        return $out;
    }
}
