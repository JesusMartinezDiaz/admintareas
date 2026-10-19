<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
sat_page_inicio('descargas', 'Descargas');
$con = sat_db();
$opcEmpresas = sat_opciones_empresas($con, 0, 'Seleccione');
?>
<div class="sat-card">
  <h4>Solicitar descarga al SAT</h4>
  <div class="sat-sub">Petición única. Para peticiones periódicas usa <a href="programacion.php">Programación</a>.</div>
  <form id="frmSolicitud" class="sat-form" onsubmit="return false;">
    <div class="row">
      <div class="col-sm-3 sat-campo"><label>Empresa</label><select class="form-control" name="id_empresa" required><?= $opcEmpresas ?></select></div>
      <div class="col-sm-2 sat-campo"><label>Tipo</label>
        <select class="form-control" name="tipo_descarga"><option value="AMBAS">Emitidos y recibidos</option><option value="EMITIDAS">Emitidos</option><option value="RECIBIDAS">Recibidos</option></select></div>
      <div class="col-sm-2 sat-campo"><label>Archivo</label>
        <select class="form-control" name="tipo_archivo"><option value="CFDI">XML</option><option value="METADATA">Metadata (estatus/cancelados)</option></select></div>
      <div class="col-sm-1 sat-campo"><label>Comprobante</label>
        <select class="form-control" name="tipo_comprobante"><option value="">Todos</option>
          <?php foreach (sat_catalogo_tipo_comprobante() as $k => $v): ?><option value="<?= $k ?>"><?= e($k . ' - ' . $v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-sm-2 sat-campo"><label>Desde</label><input class="form-control" type="date" name="fecha_inicial" value="<?= date('Y-m-01') ?>" required></div>
      <div class="col-sm-2 sat-campo"><label>Hasta</label><input class="form-control" type="date" name="fecha_final" value="<?= date('Y-m-d') ?>" required></div>
    </div>
    <div class="sat-acciones">
      <label class="sat-check"><input type="checkbox" name="auto" value="1" checked> Descargar y procesar automáticamente al terminar</label>
      <button type="button" class="btn btn-primary" onclick="fnSolicitar()"><i class="fa fa-paper-plane"></i> Solicitar al SAT</button>
    </div>
    <div class="sat-ayuda">Recibidos en XML solo incluye CFDI vigentes (regla del SAT). Para detectar cancelaciones solicita también Metadata.</div>
  </form>
</div>

<div class="sat-card">
  <div class="sat-card-head">
    <h4>Solicitudes</h4>
    <div class="sat-acciones sat-form">
      <select class="form-control" id="fEmpresa" style="width:auto"><?= sat_opciones_empresas($con, 0, 'Todas las empresas') ?></select>
      <select class="form-control" id="fEstado" style="width:auto"><option value="">Todos los estados</option><option value="PENDIENTES">En proceso SAT</option><option value="TERMINADAS">Terminadas</option><option value="ERRORES">Con error</option></select>
      <select class="form-control" id="fOrigen" style="width:auto"><option value="">Manual y programadas</option><option value="MANUAL">Manuales</option><option value="PROGRAMADA">Programadas</option></select>
      <button type="button" class="btn btn-default btn-outline-secondary" onclick="fnSolicitudes()"><i class="fa fa-refresh"></i></button>
    </div>
  </div>
  <div class="sat-scroll">
    <table class="sat-tabla">
      <thead><tr><th>#</th><th>Empresa</th><th>Tipo</th><th>Periodo</th><th>Origen</th><th>Estado SAT</th><th class="num">CFDI</th><th class="num">Paquetes</th><th>Procesada</th><th></th></tr></thead>
      <tbody id="tbSolicitudes"><tr><td colspan="10" class="sat-cargando">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="sat-modal" id="mdlDetalle">
  <div class="sat-modal-box lg">
    <div class="sat-modal-head"><h4 id="detTitulo">Solicitud</h4><button type="button" class="sat-x" data-sat-close>&times;</button></div>
    <div class="sat-modal-body" id="detBody"></div>
  </div>
</div>
<?php
sat_page_fin(array('js/descargas.js'));
include('../assets/footer.php');
