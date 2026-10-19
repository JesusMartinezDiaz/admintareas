<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
sat_page_inicio('programacion', 'Programación de peticiones');
$con = sat_db();
$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : '/usr/local/bin/php';
$cronCmd = '*/15 * * * * ' . $php . ' -q ' . SAT_ROOT . '/cron/cron_sat.php >> ' . SAT_ROOT . '/logs/cron_sat.log 2>&1';
?>
<div id="boxCron"></div>

<div class="sat-card">
  <div class="sat-card-head">
    <div>
      <h4>Programaciones</h4>
      <div class="sat-sub">Define cada cuándo se envía al SAT la petición de emitidos y/o recibidos. Al terminar el SAT, el cron descarga y procesa los XML automáticamente y el reporte se actualiza solo.</div>
    </div>
    <div class="sat-acciones"><button type="button" class="btn btn-primary" onclick="fnProgNueva()"><i class="fa fa-plus"></i> Nueva programación</button></div>
  </div>
  <div class="sat-scroll">
    <table class="sat-tabla">
      <thead><tr><th>Nombre</th><th>Empresa</th><th>Qué pide</th><th>Cuándo</th><th>Periodo solicitado</th><th>Próxima</th><th>Última ejecución</th><th>Estado</th><th></th></tr></thead>
      <tbody id="tbProg"><tr><td colspan="9" class="sat-cargando">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="sat-card sat-form">
  <h5><i class="fa fa-terminal"></i> Configuración del cron (una sola vez en el servidor)</h5>
  <div class="sat-sub">Las programaciones se ejecutan cuando corre el cron. Agrégalo en cPanel → Cron Jobs (o <code>crontab -e</code>) para que corra cada 15 minutos:</div>
  <div class="sat-code"><?= e($cronCmd) ?></div>
  <div class="sat-ayuda">Si tu hosting no permite cron por línea de comandos, configura <code>cron_token</code> en <code>config/sat_config.php</code> y llama cada 15 min a <code>sat/cron/cron_web.php?token=...</code> con un servicio externo.</div>
</div>

<!-- Modal programación -->
<div class="sat-modal" id="mdlProg">
  <div class="sat-modal-box lg">
    <div class="sat-modal-head"><h4 id="progTitulo">Programación</h4><button type="button" class="sat-x" data-sat-close>&times;</button></div>
    <form id="frmProg" class="sat-form" onsubmit="return false;">
      <div class="sat-modal-body">
        <input type="hidden" name="id_programacion" id="pg_id">
        <div class="row">
          <div class="col-sm-5 sat-campo"><label>Nombre</label><input class="form-control" name="nombre" id="pg_nombre" placeholder="Ej. Diario emitidos y recibidos" required></div>
          <div class="col-sm-7 sat-campo"><label>Empresa</label><select class="form-control" name="id_empresa" id="pg_empresa"><?= sat_opciones_empresas($con, 0, 'Seleccione') ?></select></div>
        </div>
        <h5>¿Qué se le pide al SAT?</h5>
        <div class="row">
          <div class="col-sm-4 sat-campo"><label>CFDI</label>
            <select class="form-control" name="tipo_descarga" id="pg_tipo"><option value="AMBAS">Emitidos y recibidos (2 peticiones)</option><option value="EMITIDAS">Solo emitidos</option><option value="RECIBIDAS">Solo recibidos</option></select></div>
          <div class="col-sm-4 sat-campo"><label>Archivo</label>
            <select class="form-control" name="tipo_archivo" id="pg_archivo"><option value="CFDI">XML (para el reporte y contabilidad)</option><option value="METADATA">Metadata (detecta cancelaciones)</option></select></div>
          <div class="col-sm-4 sat-campo"><label>Tipo de comprobante</label>
            <select class="form-control" name="tipo_comprobante" id="pg_tc"><option value="">Todos (facturas, notas de crédito, pagos, nómina)</option>
              <?php foreach (sat_catalogo_tipo_comprobante() as $k => $v): ?><option value="<?= $k ?>"><?= e($k . ' - ' . $v) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-sm-4 sat-campo"><label>Periodo a solicitar</label>
            <select class="form-control" name="periodo" id="pg_periodo">
              <?php foreach (SchedulerService::periodos() as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-sm-2 sat-campo" id="wrapDiasAtras"><label>N días</label><input class="form-control" type="number" min="0" max="365" name="dias_atras" id="pg_dias_atras" value="3"></div>
          <div class="col-sm-6 sat-campo"><div class="sat-ayuda" style="margin-top:22px">Recomendado: <b>Últimos 3 días</b> a diario. El SAT puede tardar hasta 72 h en reflejar CFDI recibidos; el traslape no duplica nada.</div></div>
        </div>
        <h5>¿Cuándo?</h5>
        <div class="row">
          <div class="col-sm-3 sat-campo"><label>Frecuencia</label>
            <select class="form-control" name="frecuencia" id="pg_frec">
              <?php foreach (SchedulerService::frecuencias() as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-sm-2 sat-campo" id="wrapHoras"><label>Cada (horas)</label><input class="form-control" type="number" min="1" max="168" name="intervalo_horas" id="pg_horas" value="6"></div>
          <div class="col-sm-2 sat-campo"><label id="lblHora">Hora</label><input class="form-control" type="time" name="hora_ejecucion" id="pg_hora" value="06:00"></div>
          <div class="col-sm-5 sat-campo" id="wrapDias"><label>Días</label>
            <div class="sat-dias">
              <?php foreach (array(1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom') as $k => $v): ?>
                <label><input type="checkbox" name="dias_semana[]" value="<?= $k ?>"><?= $v ?></label>
              <?php endforeach; ?>
            </div></div>
          <div class="col-sm-3 sat-campo" id="wrapDiaMes"><label>Día del mes</label>
            <select class="form-control" name="dia_mes" id="pg_dia_mes"><?php for ($i = 1; $i <= 28; $i++) echo '<option value="' . $i . '">' . $i . '</option>'; ?><option value="31">Último día</option></select></div>
        </div>
        <h5>Al terminar el SAT</h5>
        <div class="sat-campo">
          <label class="sat-check"><input type="checkbox" name="auto_descargar" id="pg_auto_desc" value="1" checked> Descargar paquetes automáticamente</label>
          <label class="sat-check"><input type="checkbox" name="auto_procesar" id="pg_auto_proc" value="1" checked> Procesar XML (reporte y pólizas)</label>
          <label class="sat-check"><input type="checkbox" name="activo" id="pg_activo" value="1" checked> Programación activa</label>
        </div>
        <div class="sat-preview" id="pgPreview">...</div>
      </div>
      <div class="sat-modal-foot"><button type="button" class="btn btn-default btn-secondary" data-sat-close>Cancelar</button><button type="button" class="btn btn-primary" onclick="fnProgGuardar()"><i class="fa fa-save"></i> Guardar</button></div>
    </form>
  </div>
</div>

<div class="sat-modal" id="mdlHist">
  <div class="sat-modal-box lg">
    <div class="sat-modal-head"><h4 id="histTitulo">Historial</h4><button type="button" class="sat-x" data-sat-close>&times;</button></div>
    <div class="sat-modal-body" id="histBody"></div>
  </div>
</div>
<?php
sat_page_fin(array('js/programacion.js'));
include('../assets/footer.php');
