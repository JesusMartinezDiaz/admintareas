var PROGS = [];

function fnProgramaciones() {
  SAT.api('getProgramaciones.php', {}, { method: 'GET' }).done(function (r) {
    PROGS = r.programaciones;
    fnEstadoCron(r.cron_ultima, r.programaciones);
    if (!r.programaciones.length) {
      $('#tbProg').html('<tr><td colspan="9" class="sat-vacio">No hay programaciones. Crea una para que las peticiones al SAT se hagan solas.</td></tr>');
      return;
    }
    $('#tbProg').html(r.programaciones.map(function (p, i) {
      var activo = p.activo == 1;
      var tipo = p.tipo_descarga === 'AMBAS' ? 'Emitidos + recibidos' : (p.tipo_descarga === 'EMITIDAS' ? 'Emitidos' : 'Recibidos');
      return '<tr>' +
        '<td><b>' + SAT.esc(p.nombre) + '</b><br><small>' + SAT.num(p.ejecuciones) + ' ejecuciones · ' + SAT.num(p.solicitudes) + ' solicitudes' + (parseInt(p.con_error, 10) ? ' · <span class="text-neg">' + p.con_error + ' con error</span>' : '') + '</small></td>' +
        '<td><b>' + SAT.esc(p.rfc) + '</b><br><small>' + SAT.esc(p.razon_social) + '</small></td>' +
        '<td>' + tipo + '<br><small>' + SAT.esc(p.tipo_archivo) + (p.tipo_comprobante ? ' · tipo ' + SAT.esc(p.tipo_comprobante) : '') + (p.auto_procesar == 1 ? ' · auto' : '') + '</small></td>' +
        '<td>' + SAT.esc(p.descripcion) + '</td>' +
        '<td><small>' + SAT.esc(p.descripcion_periodo) + '</small></td>' +
        '<td>' + (activo ? SAT.fecha(p.proxima_ejecucion) : '-') + '</td>' +
        '<td><small>' + SAT.fecha(p.ultima_ejecucion) + '<br>' + SAT.esc((p.ultimo_resultado || '').substring(0, 160)) + '</small></td>' +
        '<td>' + (activo ? '<span class="sat-badge ok">Activa</span>' : '<span class="sat-badge gris">Pausada</span>') + '</td>' +
        '<td class="sat-acciones" style="justify-content:flex-end;flex-wrap:nowrap">' +
          '<button class="btn btn-xs btn-sm btn-success" title="Ejecutar ahora" onclick="fnProgEjecutar(' + p.id_programacion + ')"><i class="fa fa-play"></i></button>' +
          '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" title="Editar" onclick="fnProgEditar(' + i + ')"><i class="fa fa-pencil"></i></button>' +
          '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" title="Historial" onclick="fnProgHistorial(' + i + ')"><i class="fa fa-history"></i></button>' +
          '<button class="btn btn-xs btn-sm btn-default btn-outline-secondary" title="' + (activo ? 'Pausar' : 'Activar') + '" onclick="fnProgEstado(' + p.id_programacion + ',' + (activo ? 0 : 1) + ')"><i class="fa ' + (activo ? 'fa-pause' : 'fa-check') + '"></i></button>' +
          '<button class="btn btn-xs btn-sm btn-danger" title="Eliminar" onclick="fnProgEliminar(' + p.id_programacion + ')"><i class="fa fa-trash"></i></button>' +
        '</td></tr>';
    }).join(''));
  });
}

function fnEstadoCron(ultima, progs) {
  var activas = progs.filter(function (p) { return p.activo == 1; }).length;
  var html = '';
  if (!ultima) {
    if (activas) html = '<div class="sat-alert sat-alert-danger"><b>El cron no se ha ejecutado nunca.</b> Las programaciones no correrán hasta configurarlo (ver instrucciones abajo).</div>';
  } else {
    var mins = Math.round((Date.now() - new Date(ultima.replace(' ', 'T')).getTime()) / 60000);
    if (mins > 60 && activas) html = '<div class="sat-alert sat-alert-warning"><b>El cron no corre desde hace ' + mins + ' minutos</b> (última vez ' + SAT.fecha(ultima) + '). Revisa la tarea programada del servidor.</div>';
    else html = '<div class="sat-alert sat-alert-success"><i class="fa fa-check"></i> Cron funcionando · última ejecución ' + SAT.fecha(ultima) + ' · ' + activas + ' programaciones activas.</div>';
  }
  $('#boxCron').html(html);
}

function fnProgForm(p) {
  var f = $('#frmProg')[0];
  f.reset();
  $('#pg_id').val(p ? p.id_programacion : '');
  $('#pg_nombre').val(p ? p.nombre : 'Diario emitidos y recibidos');
  $('#pg_empresa').val(p ? p.id_empresa : $('#pg_empresa option:eq(1)').val() || 0);
  $('#pg_tipo').val(p ? p.tipo_descarga : 'AMBAS');
  $('#pg_archivo').val(p ? p.tipo_archivo : 'CFDI');
  $('#pg_tc').val(p && p.tipo_comprobante ? p.tipo_comprobante : '');
  $('#pg_periodo').val(p ? p.periodo : 'DIAS_ATRAS');
  $('#pg_dias_atras').val(p ? p.dias_atras : 3);
  $('#pg_frec').val(p ? p.frecuencia : 'DIARIA');
  $('#pg_horas').val(p ? p.intervalo_horas : 6);
  $('#pg_hora').val(p ? String(p.hora_ejecucion).substring(0, 5) : '06:00');
  $('#pg_dia_mes').val(p ? p.dia_mes : 1);
  var dias = p && p.dias_semana ? String(p.dias_semana).split(',') : ['1', '2', '3', '4', '5'];
  $('#frmProg [name="dias_semana[]"]').each(function () { this.checked = dias.indexOf(this.value) >= 0; });
  $('#pg_auto_desc').prop('checked', p ? p.auto_descargar == 1 : true);
  $('#pg_auto_proc').prop('checked', p ? p.auto_procesar == 1 : true);
  $('#pg_activo').prop('checked', p ? p.activo == 1 : true);
  $('#progTitulo').text(p ? 'Editar programación' : 'Nueva programación');
  fnProgCampos();
  SAT.modal('mdlProg');
}

function fnProgNueva() { fnProgForm(null); }
function fnProgEditar(i) { fnProgForm(PROGS[i]); }

function fnProgCampos() {
  var fr = $('#pg_frec').val();
  $('#wrapHoras').toggle(fr === 'CADA_HORAS');
  $('#wrapDias').toggle(fr === 'SEMANAL');
  $('#wrapDiaMes').toggle(fr === 'MENSUAL');
  $('#lblHora').text(fr === 'CADA_HORAS' ? 'A partir de' : 'Hora');
  $('#wrapDiasAtras').toggle($('#pg_periodo').val() === 'DIAS_ATRAS');
  $('#frmProg .sat-dias label').each(function () { $(this).toggleClass('on', $(this).find('input').is(':checked')); });
  fnProgPreview();
}

var PREVIEW_T = null;
function fnProgPreview() {
  clearTimeout(PREVIEW_T);
  PREVIEW_T = setTimeout(function () {
    if (!parseInt($('#pg_empresa').val(), 10)) { $('#pgPreview').html('<i>Selecciona la empresa para ver la vista previa.</i>'); return; }
    SAT.api('getPreviewProgramacion.php', $('#frmProg').serialize(), { silencioso: true }).done(function (r) {
      $('#pgPreview').html('<b>' + SAT.esc(r.descripcion) + '</b> · ' + SAT.esc(r.periodo) + ' · ' + r.peticiones_por_ejecucion + ' petición(es) al SAT por ejecución.<br>Próximas ejecuciones:<ul>' +
        r.ejecuciones.map(function (x) { return '<li>' + SAT.fecha(x.ejecucion) + ' → pedirá del ' + SAT.fecha(x.desde) + ' al ' + SAT.fecha(x.hasta) + '</li>'; }).join('') + '</ul>');
    }).fail(function (r) { $('#pgPreview').html('<span class="text-neg">' + SAT.esc(r && r.msg) + '</span>'); });
  }, 250);
}

function fnProgGuardar() {
  SAT.api('postGuardarProgramacion.php', $('#frmProg').serialize()).done(function (r) {
    SAT.modal('mdlProg', false); SAT.msg(true, r.msg); fnProgramaciones();
  });
}

function fnProgEstado(id, activo) {
  SAT.api('postEstadoProgramacion.php', { id_programacion: id, activo: activo }).done(function (r) { SAT.msg(true, r.msg); fnProgramaciones(); });
}

function fnProgEliminar(id) {
  SAT.confirmar('Se eliminará la programación. Las solicitudes y CFDI ya descargados se conservan.').done(function () {
    SAT.api('postEliminarProgramacion.php', { id_programacion: id }).done(function (r) { SAT.msg(true, r.msg); fnProgramaciones(); });
  });
}

function fnProgEjecutar(id) {
  SAT.confirmar('Se enviarán ahora las peticiones al SAT con el periodo configurado. La próxima ejecución programada no cambia.').done(function () {
    SAT.api('postEjecutarProgramacion.php', { id_programacion: id }, { loading: 'Enviando peticiones al SAT...' })
      .done(function (r) { SAT.msg(true, r.msg); fnProgramaciones(); })
      .fail(function () { fnProgramaciones(); });
  });
}

function fnProgHistorial(i) {
  var p = PROGS[i];
  $('#histTitulo').text('Historial · ' + p.nombre); $('#histBody').html('<div class="sat-cargando">Cargando...</div>'); SAT.modal('mdlHist');
  SAT.api('getSolicitudes.php', { id_programacion: p.id_programacion }, { method: 'GET' }).done(function (r) {
    $('#histBody').html(r.solicitudes.length ? '<table class="sat-tabla"><thead><tr><th>#</th><th>Creada</th><th>Tipo</th><th>Periodo</th><th>Estado</th><th class="num">CFDI</th><th>Procesada</th></tr></thead><tbody>' +
      r.solicitudes.map(function (s) {
        return '<tr><td>' + s.id_solicitud + '</td><td>' + SAT.fecha(s.fecha_alta) + '</td><td>' + SAT.esc(s.tipo_descarga) + '</td><td><small>' + SAT.fecha(s.fecha_hora_inicial) + ' - ' + SAT.fecha(s.fecha_hora_final) + '</small></td><td>' +
          SAT.badgeEstado(s.estado_solicitud) + '<br><small>' + SAT.esc((s.mensaje_sat || '').substring(0, 100)) + '</small></td><td class="num">' + SAT.num(s.total_cfdi) + '</td><td>' + (s.procesada == 1 ? '<span class="sat-badge ok">' + s.xml_procesados + '</span>' : '-') + '</td></tr>';
      }).join('') + '</tbody></table>' : '<div class="sat-vacio">Esta programación aún no se ha ejecutado.</div>');
  });
}

$(function () {
  $('#frmProg').on('change input', 'select,input', fnProgCampos);
  fnProgramaciones();
});
