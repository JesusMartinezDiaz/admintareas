<?php
/**
 * Marco de las pantallas del módulo, dentro del head/footer del sistema:
 *
 * igual que el resto de módulos (p. ej. seguimiento/index.php):
 *
 *   <?php
 *   include('../assets/head.php');
 *   require_once __DIR__ . '/lib/page.php';
 *   sat_page_inicio('reporte', 'Reporte fiscal');
 *   ...contenido...
 *   sat_page_fin(array('js/reporte.js'));
 *   include('../assets/footer.php');
 */
require_once __DIR__ . '/bootstrap.php';

function sat_page_inicio($activo, $titulo)
{
    $menu = array(
        'inicio' => array('index.php', 'fa-home', 'Inicio'),
        'empresas' => array('empresas.php', 'fa-building', 'Empresas y e.firma'),
        'descargas' => array('descargas.php', 'fa-cloud-download fa-cloud-download-alt', 'Descargas'),
        'programacion' => array('programacion.php', 'fa-clock-o fa-clock', 'Programación'),
        'reporte' => array('reporte.php', 'fa-bar-chart fa-chart-bar', 'Reporte fiscal'),
        'contabilidad' => array('contabilidad.php', 'fa-book', 'Contabilidad'),
        'bitacora' => array('bitacora.php', 'fa-list', 'Bitácora'),
    );
    $v = @filemtime(SAT_ROOT . '/css/sat.css');
    echo '<link rel="stylesheet" href="css/sat.css?v=' . (int)$v . '">';
    echo '<main><div class="container-fluid sat-modulo">';
    echo '<div class="sat-titulo"><h2><i class="fa fa-university"></i> SAT · ' . e($titulo) . '</h2></div>';
    echo '<ul class="sat-tabs">';
    foreach ($menu as $k => $m) {
        echo '<li class="' . ($k === $activo ? 'active' : '') . '"><a href="' . $m[0] . '"><i class="fa ' . $m[1] . '"></i> ' . $m[2] . '</a></li>';
    }
    echo '</ul>';
}

function sat_page_fin($scripts = array())
{
    echo '</div></main>';
    $v = @filemtime(SAT_ROOT . '/js/sat.js');
    echo '<script src="js/sat.js?v=' . (int)$v . '"></script>';
    foreach ($scripts as $s) {
        echo '<script src="' . e($s) . '?v=' . (int)@filemtime(SAT_ROOT . '/' . $s) . '"></script>';
    }
}

/** <option> de empresas activas. */
function sat_opciones_empresas($con, $seleccionada = 0, $textoVacio = 'Seleccione')
{
    $html = $textoVacio !== null ? '<option value="0">' . e($textoVacio) . '</option>' : '';
    foreach (sat_empresas_activas($con) as $e) {
        $html .= '<option value="' . (int)$e['id_empresa'] . '"' . ((int)$seleccionada === (int)$e['id_empresa'] ? ' selected' : '') . '>' . e($e['rfc'] . ' - ' . $e['razon_social']) . '</option>';
    }
    return $html;
}

function sat_alerta($msg, $tipo = 'info')
{
    if ($msg === '' || $msg === null) return '';
    return '<div class="sat-alert sat-alert-' . e($tipo) . '">' . e($msg) . '</div>';
}
