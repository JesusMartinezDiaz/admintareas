/* Utilidades comunes del módulo SAT (requiere jQuery, cargado por head.php). */
var SAT = (function ($) {
  'use strict';

  function esc(t) {
    return String(t === null || t === undefined ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function money(n, dec) {
    var v = parseFloat(n || 0);
    if (Math.abs(v) < 0.005) v = 0;
    return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('es-MX', { minimumFractionDigits: dec === undefined ? 2 : dec, maximumFractionDigits: dec === undefined ? 2 : dec });
  }

  function num(n) { return parseFloat(n || 0).toLocaleString('es-MX'); }

  function msg(ok, texto) {
    if (window.Swal) return Swal.fire(ok ? 'Listo' : 'Atención', texto || '', ok ? 'success' : 'error');
    alert(texto);
    return $.Deferred().resolve().promise();
  }

  function info(titulo, html) {
    if (window.Swal) return Swal.fire({ title: titulo, html: html, icon: 'info', width: 700 });
    alert($('<div>').html(html).text());
  }

  function confirmar(texto) {
    var d = $.Deferred();
    if (window.Swal) {
      Swal.fire({ title: '¿Continuar?', text: texto, icon: 'question', showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar' })
        .then(function (r) { if (r.isConfirmed || r.value) d.resolve(); else d.reject(); });
    } else if (confirm(texto)) d.resolve(); else d.reject();
    return d.promise();
  }

  function cargando(on, texto) {
    if (!window.Swal) return;
    if (on) Swal.fire({ title: texto || 'Procesando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
    else Swal.close();
  }

  /** POST/GET a php/*.php que responde {ok,msg,...}. */
  function api(url, data, opts) {
    opts = opts || {};
    var cfg = { url: 'php/' + url, type: opts.method || 'POST', data: data || {}, dataType: 'json', timeout: opts.timeout || 600000 };
    if (data instanceof FormData) { cfg.processData = false; cfg.contentType = false; }
    if (opts.loading) cargando(true, opts.loading);
    var d = $.Deferred();
    $.ajax(cfg).done(function (r) {
      if (opts.loading) cargando(false);
      if (r && r.ok) d.resolve(r); else { if (!opts.silencioso) msg(false, (r && r.msg) || 'Error desconocido'); d.reject(r); }
    }).fail(function (x) {
      if (opts.loading) cargando(false);
      var t = x.responseText || '';
      if (x.status === 0) t = 'Sin conexión con el servidor.';
      else if (t.indexOf('<') === 0) t = 'Respuesta inesperada del servidor (¿expiró la sesión?).';
      if (!opts.silencioso) msg(false, t.substring(0, 500));
      d.reject({ ok: false, msg: t });
    });
    return d.promise();
  }

  function modal(id, abrir) {
    var $m = $('#' + id);
    if (abrir === false) $m.removeClass('open'); else $m.addClass('open');
  }

  $(document).on('click', '.sat-modal [data-sat-close]', function () { $(this).closest('.sat-modal').removeClass('open'); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') $('.sat-modal.open').removeClass('open'); });

  // Subpestañas: <div class="sat-subtabs"><a data-panel="x">..</a></div> + <div class="sat-panel" id="x">
  $(document).on('click', '.sat-subtabs a[data-panel]', function (e) {
    e.preventDefault();
    var $t = $(this), $g = $t.closest('.sat-subtabs');
    $g.find('a').removeClass('active'); $t.addClass('active');
    $('#' + $t.data('panel')).siblings('.sat-panel').removeClass('active');
    $('#' + $t.data('panel')).addClass('active');
    $(document).trigger('sat:panel', [$t.data('panel')]);
  });

  var ESTADOS = {
    SOLICITADA: 'azul', SOLICITADA_REINTENTO: 'azul', ACEPTADA: 'azul', EN_PROCESO: 'warn', TERMINADA: 'ok',
    SIN_INFORMACION: 'gris', DIVIDIDA: 'gris', ERROR: 'err', ERROR_SAT: 'err', ERROR_TEMPORAL_SAT: 'warn', RECHAZADA: 'err', VENCIDA: 'err'
  };
  function badgeEstado(e) { return '<span class="sat-badge ' + (ESTADOS[e] || 'gris') + '">' + esc(e) + '</span>'; }

  function fecha(f) {
    if (!f) return '';
    var p = String(f).split(/[- :]/);
    return p.length >= 3 ? p[2] + '/' + p[1] + '/' + p[0] + (p.length >= 5 ? ' ' + p[3] + ':' + p[4] : '') : f;
  }

  /** Exporta una tabla HTML (o filas) a Excel si SheetJS está disponible; si no, a CSV. */
  function exportar(nombre, filas) {
    if (window.XLSX) {
      var ws = XLSX.utils.aoa_to_sheet(filas);
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Datos');
      XLSX.writeFile(wb, nombre + '.xlsx');
      return;
    }
    var csv = filas.map(function (r) {
      return r.map(function (c) { c = String(c === null || c === undefined ? '' : c); return /[",\n]/.test(c) ? '"' + c.replace(/"/g, '""') + '"' : c; }).join(',');
    }).join('\r\n');
    var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = nombre + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
  }

  return { esc: esc, money: money, num: num, msg: msg, info: info, confirmar: confirmar, cargando: cargando, api: api, modal: modal, badgeEstado: badgeEstado, fecha: fecha, exportar: exportar };
})(jQuery);
