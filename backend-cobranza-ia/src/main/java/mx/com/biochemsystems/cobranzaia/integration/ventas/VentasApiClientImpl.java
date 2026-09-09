package mx.com.biochemsystems.cobranzaia.integration.ventas;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.stereotype.Component;
import org.springframework.web.reactive.function.client.WebClient;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;

@Slf4j
@Component
@RequiredArgsConstructor
public class VentasApiClientImpl implements VentasApiClient {

    @Qualifier("ventasApiWebClient")
    private final WebClient ventasApiWebClient;

    @Override
    public List<Map<String, Object>> obtenerAntiguedadSaldosPorRuta(Long idRuta, Long idCompania) {
        return ventasApiWebClient.get()
                .uri(uriBuilder -> uriBuilder
                        .path("/reportesCobranza/getAntiguadadSaldosclientesN")
                        .queryParam("idRuta", idRuta)
                        .queryParam("idCompania", idCompania)
                        .queryParam("tipo", 1)
                        .build())
                .retrieve()
                .bodyToFlux(Map.class)
                .map(m -> (Map<String, Object>) m)
                .collectList()
                .block();
    }

    @Override
    public List<Map<String, Object>> obtenerEstadoCuenta(Long idCliente, Long idCompania) {
        return ventasApiWebClient.get()
                .uri(uriBuilder -> uriBuilder
                        .path("/vntEstadoCuenta/byIdClienteAndCompania")
                        .queryParam("idCliente", idCliente)
                        .queryParam("tipo", 1)
                        .queryParam("idCompania", idCompania)
                        .build())
                .retrieve()
                .bodyToFlux(Map.class)
                .map(m -> (Map<String, Object>) m)
                .collectList()
                .block();
    }

    @Override
    public Long registrarAbono(Long idCliente, Long idCompania, Long idCatDocumento,
                                BigDecimal monto, String referencia, String comentarios) {
        // TODO: ajustar la ruta y el payload exacto una vez expuesto el endpoint de
        // registro de abonos para integraciones externas en apibackendventas
        // (hoy VntAbonoImpl.saveAbonos se invoca solo desde el propio front de ventas).
        // Este stub deja explícito el contrato que el agente de cobranza necesita.
        log.warn("registrarAbono es un stub: falta el endpoint de integración en apibackendventas. "
                        + "cliente={}, compania={}, monto={}, referencia={}",
                idCliente, idCompania, monto, referencia);
        throw new UnsupportedOperationException(
                "Pendiente de exponer endpoint de aplicación de abonos en apibackendventas");
    }
}
