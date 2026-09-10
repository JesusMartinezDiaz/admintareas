package mx.com.biochemsystems.cobranzaia.integration.ventas;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Profile;
import org.springframework.stereotype.Component;
import org.springframework.web.reactive.function.client.WebClient;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;

@Slf4j
@Component
@Profile("!local-mock")
@RequiredArgsConstructor
public class VentasApiClientImpl implements VentasApiClient {

    @Qualifier("ventasApiWebClient")
    private final WebClient ventasApiWebClient;

    // TODO: "línea" (VETERINARIA/AGRICOLA) y "tipoCliente" (CLIENTE/DISTRIBUIDOR)
    // son segmentaciones reales del negocio (ver VntPoliticaComisionesEncabezadoCobranza)
    // que /reportesCobranza exige como parámetro obligatorio. Se fijan aquí como
    // constantes para el arranque local; deben confirmarse con el negocio y
    // exponerse como filtro real en el admin (no hardcodeados) antes de producción.
    private static final String LINEA_DEFAULT = "VETERINARIA";
    private static final String TIPO_CLIENTE_DEFAULT = "CLIENTE";

    @Override
    public List<Map<String, Object>> obtenerAntiguedadSaldosPorRuta(Long idRuta, Long idCompania) {
        if (idRuta != null) {
            // getAntiguadadSaldosclientesN exige diaRuta/totales/linea/tipoCliente además
            // de idRuta+idCompania (ver VntReportesCobranzaController). "diaRuta" filtra
            // por el día de la ruta maestra; se manda vacío para traer todos los días.
            return ventasApiWebClient.get()
                    .uri(uriBuilder -> uriBuilder
                            .path("/reportesCobranza/getAntiguadadSaldosclientesN")
                            .queryParam("idRuta", idRuta)
                            .queryParam("idCompania", idCompania)
                            .queryParam("diaRuta", "")
                            .queryParam("totales", 0)
                            .queryParam("linea", LINEA_DEFAULT)
                            .queryParam("tipoCliente", TIPO_CLIENTE_DEFAULT)
                            .build())
                    .retrieve()
                    .bodyToFlux(Map.class)
                    .map(m -> (Map<String, Object>) m)
                    .collectList()
                    .block();
        }

        // Sin ruta -> cartera de toda la compañía (getAntiguedadSaldosAllGentesN no
        // acepta idRuta/diaRuta/totales, solo idCompania+linea+tipoCliente).
        return ventasApiWebClient.get()
                .uri(uriBuilder -> uriBuilder
                        .path("/reportesCobranza/getAntiguedadSaldosAllGentesN")
                        .queryParam("idCompania", idCompania)
                        .queryParam("linea", LINEA_DEFAULT)
                        .queryParam("tipoCliente", TIPO_CLIENTE_DEFAULT)
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
