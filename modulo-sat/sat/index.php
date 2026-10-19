<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
sat_page_inicio('inicio', 'Descarga masiva CFDI');
$con = sat_db();

// ¿Están instaladas/actualizadas las tablas?
$faltaInstalar = false;
$rs = mysqli_query($con, "SHOW TABLES LIKE 'sat_programaciones'");
if (!$rs || mysqli_num_rows($rs) === 0) $faltaInstalar = true;
else {
    $rs = mysqli_query($con, "SHOW COLUMNS FROM sat_cfdi LIKE 'origen_dato'");
    if (!$rs || mysqli_num_rows($rs) === 0) $faltaInstalar = true;
}

$avisos = array();
if (!is_file(SAT_ROOT . '/config/sat_config.php')) $avisos[] = array('warning', 'No existe config/sat_config.php: se está usando la configuración de ejemplo. Cópiala y ajústala.');
if ((string)sat_config('secret_key', '') === '') $avisos[] = array('warning', 'No se configuró secret_key (o SAT_SECRET_KEY): las contraseñas de e.firma se cifran con la llave por defecto. Configúrala y vuelve a cargar las e.firma.');
if (strpos(realpath(sat_path_datos()) ?: '', realpath(SAT_ROOT)) === 0 && !is_file(sat_path_datos('certificados') . '/.htaccess')) $avisos[] = array('danger', 'La carpeta uploads/certificados no está protegida (.htaccess). Recomendado: mover dir_datos fuera de public_html.');
foreach (array('curl', 'openssl', 'dom', 'zip', 'mysqli', 'mbstring') as $ext) {
    if (!extension_loaded($ext)) $avisos[] = array('danger', 'Falta la extensión PHP ' . $ext . '.');
}

if ($faltaInstalar) {
    ?>
    <div class="sat-card">
      <h4>Instalación / actualización de base de datos</h4>
      <p>Las tablas del módulo no existen o son de la versión anterior. Se crearán las tablas que falten y se agregarán columnas nuevas sin borrar información.</p>
      <?php if (sat_es_admin()): ?>
        <button class="btn btn-primary" onclick="SAT.api('postInstalar.php', {}, {loading:'Actualizando base de datos...'}).done(function(r){ SAT.msg(true, r.msg).then(function(){ location.reload(); }); })"><i class="fa fa-database"></i> Instalar / actualizar ahora</button>
      <?php else: ?>
        <div class="sat-alert sat-alert-warning">Pide a un administrador que ejecute la instalación.</div>
      <?php endif; ?>
      <div class="sat-ayuda">También por consola: <code>php <?= e(SAT_ROOT) ?>/cron/instalar.php</code></div>
    </div>
    <?php
    sat_page_fin();
    include('../assets/footer.php');
    return;
}

$n = function ($sql) use ($con) { $r = sat_row($con, $sql); return $r ? (int)array_values($r)[0] : 0; };
$empresas = $n("SELECT COUNT(*) FROM sat_empresas WHERE activo=1");
$cfdiMes = $n("SELECT COUNT(*) FROM sat_cfdi WHERE fecha_emision>='" . date('Y-m-01') . "'");
$pendientes = $n("SELECT COUNT(*) FROM sat_solicitudes_descarga WHERE estado_solicitud IN ('" . implode("','", SatDownloadService::$estadosPendientes) . "')");
$errores = $n("SELECT COUNT(*) FROM sat_solicitudes_descarga WHERE estado_solicitud IN ('ERROR','RECHAZADA','ERROR_TEMPORAL_SAT','VENCIDA') AND fecha_alta>=DATE_SUB(NOW(), INTERVAL 7 DAY)");
$progs = $n("SELECT COUNT(*) FROM sat_programaciones WHERE activo=1");
$prox = sat_row($con, "SELECT p.nombre, p.proxima_ejecucion, e.rfc FROM sat_programaciones p JOIN sat_empresas e ON e.id_empresa=p.id_empresa WHERE p.activo=1 ORDER BY p.proxima_ejecucion LIMIT 1");
$cron = @file_get_contents(sat_path_logs() . '/cron_ultima_ejecucion.txt');
if ($progs > 0 && (!$cron || strtotime($cron) < time() - 3600)) {
    $avisos[] = array('danger', $cron ? 'El cron no se ejecuta desde ' . $cron . '. Las programaciones no están corriendo.' : 'El cron nunca se ha ejecutado: las programaciones no correrán. Ver instrucciones en Programación.');
}
$porVencer = sat_rows($con, "SELECT e.rfc, c.fecha_vencimiento FROM sat_certificados c JOIN sat_empresas e ON e.id_empresa=c.id_empresa AND e.activo=1 WHERE c.activo=1 AND c.fecha_vencimiento < DATE_ADD(NOW(), INTERVAL 30 DAY)");
foreach ($porVencer as $c) $avisos[] = array(strtotime($c['fecha_vencimiento']) < time() ? 'danger' : 'warning', 'La e.firma de ' . $c['rfc'] . ' vence/venció el ' . date('d/m/Y', strtotime($c['fecha_vencimiento'])) . '.');
$sinCert = sat_rows($con, "SELECT e.rfc FROM sat_empresas e WHERE e.activo=1 AND NOT EXISTS (SELECT 1 FROM sat_certificados c WHERE c.id_empresa=e.id_empresa AND c.activo=1)");
foreach ($sinCert as $c) $avisos[] = array('info', 'La empresa ' . $c['rfc'] . ' no tiene e.firma cargada.');
$ultimas = sat_rows($con, "SELECT s.id_solicitud, s.tipo_descarga, s.tipo_archivo, s.origen, s.estado_solicitud, s.total_cfdi, s.fecha_alta, s.procesada, s.xml_procesados, e.rfc
    FROM sat_solicitudes_descarga s JOIN sat_empresas e ON e.id_empresa=s.id_empresa ORDER BY s.id_solicitud DESC LIMIT 8");

foreach ($avisos as $a) echo sat_alerta($a[1], $a[0]);
?>
<div class="sat-kpis">
  <div class="sat-kpi"><div class="t">Empresas activas</div><div class="v"><?= $empresas ?></div><div class="d"><a href="empresas.php">Administrar</a></div></div>
  <div class="sat-kpi verde"><div class="t">CFDI de este mes</div><div class="v"><?= number_format($cfdiMes) ?></div><div class="d"><a href="reporte.php">Ver reporte fiscal</a></div></div>
  <div class="sat-kpi ambar"><div class="t">Solicitudes en proceso SAT</div><div class="v"><?= $pendientes ?></div><div class="d"><?= $errores ?> con error (7 días)</div></div>
  <div class="sat-kpi morado"><div class="t">Programaciones activas</div><div class="v"><?= $progs ?></div>
    <div class="d"><?= $prox ? 'Próxima: ' . e($prox['rfc'] . ' · ' . date('d/m H:i', strtotime($prox['proxima_ejecucion']))) : '<a href="programacion.php">Crear programación</a>' ?></div></div>
  <div class="sat-kpi"><div class="t">Último cron</div><div class="v" style="font-size:16px"><?= $cron ? e(date('d/m H:i', strtotime($cron))) : 'Nunca' ?></div><div class="d">Cada 15 min recomendado</div></div>
</div>

<div class="row">
  <div class="col-sm-7">
    <div class="sat-card">
      <div class="sat-card-head"><h4>Últimas solicitudes</h4><a href="descargas.php" class="btn btn-sm btn-default btn-outline-secondary">Ver todas</a></div>
      <table class="sat-tabla"><thead><tr><th>#</th><th>RFC</th><th>Tipo</th><th>Origen</th><th>Estado</th><th class="num">CFDI</th><th>Fecha</th></tr></thead><tbody>
      <?php if (!$ultimas): ?><tr><td colspan="7" class="sat-vacio">Sin solicitudes.</td></tr><?php endif; ?>
      <?php foreach ($ultimas as $s): ?>
        <tr><td><?= (int)$s['id_solicitud'] ?></td><td><?= e($s['rfc']) ?></td><td><?= e($s['tipo_descarga']) ?> <small><?= e($s['tipo_archivo']) ?></small></td>
          <td><?= $s['origen'] === 'PROGRAMADA' ? '<span class="sat-badge azul">Programada</span>' : '<span class="sat-badge gris">Manual</span>' ?></td>
          <td><span class="sat-badge <?= in_array($s['estado_solicitud'], array('TERMINADA', 'SIN_INFORMACION'), true) ? 'ok' : (in_array($s['estado_solicitud'], SatDownloadService::$estadosPendientes, true) ? 'azul' : 'err') ?>"><?= e($s['estado_solicitud']) ?></span></td>
          <td class="num"><?= (int)$s['total_cfdi'] ?></td><td><small><?= e(date('d/m H:i', strtotime($s['fecha_alta']))) ?></small></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
  <div class="col-sm-5">
    <div class="sat-card">
      <h4>Cómo funciona</h4>
      <ol style="padding-left:18px;margin:0">
        <li>Da de alta la empresa y carga su <b>e.firma</b> (.cer, .key, contraseña) en <a href="empresas.php">Empresas</a>. Usa «Probar».</li>
        <li>Crea una <a href="programacion.php">programación</a> (p. ej. diario a las 6:00, emitidos y recibidos de los últimos 3 días).</li>
        <li>El cron envía la petición, verifica hasta que el SAT termina, descarga los ZIP y procesa los XML: facturas, notas de crédito, complementos de pago y nómina.</li>
        <li>Consulta el <a href="reporte.php">reporte fiscal</a>: IVA por flujo de efectivo, ISR estimado, PPD pendientes, alertas y exportación a Excel.</li>
        <li>Opcional: revisa las pólizas automáticas en <a href="contabilidad.php">Contabilidad</a>.</li>
      </ol>
      <?php if (sat_es_admin()): ?>
      <hr><button class="btn btn-sm btn-default btn-outline-secondary" onclick="SAT.api('postInstalar.php', {}, {loading:'Verificando base de datos...'}).done(function(r){ SAT.msg(true, r.msg); })"><i class="fa fa-database"></i> Verificar / actualizar tablas</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
sat_page_fin();
include('../assets/footer.php');
