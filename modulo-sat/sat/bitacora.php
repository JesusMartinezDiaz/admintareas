<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
sat_page_inicio('bitacora', 'Bitácora');
$con = sat_db();
?>
<div class="sat-card sat-form">
  <div class="row">
    <div class="col-sm-3 sat-campo"><label>Empresa</label><select class="form-control" id="bEmpresa"><?= sat_opciones_empresas($con, 0, 'Todas') ?></select></div>
    <div class="col-sm-2 sat-campo"><label>Proceso</label><select class="form-control" id="bProceso"><option value="">Todos</option></select></div>
    <div class="col-sm-2 sat-campo"><label>Estatus</label><select class="form-control" id="bEstatus"><option value="">Todos</option><option>OK</option><option>ERROR</option></select></div>
    <div class="col-sm-2 sat-campo"><label>Desde</label><input type="date" class="form-control" id="bDesde" value="<?= date('Y-m-d', strtotime('-7 days')) ?>"></div>
    <div class="col-sm-2 sat-campo"><label>Hasta</label><input type="date" class="form-control" id="bHasta" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-sm-1 sat-campo"><label>&nbsp;</label><button class="btn btn-primary" onclick="fnBitacora()"><i class="fa fa-search"></i></button></div>
  </div>
</div>
<div class="sat-card"><div class="sat-scroll"><table class="sat-tabla"><thead><tr><th>Fecha</th><th>Empresa</th><th>Solicitud</th><th>Proceso</th><th>Estatus</th><th>Usuario</th><th>Mensaje</th></tr></thead><tbody id="tbLog"></tbody></table></div></div>
<?php
sat_page_fin();
?>
<script>
function fnBitacora() {
  SAT.api('getBitacora.php', { id_empresa: $('#bEmpresa').val(), proceso: $('#bProceso').val(), estatus: $('#bEstatus').val(), desde: $('#bDesde').val(), hasta: $('#bHasta').val() }, { method: 'GET' }).done(function (r) {
    var sel = $('#bProceso').val();
    $('#bProceso').html('<option value="">Todos</option>' + r.procesos.map(function (p) { return '<option' + (p === sel ? ' selected' : '') + '>' + SAT.esc(p) + '</option>'; }).join(''));
    $('#tbLog').html(r.log.length ? r.log.map(function (l) {
      return '<tr><td><small>' + SAT.fecha(l.fecha) + '</small></td><td>' + SAT.esc(l.rfc || '') + '</td><td>' + (l.id_solicitud || '') + '</td><td>' + SAT.esc(l.proceso) + '</td><td><span class="sat-badge ' + (l.estatus === 'OK' ? 'ok' : 'err') + '">' + SAT.esc(l.estatus) + '</span></td><td>' + SAT.esc(l.usuario || '') + '</td><td><small>' + SAT.esc(l.mensaje) + '</small></td></tr>';
    }).join('') : '<tr><td colspan="7" class="sat-vacio">Sin registros.</td></tr>');
  });
}
$(fnBitacora);
</script>
<?php include('../assets/footer.php');
