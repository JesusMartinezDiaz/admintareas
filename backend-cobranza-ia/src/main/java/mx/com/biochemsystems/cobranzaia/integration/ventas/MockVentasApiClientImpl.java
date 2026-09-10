package mx.com.biochemsystems.cobranzaia.integration.ventas;

import org.springframework.context.annotation.Profile;
import org.springframework.stereotype.Component;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.List;
import java.util.Map;

/**
 * Implementación de desarrollo local: no llama a apibackendventas (que
 * requiere su propio servidor + base de datos real, ver
 * biochem-frontend-integration / apibackendventas-local-run). Sirve
 * datos sintéticos con la misma forma que el AntiguedadSaldosDTO y
 * VntEstadoCuentaDTO reales, para poder levantar y probar todo el flujo
 * de bloques/agente-ia sin depender de ese backend.
 *
 * Se activa con el perfil Spring "local-mock" (ver application-local.yml).
 */
@Component
@Profile("local-mock")
public class MockVentasApiClientImpl implements VentasApiClient {

    @Override
    public List<Map<String, Object>> obtenerAntiguedadSaldosPorRuta(Long idRuta, Long idCompania) {
        return List.of(
                fila("1", "Veterinaria Ejemplo del Norte", "Ruta 12 - CDMX Sur", "AG-001", "12500.00"),
                fila("2", "Agropecuaria Ejemplo Dos", "Ruta 12 - CDMX Sur", "AG-001", "3200.50"),
                fila("3", "Rancho Ejemplo Tres", "Ruta 12 - CDMX Sur", "AG-001", "0.00")
        );
    }

    private Map<String, Object> fila(String idCliente, String nombre, String ruta, String agente, String saldoTotal) {
        return Map.ofEntries(
                Map.entry("idCliente", idCliente),
                Map.entry("nombre", nombre),
                Map.entry("ruta", ruta),
                Map.entry("agente", agente),
                Map.entry("saldoTotal", saldoTotal),
                Map.entry("saldo30", saldoTotal),
                Map.entry("saldo60", "0.00"),
                Map.entry("saldo150", "0.00"),
                Map.entry("saldo300", "0.00"),
                Map.entry("saldo301", "0.00"),
                Map.entry("saldo401", "0.00")
        );
    }

    @Override
    public List<Map<String, Object>> obtenerEstadoCuenta(Long idCliente, Long idCompania) {
        return List.of(
                Map.of(
                        "idCatDocumento", 1,
                        "numDocumento", 1001,
                        "fechaDocumento", LocalDate.now().minusDays(20).toString(),
                        "total", "12500.00",
                        "cargo", "12500.00",
                        "abono", "0.00"
                )
        );
    }

    @Override
    public Long registrarPago(Long idCliente, Long idCompania, Long idAgente, BigDecimal monto,
                               String cveUsuario, String referencia, String comentarios) {
        // En modo mock simplemente simula un id de abono generado.
        return System.currentTimeMillis();
    }

    @Override
    public Long aplicarNotaCreditoProntoPago(Long idCargo, BigDecimal montoDescuento, String cveUsuario) {
        return System.currentTimeMillis();
    }
}
