-- =====================================================================
-- Alta del módulo SAT y sus pantallas en el catálogo de permisos.
--
-- AJUSTAR ANTES DE EJECUTAR: los nombres de columna de gl_cat_modulos y
-- gl_cat_submodulos son supuestos (no se tuvo la estructura real). Revisa
-- con:  SHOW CREATE TABLE gl_cat_modulos;  SHOW CREATE TABLE gl_cat_submodulos;
-- y renombra las columnas marcadas con  -- <columna>  si difieren.
--
-- Se puede ejecutar más de una vez: no duplica registros.
-- =====================================================================

-- 1) Módulo
INSERT INTO gl_cat_modulos (nombre, descripcion, url, icono, orden, activo)
SELECT 'SAT', 'Descarga masiva de CFDI, programación de peticiones, reporte fiscal y contabilidad', 'sat/index.php', 'fa-university', 99, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gl_cat_modulos WHERE nombre = 'SAT');

SET @id_modulo_sat = (SELECT id_modulo FROM gl_cat_modulos WHERE nombre = 'SAT' LIMIT 1);

-- 2) Submódulos (una fila por pantalla)
INSERT INTO gl_cat_submodulos (id_modulo, nombre, descripcion, url, orden, activo)
SELECT @id_modulo_sat, s.nombre, s.descripcion, s.url, s.orden, 1
FROM (
    SELECT 'Inicio'                 AS nombre, 'Indicadores y avisos del módulo SAT'                          AS descripcion, 'sat/index.php'                  AS url, 1  AS orden
    UNION ALL SELECT 'Empresas y e.firma',     'Alta de empresas, carga y prueba de e.firma',                     'sat/empresas.php',               2
    UNION ALL SELECT 'Descargas',              'Solicitudes manuales al SAT y seguimiento',                        'sat/descargas.php',              3
    UNION ALL SELECT 'Programación',           'Peticiones automáticas de emitidos y recibidos',                   'sat/programacion.php',           4
    UNION ALL SELECT 'Reporte fiscal',         'IVA por flujo de efectivo, ISR estimado, PPD pendientes',          'sat/reporte.php',                5
    UNION ALL SELECT 'Contabilidad',           'Pólizas automáticas generadas desde CFDI',                         'sat/contabilidad.php',           6
    UNION ALL SELECT 'Póliza (detalle)',       'Ver y resolver diferencias de una póliza',                         'sat/poliza.php',                 7
    UNION ALL SELECT 'Configuración contable', 'Cuentas de impuestos, clientes y proveedores por empresa',         'sat/configuracion_contable.php', 8
    UNION ALL SELECT 'Catálogo de cuentas',    'Cuentas contables por empresa',                                    'sat/cuentas_contables.php',      9
    UNION ALL SELECT 'Reglas contables',       'Reglas de clasificación y aprendizaje',                            'sat/reglas_contables.php',       10
    UNION ALL SELECT 'Bitácora',               'Registro de procesos del módulo SAT',                              'sat/bitacora.php',               11
) s
WHERE @id_modulo_sat IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM gl_cat_submodulos x WHERE x.id_modulo = @id_modulo_sat AND x.url = s.url);

-- 3) Verificación
SELECT m.id_modulo, m.nombre AS modulo, s.id_submodulo, s.nombre AS submodulo, s.url, s.orden
FROM gl_cat_modulos m
JOIN gl_cat_submodulos s ON s.id_modulo = m.id_modulo
WHERE m.nombre = 'SAT'
ORDER BY s.orden;
