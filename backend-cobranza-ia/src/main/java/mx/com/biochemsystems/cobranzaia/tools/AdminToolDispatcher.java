package mx.com.biochemsystems.cobranzaia.tools;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusAlerta;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloque;
import mx.com.biochemsystems.cobranzaia.integration.ventas.VentasApiClient;
import mx.com.biochemsystems.cobranzaia.repository.AlertaClienteRepository;
import mx.com.biochemsystems.cobranzaia.repository.BloqueClienteRepository;
import mx.com.biochemsystems.cobranzaia.repository.BloqueEnvioRepository;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Map;
import java.util.stream.Stream;

/** Ejecuta la herramienta que Claude pidió invocar y devuelve el resultado serializado en JSON. */
@Slf4j
@Component
@RequiredArgsConstructor
public class AdminToolDispatcher {

    private final VentasApiClient ventasApiClient;
    private final AlertaClienteRepository alertaClienteRepository;
    private final BloqueEnvioRepository bloqueEnvioRepository;
    private final BloqueClienteRepository bloqueClienteRepository;
    private final ObjectMapper objectMapper;

    public String ejecutar(String nombreHerramienta, JsonNode input) {
        try {
            Object resultado = switch (nombreHerramienta) {
                case "consultar_antiguedad_saldos" -> consultarAntiguedadSaldos(input);
                case "consultar_estado_cuenta_cliente" -> consultarEstadoCuenta(input);
                case "consultar_alertas_abiertas" -> alertaClienteRepository.findByEstatus(EstatusAlerta.ABIERTA);
                case "consultar_bloques_activos" -> consultarBloquesActivos();
                default -> Map.of("error", "Herramienta no reconocida: " + nombreHerramienta);
            };
            return objectMapper.writeValueAsString(resultado);
        } catch (Exception e) {
            log.error("Error ejecutando herramienta {}: {}", nombreHerramienta, e.getMessage(), e);
            return "{\"error\": \"" + e.getMessage() + "\"}";
        }
    }

    private Object consultarAntiguedadSaldos(JsonNode input) {
        Long idRuta = input.has("idRuta") ? input.get("idRuta").asLong() : null;
        Long idCompania = input.get("idCompania").asLong();
        return ventasApiClient.obtenerAntiguedadSaldosPorRuta(idRuta, idCompania);
    }

    private Object consultarEstadoCuenta(JsonNode input) {
        Long idCliente = input.get("idCliente").asLong();
        Long idCompania = input.get("idCompania").asLong();
        return ventasApiClient.obtenerEstadoCuenta(idCliente, idCompania);
    }

    private Object consultarBloquesActivos() {
        List<Map<String, Object>> resumen = Stream.of(EstatusBloque.EN_PROCESO, EstatusBloque.PROGRAMADO)
                .flatMap(estatus -> bloqueEnvioRepository.findByEstatus(estatus).stream())
                .map(bloque -> {
                    long total = bloqueClienteRepository.findByIdBloqueOrderByOrdenEnvioAsc(bloque.getIdBloque()).size();
                    long enviados = bloqueClienteRepository.findByIdBloqueOrderByOrdenEnvioAsc(bloque.getIdBloque())
                            .stream().filter(bc -> bc.getFechaEnvio() != null).count();
                    return Map.<String, Object>of(
                            "idBloque", bloque.getIdBloque(),
                            "nombre", bloque.getNombre(),
                            "estatus", bloque.getEstatus(),
                            "totalClientes", total,
                            "clientesContactados", enviados
                    );
                })
                .toList();
        return resumen;
    }
}
