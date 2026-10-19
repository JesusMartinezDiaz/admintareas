<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
sat_page_inicio('reporte', 'Reporte fiscal');
$con = sat_db();
$idSel = (int)sat_get('id_empresa', 0);
$empresas = sat_empresas_activas($con);
if (!$idSel && count($empresas) === 1) $idSel = (int)$empresas[0]['id_empresa'];
$meses = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<div class="sat-card sat-form sat-no-print">
  <div class="row">
    <div class="col-sm-4 sat-campo"><label>Empresa</label><select class="form-control" id="rEmpresa"><?= sat_opciones_empresas($con, $idSel, 'Seleccione') ?></select></div>
    <div class="col-sm-2 sat-campo"><label>Mes</label>
      <select class="form-control" id="rMes"><option value="">Personalizado</option>
        <?php for ($i = 0; $i < 24; $i++): $d = new DateTime('first day of -' . $i . ' month'); ?>
          <option value="<?= $d->format('Y-m') ?>" <?= $i === 0 ? 'selected' : '' ?>><?= $meses[(int)$d->format('n')] . ' ' . $d->format('Y') ?></option>
        <?php endfor; ?>
      </select></div>
    <div class="col-sm-2 sat-campo"><label>Desde</label><input type="date" class="form-control" id="rDesde" value="<?= date('Y-m-01') ?>"></div>
    <div class="col-sm-2 sat-campo"><label>Hasta</label><input type="date" class="form-control" id="rHasta" value="<?= date('Y-m-t') ?>"></div>
    <div class="col-sm-2 sat-campo"><label>&nbsp;</label><button type="button" class="btn btn-primary" style="width:100%" onclick="fnReporte()"><i class="fa fa-search"></i> Generar</button></div>
  </div>
  <div class="sat-acciones">
    <button type="button" class="btn btn-default btn-outline-secondary" onclick="fnExportarReporte()"><i class="fa fa-file-excel-o"></i> Exportar resumen</button>
    <button type="button" class="btn btn-default btn-outline-secondary" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
    <span class="sat-ayuda">Criterio de flujo de efectivo: PUE en el mes de emisión, PPD en el mes de pago (complemento) y notas de crédito restando.</span>
  </div>
</div>

<div id="rContenido"><div class="sat-card sat-vacio">Selecciona empresa y periodo y presiona Generar.</div></div>

<div class="sat-modal" id="mdlCfdi">
  <div class="sat-modal-box lg">
    <div class="sat-modal-head"><h4 id="cfdiTitulo">CFDI</h4><button type="button" class="sat-x" data-sat-close>&times;</button></div>
    <div class="sat-modal-body" id="cfdiBody"></div>
  </div>
</div>
<?php
sat_page_fin(array('js/reporte.js'));
include('../assets/footer.php');
