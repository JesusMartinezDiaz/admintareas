package mx.com.biochemsystems.cobranzaia.integration.ventas;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.config.VentasApiProperties;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Profile;
import org.springframework.jdbc.core.JdbcTemplate;
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
    private final JdbcTemplate jdbcTemplate;
    private final VentasApiProperties ventasApiProperties;

    private volatile Long idTipoDocumentoPagoCache;

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
    public Long registrarPago(Long idCliente, Long idCompania, Long idAgente, BigDecimal monto,
                               String cveUsuario, String referencia, String comentarios) {
        Map<String, Object> body = new java.util.HashMap<>();
        body.put("idCompania", idCompania);
        body.put("idCatDocumento", 3L); // pago, ver VntAbonoImpl
        body.put("idTipoDocumento", resolverIdTipoDocumentoPago());
        body.put("idCliente", idCliente);
        body.put("idAgente", idAgente); // si se manda, saveAbonos no exige vntDocumentosAsociaciones
        body.put("total", monto);
        body.put("cveUsuario", cveUsuario);
        body.put("referenciaPagos", referencia != null ? referencia : "");
        body.put("comentarios", comentarios);

        Map<String, Object> respuesta = ventasApiWebClient.post()
                .uri("/vntAbono/savePagos")
                .bodyValue(body)
                .retrieve()
                .bodyToMono(Map.class)
                .block();

        return numeroOrNull(respuesta != null ? respuesta.get("idAbono") : null);
    }

    @Override
    public Long aplicarNotaCreditoProntoPago(Long idCargo, BigDecimal montoDescuento, String cveUsuario) {
        Map<String, Object> body = Map.of(
                "total", montoDescuento,
                "cveUsuario", cveUsuario != null ? cveUsuario : "agente-ia"
        );

        Map<String, Object> respuesta = ventasApiWebClient.post()
                .uri(uriBuilder -> uriBuilder.path("/vntAbono/saveNotaCreditoPP").queryParam("idCargo", idCargo).build())
                .bodyValue(body)
                .retrieve()
                .bodyToMono(Map.class)
                .block();

        return numeroOrNull(respuesta != null ? respuesta.get("idAbono") : null);
    }

    /**
     * VntAbonoImpl#saveAbonos no fija idTipoDocumento (a diferencia de
     * idCatDocumento=3): hay que mandar un valor válido de
     * vnt_cat_tipo_documento para id_cat_documento=3 ("pago"). Si el
     * negocio confirma cuál usar, fíjalo en
     * ventas-api.id-tipo-documento-pago; si no, se resuelve el primero
     * que exista (correcto solo si hay un único tipo de pago genérico).
     */
    private Long resolverIdTipoDocumentoPago() {
        String configurado = ventasApiProperties.getIdTipoDocumentoPago();
        if (configurado != null && !configurado.isBlank()) {
            return Long.parseLong(configurado.trim());
        }
        if (idTipoDocumentoPagoCache == null) {
            Long resuelto = jdbcTemplate.queryForObject(
                    "SELECT id_tipo_documento FROM vnt_cat_tipo_documento WHERE id_cat_documento = 3 ORDER BY id_tipo_documento LIMIT 1",
                    Long.class);
            if (resuelto == null) {
                throw new IllegalStateException(
                        "No se encontró ningún vnt_cat_tipo_documento con id_cat_documento=3 (pago). "
                                + "Configura ventas-api.id-tipo-documento-pago explícitamente.");
            }
            log.warn("ventas-api.id-tipo-documento-pago no configurado; usando el primero encontrado en "
                    + "vnt_cat_tipo_documento para pagos: {}. Confirma con negocio si es el correcto.", resuelto);
            idTipoDocumentoPagoCache = resuelto;
        }
        return idTipoDocumentoPagoCache;
    }

    private Long numeroOrNull(Object valor) {
        return valor instanceof Number n ? n.longValue() : null;
    }
}
