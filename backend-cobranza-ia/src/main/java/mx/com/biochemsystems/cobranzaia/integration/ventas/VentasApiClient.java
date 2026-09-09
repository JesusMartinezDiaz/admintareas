package mx.com.biochemsystems.cobranzaia.integration.ventas;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;

/**
 * Cliente hacia el backend de ventas (apibackendventas), que ya expone la
 * lógica real de antigüedad de saldos, estado de cuenta y aplicación de
 * abonos. Este módulo de cobranza NO reimplementa esas reglas: las
 * consume, para no duplicar/desviarse de la fuente de verdad.
 *
 * Endpoints de referencia en apibackendventas:
 *  - GET /reportesCobranza/getAntiguadadSaldosclientesN?idRuta=&idCompania=&tipo=
 *  - GET /vntEstadoCuenta/byIdClienteAndCompania?idCliente=&tipo=&idCompania=
 *  - POST /vntAbono/... (registro de abonos / notas de crédito - ver VntAbonoImpl)
 */
public interface VentasApiClient {

    /**
     * Antigüedad de saldos de todos los clientes de una ruta (o de toda
     * la compañía si idRuta es null), tal cual la calcula
     * VntReportesCobranzaController#getAntiguadadSaldosclientesN.
     */
    List<Map<String, Object>> obtenerAntiguedadSaldosPorRuta(Long idRuta, Long idCompania);

    /** Estado de cuenta detallado (movimientos) de un cliente en una compañía. */
    List<Map<String, Object>> obtenerEstadoCuenta(Long idCliente, Long idCompania);

    /**
     * Registra un abono (pago o nota de crédito) en el sistema de ventas.
     * idCatDocumento: 3 = pago, 4 = nota de crédito (ver VntAbonoImpl).
     * Devuelve el id_abono generado.
     */
    Long registrarAbono(Long idCliente, Long idCompania, Long idCatDocumento,
                         BigDecimal monto, String referencia, String comentarios);
}
