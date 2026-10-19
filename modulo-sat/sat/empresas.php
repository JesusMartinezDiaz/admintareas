<?php
include('../assets/head.php');
require_once __DIR__ . '/lib/page.php';
sat_page_inicio('empresas', 'Empresas y e.firma');
?>
<div class="sat-card">
  <div class="sat-card-head">
    <div>
      <h4>Empresas</h4>
      <div class="sat-sub">Cada empresa necesita su e.firma (FIEL) vigente: archivo .cer, archivo .key y contraseña. No uses el CSD (sello digital).</div>
    </div>
    <div class="sat-acciones"><button type="button" class="btn btn-primary" onclick="fnEmpresaNueva()"><i class="fa fa-plus"></i> Nueva empresa</button></div>
  </div>
  <div class="sat-scroll">
    <table class="sat-tabla">
      <thead><tr><th>RFC</th><th>Razón social</th><th>e.firma</th><th>Vigencia</th><th class="num">CFDI</th><th class="num">Programaciones</th><th>Estado</th><th></th></tr></thead>
      <tbody id="tbEmpresas"><tr><td colspan="8" class="sat-cargando">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Modal empresa -->
<div class="sat-modal" id="mdlEmpresa">
  <div class="sat-modal-box">
    <div class="sat-modal-head"><h4 id="mdlEmpresaTitulo">Empresa</h4><button type="button" class="sat-x" data-sat-close>&times;</button></div>
    <form id="frmEmpresa" class="sat-form" onsubmit="return false;">
      <div class="sat-modal-body">
        <input type="hidden" name="id_empresa" id="emp_id">
        <div class="row">
          <div class="col-sm-8 sat-campo"><label>Razón social *</label><input class="form-control" name="razon_social" id="emp_razon" required></div>
          <div class="col-sm-4 sat-campo"><label>RFC *</label><input class="form-control" name="rfc" id="emp_rfc" maxlength="13" style="text-transform:uppercase" required></div>
          <div class="col-sm-4 sat-campo"><label>Régimen fiscal</label><input class="form-control" name="regimen_fiscal" id="emp_regimen" placeholder="601, 612, 626..."></div>
          <div class="col-sm-4 sat-campo"><label>Email contacto</label><input class="form-control" type="email" name="email_contacto" id="emp_email"></div>
          <div class="col-sm-4 sat-campo"><label>Teléfono</label><input class="form-control" name="telefono" id="emp_tel"></div>
        </div>
      </div>
      <div class="sat-modal-foot"><button type="button" class="btn btn-default btn-secondary" data-sat-close>Cancelar</button><button type="button" class="btn btn-primary" onclick="fnEmpresaGuardar()"><i class="fa fa-save"></i> Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal e.firma -->
<div class="sat-modal" id="mdlCert">
  <div class="sat-modal-box">
    <div class="sat-modal-head"><h4>Cargar e.firma · <span id="certRfc"></span></h4><button type="button" class="sat-x" data-sat-close>&times;</button></div>
    <form id="frmCert" class="sat-form" onsubmit="return false;" enctype="multipart/form-data">
      <div class="sat-modal-body">
        <input type="hidden" name="id_empresa" id="cert_id">
        <div class="sat-alert sat-alert-info">Se valida que la contraseña abra la llave, que el .key corresponda al .cer, que el RFC sea el de la empresa y que esté vigente. La contraseña se guarda cifrada.</div>
        <div class="row">
          <div class="col-sm-6 sat-campo"><label>Archivo .cer *</label><input class="form-control" type="file" name="archivo_cer" accept=".cer" required></div>
          <div class="col-sm-6 sat-campo"><label>Archivo .key *</label><input class="form-control" type="file" name="archivo_key" accept=".key" required></div>
          <div class="col-sm-6 sat-campo"><label>Contraseña de la llave privada *</label><input class="form-control" type="password" name="password_key" autocomplete="new-password" required></div>
        </div>
      </div>
      <div class="sat-modal-foot"><button type="button" class="btn btn-default btn-secondary" data-sat-close>Cancelar</button><button type="button" class="btn btn-primary" onclick="fnCertGuardar()"><i class="fa fa-upload"></i> Validar y guardar</button></div>
    </form>
  </div>
</div>
<?php
sat_page_fin(array('js/empresas.js'));
include('../assets/footer.php');
