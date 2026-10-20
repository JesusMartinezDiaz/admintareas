-- =====================================================================
-- Alta del módulo SAT y sus pantallas en el catálogo de permisos.
--
-- gl_cat_modulos    (id_modulo, nombre_modulo, url, icono)
-- gl_cat_submodulos (id_submodulo, nombre_submodulo, id_modulo, URL, categoria)
--
-- Los id se calculan con MAX()+1, así funciona tanto si las columnas id son
-- AUTO_INCREMENT como si no. Se puede ejecutar más de una vez: no duplica.
-- =====================================================================

-- 1) Módulo
INSERT INTO gl_cat_modulos (id_modulo, nombre_modulo, url, icono)
SELECT COALESCE(MAX(id_modulo), 0) + 1, 'SAT', 'sat/index.php', 'fa-university'
FROM gl_cat_modulos
HAVING NOT EXISTS (SELECT 1 FROM gl_cat_modulos WHERE nombre_modulo = 'SAT');

SET @id_modulo_sat = (SELECT id_modulo FROM gl_cat_modulos WHERE nombre_modulo = 'SAT' LIMIT 1);
SET @id_sub = (SELECT COALESCE(MAX(id_submodulo), 0) FROM gl_cat_submodulos);

-- 2) Submódulos (una fila por pantalla)
INSERT INTO gl_cat_submodulos (id_submodulo, nombre_submodulo, id_modulo, URL, categoria)
SELECT @id_sub + s.orden, s.nombre, @id_modulo_sat, s.url, s.categoria
FROM (
              SELECT 1 AS orden, 'Inicio SAT'             AS nombre, 'sat/index.php'                  AS url, 'Descarga SAT' AS categoria
    UNION ALL SELECT 2,  'Empresas y e.firma',     'sat/empresas.php',               'Descarga SAT'
    UNION ALL SELECT 3,  'Descargas SAT',          'sat/descargas.php',              'Descarga SAT'
    UNION ALL SELECT 4,  'Programación SAT',       'sat/programacion.php',           'Descarga SAT'
    UNION ALL SELECT 5,  'Bitácora SAT',           'sat/bitacora.php',               'Descarga SAT'
    UNION ALL SELECT 6,  'Reporte fiscal',         'sat/reporte.php',                'Reportes'
    UNION ALL SELECT 7,  'Contabilidad',           'sat/contabilidad.php',           'Contabilidad'
    UNION ALL SELECT 8,  'Póliza (detalle)',       'sat/poliza.php',                 'Contabilidad'
    UNION ALL SELECT 9,  'Configuración contable', 'sat/configuracion_contable.php', 'Contabilidad'
    UNION ALL SELECT 10, 'Catálogo de cuentas',    'sat/cuentas_contables.php',      'Contabilidad'
    UNION ALL SELECT 11, 'Reglas contables',       'sat/reglas_contables.php',       'Contabilidad'
) s
WHERE @id_modulo_sat IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM gl_cat_submodulos x WHERE x.id_modulo = @id_modulo_sat AND x.URL = s.url)
ORDER BY s.orden;

-- 3) Verificación
SELECT m.id_modulo, m.nombre_modulo, m.url, m.icono,
       s.id_submodulo, s.nombre_submodulo, s.URL, s.categoria
FROM gl_cat_modulos m
JOIN gl_cat_submodulos s ON s.id_modulo = m.id_modulo
WHERE m.nombre_modulo = 'SAT'
ORDER BY s.id_submodulo;
