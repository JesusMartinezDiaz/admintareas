package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;
import mx.com.biochemsystems.cobranzaia.domain.TelefonoCliente;
import mx.com.biochemsystems.cobranzaia.integration.ventas.VentasApiClient;
import mx.com.biochemsystems.cobranzaia.repository.ClienteLookupRepository;
import mx.com.biochemsystems.cobranzaia.repository.TelefonoClienteRepository;
import mx.com.biochemsystems.cobranzaia.service.CarteraConsultaService;
import org.springframework.stereotype.Service;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;
import java.util.Optional;

/**
 * La antigüedad de saldos y montos se obtienen SIEMPRE de
 * apibackendventas (VentasApiClient), que es la fuente de verdad de esa
 * lógica (FIFO de conciliación, exclusión de PAG no conciliados, etc.).
 * Este servicio solo combina esa información con teléfono/ruta/agente
 * para dejarla lista para el módulo de WhatsApp.
 */
@Service
@RequiredArgsConstructor
public class CarteraConsultaServiceImpl implements CarteraConsultaService {

    private final VentasApiClient ventasApiClient;
    private final ClienteLookupRepository clienteLookupRepository;
    private final TelefonoClienteRepository telefonoClienteRepository;

    @Override
    public List<ClienteCarteraDTO> obtenerCarteraVencidaPorRuta(Long idRuta, Long idCompania, int diasMinVencido) {
        List<Map<String, Object>> filas = ventasApiClient.obtenerAntiguedadSaldosPorRuta(idRuta, idCompania);

        return filas.stream()
                .map(fila -> mapearFila(fila, idRuta))
                .filter(dto -> dto.getSaldoTotal() != null && dto.getSaldoTotal().compareTo(BigDecimal.ZERO) > 0)
                .filter(dto -> dto.getDiasVencidoMax() == null || dto.getDiasVencidoMax() >= diasMinVencido)
                .toList();
    }

    @Override
    public Optional<ClienteCarteraDTO> obtenerClientePorTelefono(String telefono, Long idCompania) {
        return clienteLookupRepository.buscarClientePorTelefono(telefono).map(cliente -> {
            Long idCliente = ((Number) cliente.get("id_cliente")).longValue();
            Map<String, Object> rutaAgente = clienteLookupRepository
                    .obtenerRutaYAgentePorCliente(idCliente).orElse(Map.of());

            return ClienteCarteraDTO.builder()
                    .idCliente(idCliente)
                    .nombreCliente((String) cliente.get("nombre"))
                    .nombreComercial((String) cliente.get("nombre_comercial"))
                    .idRuta(numeroOrNull(rutaAgente.get("id_ruta")))
                    .nombreRuta((String) rutaAgente.get("nombre_ruta"))
                    .idAgente(numeroOrNull(rutaAgente.get("id_agente")))
                    .telefonoWhatsapp(clienteLookupRepository.normalizar(telefono))
                    .build();
        });
    }

    @Override
    public Optional<ClienteCarteraDTO> obtenerClientePorId(Long idCliente, Long idCompania) {
        return clienteLookupRepository.buscarClientePorId(idCliente).map(cliente -> {
            Map<String, Object> rutaAgente = clienteLookupRepository
                    .obtenerRutaYAgentePorCliente(idCliente).orElse(Map.of());

            String telefonoWhatsapp = telefonoClienteRepository
                    .findByIdClienteAndEstatus(idCliente, "A").stream()
                    .filter(TelefonoCliente::getEsWhatsapp)
                    .sorted((a, b) -> Boolean.compare(b.getPreferido(), a.getPreferido()))
                    .map(TelefonoCliente::getTelefonoE164)
                    .findFirst()
                    .orElseGet(() -> clienteLookupRepository.normalizar((String) cliente.get("telefono_comercial")));

            return ClienteCarteraDTO.builder()
                    .idCliente(idCliente)
                    .nombreCliente((String) cliente.get("nombre"))
                    .nombreComercial((String) cliente.get("nombre_comercial"))
                    .idRuta(numeroOrNull(rutaAgente.get("id_ruta")))
                    .nombreRuta((String) rutaAgente.get("nombre_ruta"))
                    .idAgente(numeroOrNull(rutaAgente.get("id_agente")))
                    .telefonoWhatsapp(telefonoWhatsapp)
                    .build();
        });
    }

    @SuppressWarnings("unchecked")
    private ClienteCarteraDTO mapearFila(Map<String, Object> fila, Long idRutaFiltro) {
        // Los nombres de campo exactos dependen del DTO serializado por
        // AntiguedadSaldosDTO en apibackendventas; se mapean de forma
        // defensiva porque, según el análisis del backend, esos nombres
        // no siempre corresponden a su rango real (ver README).
        return ClienteCarteraDTO.builder()
                .idCliente(numeroOrNull(fila.get("idCliente")))
                .nombreCliente((String) fila.getOrDefault("nombreCliente", fila.get("nombre")))
                .idRuta(idRutaFiltro)
                .saldoTotal(decimalOrNull(fila.get("saldoTotal")))
                .diasVencidoMax(fila.get("diasVencido") != null ? ((Number) fila.get("diasVencido")).intValue() : null)
                .build();
    }

    private Long numeroOrNull(Object valor) {
        return valor instanceof Number n ? n.longValue() : null;
    }

    private BigDecimal decimalOrNull(Object valor) {
        if (valor instanceof BigDecimal bd) {
            return bd;
        }
        return valor instanceof Number n ? BigDecimal.valueOf(n.doubleValue()) : null;
    }
}
