<?php
/** Vista previa: próximas 5 ejecuciones y el periodo que se pediría al SAT. */
require_once __DIR__ . '/_init.php';
$p = SchedulerService::normalizar($_POST);
$p['ejecuciones'] = 0;
$fechas = array();
$d = new DateTime();
for ($i = 0; $i < 5; $i++) {
    $d = SchedulerService::calcularProxima($p, $d);
    list($ini, $fin) = SchedulerService::calcularRango($p, $d);
    $fechas[] = array('ejecucion' => $d->format('Y-m-d H:i'), 'desde' => $ini->format('Y-m-d H:i'), 'hasta' => $fin->format('Y-m-d H:i'));
}
$tipos = $p['tipo_descarga'] === 'AMBAS' ? 2 : 1;
sat_json(true, '', array('descripcion' => SchedulerService::describir($p), 'periodo' => SchedulerService::describirPeriodo($p),
    'ejecuciones' => $fechas, 'peticiones_por_ejecucion' => $tipos));
