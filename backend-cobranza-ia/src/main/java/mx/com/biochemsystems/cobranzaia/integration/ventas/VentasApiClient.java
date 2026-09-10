package mx.com.biochemsystems.cobranzaia.integration.ventas;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;

/**
 * Cliente hacia el backend de ventas (apibackendventas), que ya expone la
 * lógica real de antigüedad de saldos, estado de cuenta y aplicación de
 * abonos/notas de crédito. Este módulo de cobranza NO reimplementa esas
 * reglas: las consume, para no duplicar/desviarse de la fuente de verdad.
 *
 * Endpoints de referencia en apibackendventas (confirmados leyendo su
 * código fuente, ver VntAbonoController / VntAbonoImpl):
 *  - GET  /reportesCobranza/getAntiguadadSaldosclientesN?idRuta=&idCompania=&diaRuta=&totales=&linea=&tipoCliente=
 *  - GET  /reportesCobranza/getAntiguedadSaldosAllGentesN?idCompania=&linea=&tipoCliente=
 *  - GET  /vntEstadoCuenta/byIdClienteAndCompania?idCliente=&tipo=&idCompania=
 *  - POST /vntAbono/savePagos - registra un pago y lo concilia FIFO automático
 *    contra las facturas pendientes del cliente (VntAbonoImpl.saveAbonos).
 *  - POST /vntAbono/saveNotaCreditoPP?idCargo= - genera y aplica una nota de
 *    crédito proporcional (IEPS/IVA prorrateados) sobre una factura
 *    específica; YA es el mecanismo real de "nota de crédito por pronto
 *    pago" - decide el % de descuento el llamador (este módulo).
 */
public interface VentasApiClient {

    List<Map<String, Object>> obtenerAntiguedadSaldosPorRuta(Long idRuta, Long idCompania);

    List<Map<String, Object>> obtenerEstadoCuenta(Long idCliente, Long idCompania);

    /**
     * Registra un pago (idCatDocumento=3) y lo concilia FIFO contra las
     * facturas pendientes del cliente - ver VntAbonoImpl#saveAbonos.
     * Devuelve el id_abono generado.
     */
    Long registrarPago(Long idCliente, Long idCompania, Long idAgente, BigDecimal monto,
                        String cveUsuario, String referencia, String comentarios);

    /**
     * Genera y aplica una nota de crédito por pronto pago sobre una
     * factura específica (VntAbonoImpl#saveNotaCreditoPP): el monto se
     * prorratea fiscalmente según la composición de esa factura y se
     * resta de su saldo pendiente de inmediato. Devuelve el id_abono
     * de la nota generada.
     */
    Long aplicarNotaCreditoProntoPago(Long idCargo, BigDecimal montoDescuento, String cveUsuario);
}
