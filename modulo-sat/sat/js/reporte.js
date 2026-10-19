var REP = null;
var CHART = null;
var DETALLE = [];
var M = SAT.money;
var TIPOS = { I: 'Ingreso', E: 'Egreso / NC', P: 'Pago', N: 'Nómina', T: 'Traslado' };

function fnParams() {
  return { id_empresa: $('#rEmpresa').val(), fecha_inicial: $('#rDesde').val(), fecha_final: $('#rHasta').val() };
}

function fnReporte() {
  var p = fnParams();
  if (!parseInt(p.id_empresa, 10)) { SAT.msg(false, 'Selecciona la empresa.'); return; }
  p.tendencia = 1;
  $('#rContenido').html('<div class="sat-card sat-cargando"><i class="fa fa-spinner fa-spin"></i> Calculando...</div>');
  SAT.api('getReporteFiscal.php', p, { method: 'GET' }).done(function (r) {
    REP = r.reporte;
    fnPintar(REP);
  }).fail(function () { $('#rContenido').html(''); });
}

function kpi(t, v, d, cls) {
  return '<div class="sat-kpi ' + (cls || '') + '"><div class="t">' + t + '</div><div class="v">' + v + '</div><div class="d">' + (d || '') + '</div></div>';
}

function fnPintar(r) {
  var iva = r.iva, res = iva.resultado, isr = r.isr;
  var aCargo = res.iva_a_cargo > 0;
  var h = '';
  h += '<div class="sat-card"><b>' + SAT.esc(r.empresa.rfc) + '</b> · ' + SAT.esc(r.empresa.razon_social) + ' · Periodo ' + SAT.fecha(r.periodo.inicio) + ' al ' + SAT.fecha(r.periodo.fin) + '</div>';
  h += '<div class="sat-kpis">' +
    kpi(aCargo ? 'IVA a cargo (estimado)' : 'IVA a favor (estimado)', M(aCargo ? res.iva_a_cargo : res.iva_a_favor), 'Flujo de efectivo', aCargo ? 'rojo' : 'verde') +
    kpi('IVA trasladado cobrado', M(res.iva_trasladado_cobrado), 'Devengado: ' + M(res.devengado_trasladado)) +
    kpi('IVA acreditable pagado', M(res.iva_acreditable_pagado), 'Devengado: ' + M(res.devengado_acreditable), 'morado') +
    kpi('Ingresos cobrados', M(isr.ingresos_cobrados), 'Facturado: ' + M(isr.ingresos_facturados)) +
    kpi('Deducciones pagadas', M(isr.deducciones_pagadas), 'Facturado: ' + M(isr.gastos_facturados), 'morado') +
    kpi('Utilidad (flujo)', M(isr.utilidad_flujo), 'Devengada: ' + M(isr.utilidad_devengada), isr.utilidad_flujo >= 0 ? 'verde' : 'rojo') +
    '</div>';
  if (r.alertas.length) {
    h += r.alertas.map(function (a) { return '<div class="sat-alert sat-alert-' + a.nivel + '"><i class="fa fa-exclamation-circle"></i> ' + SAT.esc(a.texto) + '</div>'; }).join('');
  }
  h += '<div class="sat-subtabs sat-no-print">' +
    '<a data-panel="pIva" class="active">IVA</a><a data-panel="pIsr">ISR</a><a data-panel="pTipos">Por tipo de CFDI</a>' +
    '<a data-panel="pPpd">PPD pendientes (' + r.ppd_pendientes.length + ')</a><a data-panel="pTop">Clientes / proveedores</a>' +
    '<a data-panel="pTend">Tendencia 12 meses</a><a data-panel="pDet">Detalle CFDI</a></div>';
  h += '<div>' + panelIva(iva) + panelIsr(r) + panelTipos(r) + panelPpd(r) + panelTop(r) + panelTend(r) + panelDetalle() + '</div>';
  $('#rContenido').html(h);
  fnGrafica(r.tendencia || []);
}

function filaIva(nombre, x, signo) {
  var f = signo === '-' ? -1 : 1;
  return '<tr><td>' + nombre + '</td><td class="num">' + SAT.num(x.n) + '</td><td class="num">' + M(f * x.base) + '</td><td class="num">' + M(f * x.iva) + '</td><td class="num">' + M(f * x.iva_ret) + '</td><td class="num">' + M(f * x.isr_ret) + '</td></tr>';
}

function tablaIva(titulo, t, quien) {
  var ppd = $.extend({}, t.ppd, { base: t.ppd.base_iva });
  return '<div class="sat-card"><h5>' + titulo + '</h5><div class="sat-scroll"><table class="sat-tabla"><thead><tr><th>Concepto</th><th class="num">CFDI</th><th class="num">Base</th><th class="num">IVA</th><th class="num">IVA retenido</th><th class="num">ISR retenido</th></tr></thead><tbody>' +
    filaIva('Facturas PUE del periodo', t.pue) +
    filaIva('(+) ' + quien + ' de facturas PPD (complementos de pago)' + (t.ppd.estimados > 0 ? ' <small>*' + t.ppd.estimados + ' prorrateados (pagos 1.0)</small>' : ''), ppd) +
    filaIva('(−) Notas de crédito', t.notas_credito, '-') +
    '<tr class="total"><td>Neto del periodo</td><td></td><td class="num">' + M(t.base_16 + t.base_8 + t.base_0 + t.base_exento + t.base_ppd) + '</td><td class="num">' + M(t.iva_neto) + '</td><td class="num">' + M(t.iva_retenido) + '</td><td class="num">' + M(t.isr_retenido) + '</td></tr>' +
    '</tbody></table></div>' +
    '<div class="sat-ayuda">Bases PUE − NC por tasa: 16% ' + M(t.base_16) + ' · 8% ' + M(t.base_8) + ' · 0% ' + M(t.base_0) + ' · Exento ' + M(t.base_exento) + ' · Base IVA de pagos PPD ' + M(t.base_ppd) + '</div></div>';
}

function panelIva(iva) {
  var r = iva.resultado;
  var neto = r.iva_a_cargo > 0 ? r.iva_a_cargo : -r.iva_a_favor;
  return '<div class="sat-panel active" id="pIva">' +
    '<div class="row"><div class="col-sm-6">' + tablaIva('IVA trasladado (CFDI emitidos)', iva.trasladado, 'Cobros') + '</div>' +
    '<div class="col-sm-6">' + tablaIva('IVA acreditable (CFDI recibidos)', iva.acreditable, 'Pagos') + '</div></div>' +
    '<div class="sat-card"><h5>Determinación estimada del IVA del periodo</h5><table class="sat-tabla" style="max-width:640px"><tbody>' +
    '<tr><td>IVA trasladado efectivamente cobrado</td><td class="num">' + M(r.iva_trasladado_cobrado) + '</td></tr>' +
    '<tr><td>(−) IVA que nos retuvieron los clientes</td><td class="num">' + M(-r.iva_retenido_por_clientes) + '</td></tr>' +
    '<tr><td>(−) IVA acreditable efectivamente pagado</td><td class="num">' + M(-r.iva_acreditable_pagado) + '</td></tr>' +
    '<tr class="total"><td>' + (neto >= 0 ? 'IVA a cargo' : 'IVA a favor') + '</td><td class="num ' + (neto >= 0 ? 'text-neg' : 'text-pos') + '">' + M(Math.abs(neto)) + '</td></tr>' +
    '<tr><td>IVA retenido a proveedores (se entera por separado)</td><td class="num">' + M(r.iva_retenido_a_enterar) + '</td></tr>' +
    '</tbody></table><div class="sat-ayuda">No incluye saldos a favor de periodos anteriores, proporción de IVA acreditable por actos exentos ni ajustes manuales. Validar con el contador.</div></div></div>';
}

function panelIsr(r) {
  var x = r.isr, n = r.nomina;
  return '<div class="sat-panel" id="pIsr"><div class="row"><div class="col-sm-6"><div class="sat-card"><h5>Flujo de efectivo (personas físicas / RESICO)</h5><table class="sat-tabla"><tbody>' +
    '<tr><td>Ingresos cobrados (sin IVA)</td><td class="num">' + M(x.ingresos_cobrados) + '</td></tr>' +
    '<tr><td>(−) Deducciones pagadas (sin IVA)</td><td class="num">' + M(-x.deducciones_pagadas) + '</td></tr>' +
    '<tr class="total"><td>Base estimada</td><td class="num">' + M(x.utilidad_flujo) + '</td></tr>' +
    '<tr><td>ISR que nos retuvieron (acreditable)</td><td class="num">' + M(x.isr_retenido_por_clientes) + '</td></tr>' +
    '<tr><td>ISR retenido a terceros (por enterar)</td><td class="num">' + M(x.isr_retenido_a_enterar) + '</td></tr>' +
    '</tbody></table></div></div><div class="col-sm-6"><div class="sat-card"><h5>Devengado (lo facturado, personas morales)</h5><table class="sat-tabla"><tbody>' +
    '<tr><td>Ingresos facturados − notas de crédito</td><td class="num">' + M(x.ingresos_facturados) + '</td></tr>' +
    '<tr><td>(−) Gastos facturados − notas de crédito</td><td class="num">' + M(-x.gastos_facturados) + '</td></tr>' +
    '<tr class="total"><td>Diferencia</td><td class="num">' + M(x.utilidad_devengada) + '</td></tr>' +
    '</tbody></table><div class="sat-ayuda">Personas morales: el pago provisional se calcula con ingresos nominales × coeficiente de utilidad.</div></div>' +
    '<div class="sat-card"><h5>Nómina emitida</h5><table class="sat-tabla"><tbody><tr><td>Recibos</td><td class="num">' + SAT.num(n.n) + '</td></tr><tr><td>Percepciones</td><td class="num">' + M(n.percepciones) + '</td></tr><tr><td>Deducciones (incluye ISR retenido)</td><td class="num">' + M(n.deducciones) + '</td></tr><tr><td>Neto pagado</td><td class="num">' + M(n.neto) + '</td></tr></tbody></table></div>' +
    '</div></div></div>';
}

function panelTipos(r) {
  return '<div class="sat-panel" id="pTipos"><div class="sat-card"><div class="sat-scroll"><table class="sat-tabla"><thead><tr><th>Relación</th><th>Tipo</th><th class="num">Vigentes</th><th class="num">Cancelados</th><th class="num">Base</th><th class="num">IVA</th><th class="num">Retenciones</th><th class="num">Total (MXN)</th></tr></thead><tbody>' +
    (r.resumen_tipos.length ? r.resumen_tipos.map(function (t) {
      return '<tr><td>' + (t.rel === 'EMITIDA' ? '<span class="sat-badge azul">Emitidos</span>' : '<span class="sat-badge gris">Recibidos</span>') + '</td><td>' + SAT.esc(t.tipo + ' - ' + (TIPOS[t.tipo] || '')) + '</td><td class="num">' + SAT.num(t.vigentes) + '</td><td class="num">' + SAT.num(t.cancelados) + '</td><td class="num">' + M(t.base) + '</td><td class="num">' + M(t.iva) + '</td><td class="num">' + M(t.retenciones) + '</td><td class="num">' + M(t.total) + '</td></tr>';
    }).join('') : '<tr><td colspan="8" class="sat-vacio">Sin CFDI en el periodo.</td></tr>') +
    '</tbody></table></div><div class="sat-ayuda">Pagos (P): el total es el monto pagado del complemento.</div></div></div>';
}

function panelPpd(r) {
  var tot = { EMITIDA: 0, RECIBIDA: 0 };
  r.ppd_pendientes.forEach(function (x) { tot[x.rel] += parseFloat(x.saldo_mxn); });
  return '<div class="sat-panel" id="pPpd"><div class="sat-kpis">' + kpi('Por cobrar (PPD emitidas)', M(tot.EMITIDA), 'Saldo según complementos de pago') + kpi('Por pagar (PPD recibidas)', M(tot.RECIBIDA), 'Saldo según complementos de pago', 'morado') + '</div>' +
    '<div class="sat-card"><div class="sat-scroll"><table class="sat-tabla"><thead><tr><th>Relación</th><th>Factura</th><th>Fecha</th><th>RFC / Nombre</th><th class="num">Total</th><th class="num">Pagado</th><th class="num">Saldo</th><th class="num">Saldo MXN</th><th class="num">Días</th><th></th></tr></thead><tbody>' +
    (r.ppd_pendientes.length ? r.ppd_pendientes.map(function (x) {
      return '<tr><td>' + (x.rel === 'EMITIDA' ? 'Por cobrar' : 'Por pagar') + '</td><td>' + SAT.esc((x.serie || '') + (x.folio || '')) + '<br><span class="sat-uuid">' + SAT.esc(x.uuid) + '</span></td><td>' + SAT.fecha(x.fecha_emision).substring(0, 10) +
        '</td><td><b>' + SAT.esc(x.rfc) + '</b><br><small>' + SAT.esc(x.nombre) + '</small></td><td class="num">' + M(x.total) + ' ' + SAT.esc(x.moneda) + '</td><td class="num">' + M(x.pagado) + '<br><small>' + x.parcialidades + ' pagos</small></td><td class="num">' + M(x.saldo) + '</td><td class="num"><b>' + M(x.saldo_mxn) + '</b></td><td class="num">' + x.dias + '</td>' +
        '<td><button class="btn btn-xs btn-sm btn-default btn-outline-secondary" onclick="fnCfdi(' + x.id_cfdi + ')"><i class="fa fa-search"></i></button></td></tr>';
    }).join('') : '<tr><td colspan="10" class="sat-vacio">No hay facturas PPD con saldo al cierre del periodo.</td></tr>') + '</tbody></table></div></div></div>';
}

function tablaTop(titulo, rows) {
  return '<div class="sat-card"><h5>' + titulo + '</h5><table class="sat-tabla"><thead><tr><th>RFC / Nombre</th><th class="num">CFDI</th><th class="num">Base</th><th class="num">IVA</th><th class="num">Total</th></tr></thead><tbody>' +
    (rows.length ? rows.map(function (x) { return '<tr><td><b>' + SAT.esc(x.rfc) + '</b><br><small>' + SAT.esc(x.nombre) + '</small></td><td class="num">' + x.n + '</td><td class="num">' + M(x.base) + '</td><td class="num">' + M(x.iva) + '</td><td class="num">' + M(x.total) + '</td></tr>'; }).join('') : '<tr><td colspan="5" class="sat-vacio">Sin datos.</td></tr>') +
    '</tbody></table></div>';
}

function panelTop(r) {
  return '<div class="sat-panel" id="pTop"><div class="row"><div class="col-sm-6">' + tablaTop('Top 10 clientes (facturado − NC)', r.top_clientes) + '</div><div class="col-sm-6">' + tablaTop('Top 10 proveedores (facturado − NC)', r.top_proveedores) + '</div></div></div>';
}

function panelTend(r) {
  var t = r.tendencia || [];
  return '<div class="sat-panel" id="pTend"><div class="sat-card"><div style="height:300px"><canvas id="chTend"></canvas></div></div><div class="sat-card"><div class="sat-scroll"><table class="sat-tabla"><thead><tr><th>Mes</th><th class="num">Ingresos cobrados</th><th class="num">Deducciones pagadas</th><th class="num">IVA trasladado</th><th class="num">IVA acreditable</th><th class="num">IVA neto</th></tr></thead><tbody>' +
    t.map(function (x) { return '<tr><td>' + x.mes + '</td><td class="num">' + M(x.ingresos) + '</td><td class="num">' + M(x.gastos) + '</td><td class="num">' + M(x.iva_trasladado) + '</td><td class="num">' + M(x.iva_acreditable) + '</td><td class="num ' + (x.iva_neto > 0 ? 'text-neg' : 'text-pos') + '">' + M(x.iva_neto) + '</td></tr>'; }).join('') +
    '</tbody></table></div></div></div>';
}

function fnGrafica(t) {
  if (!window.Chart || !document.getElementById('chTend')) return;
  if (CHART) CHART.destroy();
  CHART = new Chart(document.getElementById('chTend'), {
    type: 'bar',
    data: {
      labels: t.map(function (x) { return x.mes; }),
      datasets: [
        { label: 'Ingresos cobrados', data: t.map(function (x) { return x.ingresos; }), backgroundColor: '#3b82f6' },
        { label: 'Deducciones pagadas', data: t.map(function (x) { return x.gastos; }), backgroundColor: '#a78bfa' },
        { label: 'IVA neto', data: t.map(function (x) { return x.iva_neto; }), type: 'line', borderColor: '#dc2626', backgroundColor: '#dc2626', yAxisID: 'y' }
      ]
    },
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
  });
}

function panelDetalle() {
  return '<div class="sat-panel" id="pDet"><div class="sat-card sat-form"><div class="row">' +
    '<div class="col-sm-2 sat-campo"><label>Relación</label><select class="form-control" id="dRel"><option value="">Todos</option><option value="EMITIDA">Emitidos</option><option value="RECIBIDA">Recibidos</option></select></div>' +
    '<div class="col-sm-2 sat-campo"><label>Tipo</label><select class="form-control" id="dTipo"><option value="">Todos</option>' + Object.keys(TIPOS).map(function (k) { return '<option value="' + k + '">' + k + ' - ' + TIPOS[k] + '</option>'; }).join('') + '</select></div>' +
    '<div class="col-sm-2 sat-campo"><label>Método</label><select class="form-control" id="dMetodo"><option value="">Todos</option><option>PUE</option><option>PPD</option></select></div>' +
    '<div class="col-sm-2 sat-campo"><label>Estatus</label><select class="form-control" id="dEstatus"><option value="">Todos</option><option value="VIGENTE">Vigentes</option><option value="CANCELADO">Cancelados</option></select></div>' +
    '<div class="col-sm-2 sat-campo"><label>Buscar</label><input class="form-control" id="dBuscar" placeholder="RFC, nombre, UUID, folio"></div>' +
    '<div class="col-sm-2 sat-campo"><label>&nbsp;</label><div class="sat-acciones"><button class="btn btn-primary" onclick="fnDetalle()"><i class="fa fa-search"></i></button><button class="btn btn-default btn-outline-secondary" onclick="fnExportarDetalle()" title="Exportar a Excel"><i class="fa fa-file-excel-o"></i></button></div></div>' +
    '</div><div class="sat-scroll"><table class="sat-tabla"><thead><tr><th>Fecha</th><th>Rel.</th><th>Tipo</th><th>Folio / UUID</th><th>Emisor</th><th>Receptor</th><th>Mét.</th><th class="num">Subtotal</th><th class="num">IVA</th><th class="num">Ret.</th><th class="num">Total</th><th>Estatus</th><th></th></tr></thead><tbody id="tbDetalle"><tr><td colspan="13" class="sat-vacio">Presiona buscar.</td></tr></tbody></table></div></div></div>';
}

function fnFiltrosDetalle() {
  return $.extend(fnParams(), { rel: $('#dRel').val(), tipo: $('#dTipo').val(), metodo: $('#dMetodo').val(), estatus: $('#dEstatus').val(), buscar: $('#dBuscar').val() });
}

function fnDetalle() {
  $('#tbDetalle').html('<tr><td colspan="13" class="sat-cargando">Cargando...</td></tr>');
  SAT.api('getDetalleCfdi.php', fnFiltrosDetalle(), { method: 'GET' }).done(function (r) {
    DETALLE = r.cfdi;
    if (!r.cfdi.length) { $('#tbDetalle').html('<tr><td colspan="13" class="sat-vacio">Sin resultados.</td></tr>'); return; }
    $('#tbDetalle').html(r.cfdi.map(function (c) {
      var canc = String(c.estatus_sat || '').toUpperCase().indexOf('CANCEL') === 0;
      var mon = c.moneda && c.moneda !== 'MXN' && c.moneda !== 'XXX' ? ' <small>' + SAT.esc(c.moneda) + '</small>' : '';
      var total = c.tipo === 'P' ? M(c.total_pagos) + ' <small>pagado</small>' : M(c.total) + mon;
      return '<tr class="' + (canc ? 'cancelado' : '') + '"><td><small>' + SAT.fecha(c.fecha_emision) + '</small></td><td>' + (c.rel === 'EMITIDA' ? 'Emit.' : 'Rec.') + '</td><td>' + SAT.esc(c.tipo) + '</td>' +
        '<td>' + SAT.esc((c.serie || '') + (c.folio || '')) + '<br><span class="sat-uuid">' + SAT.esc(c.uuid) + '</span></td>' +
        '<td><b>' + SAT.esc(c.rfc_emisor) + '</b><br><small>' + SAT.esc(c.nombre_emisor) + '</small></td><td><b>' + SAT.esc(c.rfc_receptor) + '</b><br><small>' + SAT.esc(c.nombre_receptor) + '</small></td>' +
        '<td>' + SAT.esc(c.metodo_pago || '') + '</td><td class="num">' + M(c.subtotal) + '</td><td class="num">' + M(c.iva) + '</td><td class="num">' + M(parseFloat(c.iva_retenido) + parseFloat(c.isr_retenido)) + '</td><td class="num">' + total + '</td>' +
        '<td>' + (canc ? '<span class="sat-badge err">Cancelado</span>' : (c.origen_dato === 'METADATA' ? '<span class="sat-badge warn" title="Solo metadata, falta XML">Sin XML</span>' : '<span class="sat-badge ok">Vigente</span>')) + '</td>' +
        '<td class="sat-acciones" style="flex-wrap:nowrap"><button class="btn btn-xs btn-sm btn-default btn-outline-secondary" onclick="fnCfdi(' + c.id_cfdi + ')" title="Pagos y relacionados"><i class="fa fa-search"></i></button>' +
        (c.origen_dato !== 'METADATA' ? '<a class="btn btn-xs btn-sm btn-default btn-outline-secondary" href="php/getXml.php?id_cfdi=' + c.id_cfdi + '" title="Descargar XML"><i class="fa fa-download"></i></a>' : '') + '</td></tr>';
    }).join('') + (r.cfdi.length >= r.limite ? '<tr><td colspan="13" class="sat-vacio">Se muestran los primeros ' + r.limite + '. Usa los filtros o exporta para ver todos.</td></tr>' : ''));
  });
}

function filasDetalle(rows) {
  var f = [['Fecha', 'Relación', 'Tipo', 'Serie', 'Folio', 'UUID', 'RFC emisor', 'Emisor', 'RFC receptor', 'Receptor', 'Método', 'Forma', 'Uso CFDI', 'Moneda', 'Tipo cambio', 'Subtotal', 'Descuento', 'IVA', 'IEPS', 'IVA retenido', 'ISR retenido', 'Total', 'Total MXN', 'Monto pagos (P)', 'Estatus', 'Fecha cancelación', 'Relacionados']];
  rows.forEach(function (c) {
    f.push([c.fecha_emision, c.rel, c.tipo, c.serie, c.folio, c.uuid, c.rfc_emisor, c.nombre_emisor, c.rfc_receptor, c.nombre_receptor, c.metodo_pago, c.forma_pago, c.uso_cfdi, c.moneda,
      +c.tipo_cambio, +c.subtotal, +c.descuento, +c.iva, +c.ieps, +c.iva_retenido, +c.isr_retenido, +c.total, +c.total_mxn, +c.total_pagos, c.estatus_sat, c.fecha_cancelacion, c.relacionados]);
  });
  return f;
}

function fnExportarDetalle() {
  var p = $.extend(fnFiltrosDetalle(), { todo: 1 });
  SAT.api('getDetalleCfdi.php', p, { method: 'GET', loading: 'Preparando archivo...' }).done(function (r) {
    SAT.exportar('CFDI_' + REP.empresa.rfc + '_' + p.fecha_inicial + '_' + p.fecha_final, filasDetalle(r.cfdi));
  });
}

function fnExportarReporte() {
  if (!REP) { SAT.msg(false, 'Primero genera el reporte.'); return; }
  var i = REP.iva, r = i.resultado, x = REP.isr;
  var f = [
    ['Reporte fiscal', REP.empresa.rfc, REP.empresa.razon_social], ['Periodo', REP.periodo.inicio, REP.periodo.fin], [],
    ['IVA TRASLADADO', 'CFDI', 'Base', 'IVA', 'IVA retenido', 'ISR retenido'],
    ['Facturas PUE', i.trasladado.pue.n, i.trasladado.pue.base, i.trasladado.pue.iva, i.trasladado.pue.iva_ret, i.trasladado.pue.isr_ret],
    ['Cobros PPD', i.trasladado.ppd.n, i.trasladado.ppd.base_iva, i.trasladado.ppd.iva, i.trasladado.ppd.iva_ret, i.trasladado.ppd.isr_ret],
    ['Notas de crédito', i.trasladado.notas_credito.n, -i.trasladado.notas_credito.base, -i.trasladado.notas_credito.iva, -i.trasladado.notas_credito.iva_ret, -i.trasladado.notas_credito.isr_ret],
    ['Neto', '', '', i.trasladado.iva_neto, i.trasladado.iva_retenido, i.trasladado.isr_retenido], [],
    ['IVA ACREDITABLE', 'CFDI', 'Base', 'IVA', 'IVA retenido', 'ISR retenido'],
    ['Facturas PUE', i.acreditable.pue.n, i.acreditable.pue.base, i.acreditable.pue.iva, i.acreditable.pue.iva_ret, i.acreditable.pue.isr_ret],
    ['Pagos PPD', i.acreditable.ppd.n, i.acreditable.ppd.base_iva, i.acreditable.ppd.iva, i.acreditable.ppd.iva_ret, i.acreditable.ppd.isr_ret],
    ['Notas de crédito', i.acreditable.notas_credito.n, -i.acreditable.notas_credito.base, -i.acreditable.notas_credito.iva, -i.acreditable.notas_credito.iva_ret, -i.acreditable.notas_credito.isr_ret],
    ['Neto', '', '', i.acreditable.iva_neto, i.acreditable.iva_retenido, i.acreditable.isr_retenido], [],
    ['IVA trasladado cobrado', r.iva_trasladado_cobrado], ['(-) IVA retenido por clientes', -r.iva_retenido_por_clientes], ['(-) IVA acreditable pagado', -r.iva_acreditable_pagado],
    ['IVA a cargo', r.iva_a_cargo], ['IVA a favor', r.iva_a_favor], ['IVA retenido a terceros por enterar', r.iva_retenido_a_enterar], [],
    ['Ingresos cobrados', x.ingresos_cobrados], ['Deducciones pagadas', x.deducciones_pagadas], ['Base flujo', x.utilidad_flujo],
    ['Ingresos facturados', x.ingresos_facturados], ['Gastos facturados', x.gastos_facturados], ['ISR retenido por clientes', x.isr_retenido_por_clientes], ['ISR retenido a terceros', x.isr_retenido_a_enterar], [],
    ['PPD PENDIENTES', 'Relación', 'UUID', 'Serie/Folio', 'RFC', 'Nombre', 'Total', 'Pagado', 'Saldo', 'Saldo MXN']
  ];
  REP.ppd_pendientes.forEach(function (p) { f.push(['', p.rel, p.uuid, (p.serie || '') + (p.folio || ''), p.rfc, p.nombre, +p.total, +p.pagado, +p.saldo, +p.saldo_mxn]); });
  SAT.exportar('Reporte_fiscal_' + REP.empresa.rfc + '_' + REP.periodo.inicio, f);
}

function fnCfdi(id) {
  $('#cfdiBody').html('<div class="sat-cargando">Cargando...</div>'); SAT.modal('mdlCfdi');
  SAT.api('getPagosCfdi.php', { id_cfdi: id }, { method: 'GET' }).done(function (r) {
    var c = r.cfdi;
    $('#cfdiTitulo').text((TIPOS[c.tipo_comprobante] || c.tipo_comprobante) + ' ' + (c.serie || '') + (c.folio || ''));
    var h = '<div><span class="sat-uuid">' + SAT.esc(c.uuid) + '</span> · ' + SAT.fecha(c.fecha_emision) + ' · ' + SAT.esc(c.metodo_pago || '') + ' · Total ' + M(c.total) + ' ' + SAT.esc(c.moneda || '') + '</div><hr>';
    h += '<h5>' + (c.tipo_comprobante === 'P' ? 'Documentos que paga este complemento' : 'Pagos recibidos (complementos)') + '</h5>';
    h += r.pagos.length ? '<table class="sat-tabla"><thead><tr><th>Fecha pago</th><th>Documento</th><th class="num">Parcialidad</th><th class="num">Saldo anterior</th><th class="num">Pagado</th><th class="num">Saldo insoluto</th><th class="num">IVA MXN</th></tr></thead><tbody>' +
      r.pagos.map(function (p) { return '<tr><td>' + SAT.fecha(p.fecha_pago) + '</td><td>' + SAT.esc((p.serie || '') + (p.folio || '')) + '<br><span class="sat-uuid">' + SAT.esc(p.uuid) + '</span></td><td class="num">' + (p.num_parcialidad || '') + '</td><td class="num">' + M(p.imp_saldo_ant) + '</td><td class="num">' + M(p.imp_pagado) + '</td><td class="num">' + M(p.imp_saldo_insoluto) + '</td><td class="num">' + M(p.iva_mxn) + (p.iva_estimado == 1 ? '*' : '') + '</td></tr>'; }).join('') + '</tbody></table>'
      : '<div class="sat-vacio">Sin pagos registrados.</div>';
    if (r.relacionados.length || r.relacionado_por.length) {
      h += '<h5 style="margin-top:12px">CFDI relacionados</h5><ul>' +
        r.relacionados.map(function (x) { return '<li>Relación ' + SAT.esc(x.tipo_relacion) + ' con <span class="sat-uuid">' + SAT.esc(x.uuid_relacionado) + '</span>' + (x.id_cfdi ? '' : ' <small>(no descargado)</small>') + '</li>'; }).join('') +
        r.relacionado_por.map(function (x) { return '<li>Referenciado por ' + SAT.esc((x.serie || '') + (x.folio || '')) + ' (' + SAT.esc(x.tipo_relacion) + ') del ' + SAT.fecha(x.fecha_emision) + ' por ' + M(x.total) + '</li>'; }).join('') + '</ul>';
    }
    $('#cfdiBody').html(h);
  });
}

$(function () {
  $('#rMes').on('change', function () {
    var v = $(this).val(); if (!v) return;
    var p = v.split('-'), ult = new Date(parseInt(p[0], 10), parseInt(p[1], 10), 0).getDate();
    $('#rDesde').val(v + '-01'); $('#rHasta').val(v + '-' + (ult < 10 ? '0' : '') + ult);
  });
  $('#rDesde,#rHasta').on('change', function () { $('#rMes').val(''); });
  $(document).on('keydown', '#dBuscar', function (e) { if (e.key === 'Enter') fnDetalle(); });
  $(document).on('sat:panel', function (e, id) { if (id === 'pDet' && !DETALLE.length) fnDetalle(); if (id === 'pTend' && REP) fnGrafica(REP.tendencia || []); });
  if (parseInt($('#rEmpresa').val(), 10)) fnReporte();
});
