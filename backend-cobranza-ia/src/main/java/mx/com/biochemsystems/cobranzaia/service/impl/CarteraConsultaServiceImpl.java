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
        // AntiguedadSaldosDTO (apibackendventas) serializa TODO como String,
        // incluido idCliente y los montos (saldoTotal, saldo30, saldo60, ...) -
        // numeroOrNull/decimalOrNull parsean ambos casos (Number o String).
        // No expone un conteo de "días vencido" real, por eso diasVencidoMax
        // queda null aquí (el filtro por diasMinVencido se vuelve un no-op;
        // ver TODO en CarteraConsultaService sobre reemplazarlo por montoVencido).
        return ClienteCarteraDTO.builder()
                .idCliente(numeroOrNull(fila.get("idCliente")))
                .nombreCliente((String) fila.getOrDefault("nombreCliente", fila.get("nombre")))
                .nombreRuta((String) fila.get("ruta"))
                .idAgente(numeroOrNull(fila.get("agente")))
                .idRuta(idRutaFiltro)
                .saldoTotal(decimalOrNull(fila.get("saldoTotal")))
                .diasVencidoMax(null)
                .build();
    }

    private Long numeroOrNull(Object valor) {
        if (valor instanceof Number n) {
            return n.longValue();
        }
        if (valor instanceof String s && !s.isBlank()) {
            try {
                return Long.parseLong(s.trim());
            } catch (NumberFormatException e) {
                return null;
            }
        }
        return null;
    }

    private BigDecimal decimalOrNull(Object valor) {
        if (valor instanceof BigDecimal bd) {
            return bd;
        }
        if (valor instanceof Number n) {
            return BigDecimal.valueOf(n.doubleValue());
        }
        if (valor instanceof String s && !s.isBlank()) {
            try {
                return new BigDecimal(s.trim());
            } catch (NumberFormatException e) {
                return null;
            }
        }
        return null;
    }
}
