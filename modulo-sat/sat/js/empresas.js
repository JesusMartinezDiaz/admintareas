var EMPRESAS = [];

function fnEmpresas() {
  SAT.api('getEmpresas.php', {}, { method: 'GET' }).done(function (r) {
    EMPRESAS = r.empresas;
    if (!r.empresas.length) { $('#tbEmpresas').html('<tr><td colspan="8" class="sat-vacio">Aún no hay empresas. Da de alta la primera.</td></tr>'); return; }
    $('#tbEmpresas').html(r.empresas.map(function (e, i) {
      var cert = e.id_certificado
        ? '<span class="sat-badge ok">Cargada</span><br><small>No. ' + SAT.esc(e.no_certificado || '') + '</small>'
        : '<span class="sat-badge err">Sin e.firma</span>';
      var vig = '';
      if (e.dias_vigencia !== null) {
        var cls = e.dias_vigencia < 0 ? 'err' : (e.dias_vigencia < 30 ? 'warn' : 'ok');
        vig = '<span class="sat-badge ' + cls + '">' + (e.dias_vigencia < 0 ? 'Vencida' : e.dias_vigencia + ' días') + '</span><br><small>' + SAT.fecha(e.fecha_vencimiento) + '</small>';
      }
      return '<tr>' +
        '<td><b>' + SAT.esc(e.rfc) + '</b></td>' +
        '<td>' + SAT.esc(e.razon_social) + '<br><small>' + SAT.esc(e.tipo_persona || '') + (e.regimen_fiscal ? ' · Régimen ' + SAT.esc(e.regimen_fiscal) : '') + '</small></td>' +
        '<td>' + cert + '</td><td>' + vig + '</td>' +
        '<td class="num">' + SAT.num(e.cfdi) + '</td><td class="num">' + SAT.num(e.programaciones) + '</td>' +
        '<td>' + (parseInt(e.activo, 10) ? '<span class="sat-badge ok">Activa</span>' : '<span class="sat-badge gris">Inactiva</span>') + '</td>' +
        '<td class="sat-acciones" style="justify-content:flex-end">' +
          '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" onclick="fnEmpresaEditar(' + i + ')" title="Editar"><i class="fa fa-pencil fa-pencil-alt"></i></button>' +
          '<button class="btn btn-xs btn-sm btn-primary" onclick="fnCertAbrir(' + i + ')"><i class="fa fa-key"></i> e.firma</button>' +
          (e.id_certificado ? '<button class="btn btn-xs btn-sm btn-success" onclick="fnProbar(' + e.id_empresa + ')"><i class="fa fa-plug"></i> Probar</button>' : '') +
          '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" onclick="fnEmpresaEstado(' + e.id_empresa + ',' + (parseInt(e.activo, 10) ? 0 : 1) + ')">' + (parseInt(e.activo, 10) ? 'Desactivar' : 'Activar') + '</button>' +
        '</td></tr>';
    }).join(''));
  });
}

function fnEmpresaNueva() {
  $('#frmEmpresa')[0].reset(); $('#emp_id').val('');
  $('#mdlEmpresaTitulo').text('Nueva empresa'); SAT.modal('mdlEmpresa');
}

function fnEmpresaEditar(i) {
  var e = EMPRESAS[i];
  $('#emp_id').val(e.id_empresa); $('#emp_razon').val(e.razon_social); $('#emp_rfc').val(e.rfc);
  $('#emp_regimen').val(e.regimen_fiscal); $('#emp_email').val(e.email_contacto); $('#emp_tel').val(e.telefono);
  $('#mdlEmpresaTitulo').text('Editar ' + e.rfc); SAT.modal('mdlEmpresa');
}

function fnEmpresaGuardar() {
  SAT.api('postGuardarEmpresa.php', $('#frmEmpresa').serialize()).done(function (r) {
    SAT.modal('mdlEmpresa', false); SAT.msg(true, r.msg); fnEmpresas();
  });
}

function fnEmpresaEstado(id, activo) {
  SAT.confirmar(activo ? '¿Activar la empresa?' : 'Se pausarán también sus programaciones.').done(function () {
    SAT.api('postEstadoEmpresa.php', { id_empresa: id, activo: activo }).done(function (r) { SAT.msg(true, r.msg); fnEmpresas(); });
  });
}

function fnCertAbrir(i) {
  $('#frmCert')[0].reset(); $('#cert_id').val(EMPRESAS[i].id_empresa); $('#certRfc').text(EMPRESAS[i].rfc);
  SAT.modal('mdlCert');
}

function fnCertGuardar() {
  SAT.api('postGuardarCertificado.php', new FormData($('#frmCert')[0]), { loading: 'Validando e.firma...' }).done(function (r) {
    SAT.modal('mdlCert', false); SAT.msg(true, r.msg); fnEmpresas();
  });
}

function fnProbar(id) {
  SAT.api('postProbarConexion.php', { id_empresa: id }, { loading: 'Autenticando con el SAT...' }).done(function (r) { SAT.msg(true, r.msg); });
}

$(fnEmpresas);
