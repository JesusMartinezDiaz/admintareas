var PENDIENTES = [];
var TIMER = null;

function fnSolicitar() {
  var $f = $('#frmSolicitud');
  if (!parseInt($f.find('[name=id_empresa]').val(), 10)) { SAT.msg(false, 'Selecciona la empresa.'); return; }
  SAT.api('postSolicitarDescarga.php', $f.serialize(), { loading: 'Enviando solicitud al SAT...' }).done(function (r) {
    SAT.msg(true, r.msg); fnSolicitudes();
  }).fail(function () { fnSolicitudes(); });
}

function fnSolicitudes() {
  SAT.api('getSolicitudes.php', { id_empresa: $('#fEmpresa').val(), estado: $('#fEstado').val(), origen: $('#fOrigen').val() }, { method: 'GET', silencioso: true }).done(function (r) {
    PENDIENTES = r.pendientes;
    if (!r.solicitudes.length) { $('#tbSolicitudes').html('<tr><td colspan="10" class="sat-vacio">Sin solicitudes.</td></tr>'); return; }
    var hayPend = false;
    $('#tbSolicitudes').html(r.solicitudes.map(function (s) {
      var pend = PENDIENTES.indexOf(s.estado_solicitud) >= 0;
      if (pend) hayPend = true;
      var tp = parseInt(s.total_paquetes, 10), pd = parseInt(s.paquetes_descargados, 10);
      var acciones = '';
      if (pend || (s.estado_solicitud === 'TERMINADA' && (pd < tp || (pd > 0 && s.procesada != 1)))) {
        acciones += '<button class="btn btn-xs btn-sm btn-primary" onclick="fnAccion(' + s.id_solicitud + ',\'actualizar\')" title="Verifica, descarga y procesa lo que falte"><i class="fa fa-play"></i> Actualizar</button>';
      }
      if (pd > 0 && s.estado_solicitud === 'TERMINADA') acciones += '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" onclick="fnAccion(' + s.id_solicitud + ',\'procesar\')" title="Volver a leer los XML"><i class="fa fa-cogs"></i></button>';
      acciones += '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" onclick="fnDetalle(' + s.id_solicitud + ')" title="Detalle"><i class="fa fa-info-circle"></i></button>';
      return '<tr>' +
        '<td>' + s.id_solicitud + '</td>' +
        '<td><b>' + SAT.esc(s.rfc) + '</b><br><small>' + SAT.esc(s.razon_social) + '</small></td>' +
        '<td>' + SAT.esc(s.tipo_descarga) + '<br><small>' + SAT.esc(s.tipo_archivo) + (s.tipo_comprobante ? ' · ' + SAT.esc(s.tipo_comprobante) : '') + '</small></td>' +
        '<td><small>' + SAT.fecha(s.fecha_hora_inicial || s.fecha_inicial) + '<br>' + SAT.fecha(s.fecha_hora_final || s.fecha_final) + '</small></td>' +
        '<td>' + (s.origen === 'PROGRAMADA' ? '<span class="sat-badge azul" title="' + SAT.esc(s.programacion || '') + '"><i class="fa fa-clock-o"></i> Programada</span>' : '<span class="sat-badge gris">Manual</span>') + '<br><small>' + SAT.fecha(s.fecha_alta) + '</small></td>' +
        '<td>' + SAT.badgeEstado(s.estado_solicitud) + '<br><small>' + SAT.esc((s.mensaje_sat || '').substring(0, 120)) + '</small></td>' +
        '<td class="num">' + SAT.num(s.total_cfdi) + '</td>' +
        '<td class="num">' + pd + '/' + tp + '</td>' +
        '<td>' + (s.procesada == 1 ? '<span class="sat-badge ok">' + SAT.num(s.xml_procesados) + '</span>' : (s.auto_procesar == 1 ? '<small>Automático</small>' : '<small>Manual</small>')) + '</td>' +
        '<td class="sat-acciones" style="justify-content:flex-end;flex-wrap:nowrap">' + acciones + '</td></tr>';
    }).join(''));
    clearTimeout(TIMER);
    if (hayPend) TIMER = setTimeout(fnSolicitudes, 60000);
  });
}

function fnAccion(id, accion) {
  SAT.api('postAccionSolicitud.php', { id_solicitud: id, accion: accion }, { loading: 'Consultando al SAT...' })
    .done(function (r) { SAT.msg(true, r.msg); fnSolicitudes(); })
    .fail(function () { fnSolicitudes(); });
}

function fnDetalle(id) {
  $('#detTitulo').text('Solicitud #' + id); $('#detBody').html('<div class="sat-cargando">Cargando...</div>'); SAT.modal('mdlDetalle');
  SAT.api('getSolicitudDetalle.php', { id_solicitud: id }, { method: 'GET' }).done(function (r) {
    var s = r.solicitud, c = r.cfdi || {};
    var h = '<div class="row"><div class="col-sm-6"><b>Request ID SAT:</b> <span class="sat-uuid">' + SAT.esc(s.request_id || '-') + '</span><br><b>Estado:</b> ' + SAT.badgeEstado(s.estado_solicitud) + ' ' + SAT.esc(s.codigo_estado || '') +
      '<br><b>Mensaje:</b> ' + SAT.esc(s.mensaje_sat || '') + '</div><div class="col-sm-6"><b>Verificaciones:</b> ' + s.verificaciones + ' · <b>Reintentos:</b> ' + s.reintentos +
      '<br><b>Última verificación:</b> ' + SAT.fecha(s.fecha_ultima_verificacion) + '<br><b>CFDI guardados:</b> ' + SAT.num(c.n) + ' (I ' + (c.i || 0) + ', E ' + (c.e || 0) + ', P ' + (c.p || 0) + ', N ' + (c.nn || 0) + ')</div></div><hr>';
    h += '<h5>Paquetes</h5><table class="sat-tabla"><thead><tr><th>Paquete</th><th>Descargado</th><th class="num">Intentos</th><th>Error</th></tr></thead><tbody>' +
      (r.paquetes.length ? r.paquetes.map(function (p) {
        return '<tr><td class="sat-uuid">' + SAT.esc(p.id_paquete_sat) + '</td><td>' + (p.descargado == 1 ? '<span class="sat-badge ok">' + SAT.fecha(p.fecha_descarga) + '</span>' : '<span class="sat-badge warn">Pendiente</span>') + '</td><td class="num">' + p.intentos + '</td><td><small>' + SAT.esc(p.ultimo_error || '') + '</small></td></tr>';
      }).join('') : '<tr><td colspan="4" class="sat-vacio">Sin paquetes todavía.</td></tr>') + '</tbody></table>';
    h += '<h5 style="margin-top:14px">Bitácora</h5><table class="sat-tabla"><thead><tr><th>Fecha</th><th>Proceso</th><th>Estatus</th><th>Mensaje</th></tr></thead><tbody>' +
      r.log.map(function (l) {
        return '<tr><td><small>' + SAT.fecha(l.fecha) + '</small></td><td>' + SAT.esc(l.proceso) + '</td><td><span class="sat-badge ' + (l.estatus === 'OK' ? 'ok' : 'err') + '">' + SAT.esc(l.estatus) + '</span></td><td><small>' + SAT.esc(l.mensaje) + '</small></td></tr>';
      }).join('') + '</tbody></table>';
    $('#detBody').html(h);
  });
}

$(function () {
  $('#fEmpresa,#fEstado,#fOrigen').on('change', fnSolicitudes);
  fnSolicitudes();
});
