<?php
/**
 * Configuración del módulo SAT.
 *
 * Copia este archivo como `sat_config.php` (mismo directorio) y ajústalo.
 * `sat_config.php` NO debe subirse al repositorio.
 */
return array(

    // Llave para cifrar las contraseñas de la e.firma (AES-256).
    // Preferentemente defínela como variable de entorno SAT_SECRET_KEY.
    // IMPORTANTE: si ya tenías certificados cargados con la versión anterior
    // del módulo, usa la MISMA llave que tenías; si nunca la cambiaste, deja
    // vacío y se usará la llave por defecto anterior (y la pantalla de inicio
    // mostrará una advertencia para que la cambies y vuelvas a cargar la e.firma).
    'secret_key' => getenv('SAT_SECRET_KEY') ?: '',

    // Base de datos.
    //  - null: usa la conexión del sistema ($con de assets/dbConection.php).
    //  - array: conexión propia para las tablas sat_* , p. ej.
    //    array('host' => 'localhost', 'user' => 'usr', 'pass' => '***', 'name' => 'bd_sat')
    'db' => null,

    // Ruta a la carpeta assets del sistema (head.php, footer.php, dbConection.php, control.php).
    'ruta_assets' => dirname(__DIR__, 2) . '/assets',

    // Carpeta donde se guardan certificados, ZIP y XML. Recomendado: FUERA de
    // public_html, p. ej. '/home/USUARIO/sat_datos'. Por defecto: sat/uploads.
    'dir_datos' => dirname(__DIR__) . '/uploads',

    'charset' => 'utf8mb4',
    'zona_horaria' => 'America/Mexico_City',

    // Guarda en logs/ las peticiones y respuestas SOAP (solo para diagnóstico).
    'debug_soap' => false,

    // Token para ejecutar el cron por URL (cron/cron_web.php?token=...).
    // Vacío = deshabilitado (solo se puede ejecutar por línea de comandos).
    'cron_token' => '',

    // Minutos mínimos entre verificaciones automáticas de una misma solicitud.
    'verificar_cada_minutos' => 10,

    // Reintentos automáticos cuando el SAT devuelve error interno 404/5006 en recibidas.
    'max_reintentos_recibidas' => 2,

    // Días tras los cuales una solicitud sin terminar se marca VENCIDA (el SAT
    // conserva los paquetes 72 horas).
    'dias_vencimiento_solicitud' => 3,

    // Al terminar una solicitud MANUAL, ¿descargar y procesar automáticamente en el cron?
    'auto_descargar_manuales' => true,

    // Usuarios ($usuario_acceso) que pueden administrar empresas, e.firma y
    // programaciones. Vacío = cualquier usuario con sesión.
    'usuarios_admin' => array(),
);
