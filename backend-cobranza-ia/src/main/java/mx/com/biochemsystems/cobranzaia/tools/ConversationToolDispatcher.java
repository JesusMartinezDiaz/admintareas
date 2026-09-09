package mx.com.biochemsystems.cobranzaia.tools;

import com.fasterxml.jackson.databind.JsonNode;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.domain.AlertaCliente;
import mx.com.biochemsystems.cobranzaia.domain.ComprobantePago;
import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusConversacion;
import mx.com.biochemsystems.cobranzaia.domain.enums.TipoAlerta;
import mx.com.biochemsystems.cobranzaia.repository.AlertaClienteRepository;
import mx.com.biochemsystems.cobranzaia.repository.ComprobantePagoRepository;
import mx.com.biochemsystems.cobranzaia.repository.ConversacionRepository;
import mx.com.biochemsystems.cobranzaia.repository.NotaCreditoGeneradaRepository;
import mx.com.biochemsystems.cobranzaia.service.ProntoPagoRuleEngine;
import org.springframework.stereotype.Component;

import java.math.BigDecimal;
import java.time.LocalDate;

/**
 * Ejecuta las herramientas del agente conversacional de WhatsApp para una
 * conversación/cliente concretos. A diferencia del agente de datos, aquí
 * SÍ se escriben datos (alertas, propuestas de NDC, comprobantes) - pero
 * ninguna de estas herramientas aplica un movimiento real en el sistema
 * de ventas: todo queda en estatus "propuesta/pendiente" hasta que
 * VentasApiClient exponga la aplicación real (ver TODO en esa clase).
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class ConversationToolDispatcher {

    private final ComprobantePagoRepository comprobantePagoRepository;
    private final ProntoPagoRuleEngine prontoPagoRuleEngine;
    private final NotaCreditoGeneradaRepository notaCreditoGeneradaRepository;
    private final AlertaClienteRepository alertaClienteRepository;
    private final ConversacionRepository conversacionRepository;

    public String ejecutar(String nombreHerramienta, JsonNode input, Conversacion conversacion, Long idMensajeOrigen) {
        return switch (nombreHerramienta) {
            case "registrar_comprobante_pago" -> registrarComprobante(input, conversacion, idMensajeOrigen);
            case "evaluar_pronto_pago" -> evaluarProntoPago(input, conversacion);
            case "marcar_alerta_cliente" -> marcarAlerta(input, conversacion);
            case "escalar_atencion_humana" -> escalarAtencionHumana(input, conversacion);
            default -> "{\"error\": \"Herramienta no reconocida: " + nombreHerramienta + "\"}";
        };
    }

    private String registrarComprobante(JsonNode input, Conversacion conversacion, Long idMensajeOrigen) {
        ComprobantePago comprobante = comprobantePagoRepository.save(ComprobantePago.builder()
                .idMensaje(idMensajeOrigen)
                .idCliente(conversacion.getIdCliente())
                .montoDetectado(input.has("montoDetectado") ? BigDecimal.valueOf(input.get("montoDetectado").asDouble()) : null)
                .fechaPagoDetectada(input.has("fechaPagoDetectada") ? LocalDate.parse(input.get("fechaPagoDetectada").asText()) : null)
                .referenciaDetectada(textoOrNull(input, "referenciaDetectada"))
                .bancoDetectado(textoOrNull(input, "bancoDetectado"))
                .confianzaIa(input.has("confianza") ? BigDecimal.valueOf(input.get("confianza").asDouble()) : null)
                .build());
        return "{\"idComprobante\": " + comprobante.getIdComprobante()
                + ", \"estatus\": \"PENDIENTE_VALIDACION\", "
                + "\"nota\": \"Comprobante registrado; pendiente de conciliación humana o automática con banco\"}";
    }

    private String evaluarProntoPago(JsonNode input, Conversacion conversacion) {
        BigDecimal montoFactura = BigDecimal.valueOf(input.get("montoFactura").asDouble());
        LocalDate fechaFactura = LocalDate.parse(input.get("fechaFactura").asText());
        LocalDate fechaPago = LocalDate.parse(input.get("fechaPago").asText());
        Long idCargoOriginal = input.has("idCargoOriginal") ? input.get("idCargoOriginal").asLong() : null;

        NotaCreditoGenerada propuesta = prontoPagoRuleEngine.evaluar(
                conversacion.getIdCliente(), null, null, idCargoOriginal, montoFactura, fechaFactura, fechaPago);

        if (propuesta == null) {
            return "{\"califica\": false, \"mensaje\": \"Ninguna regla de pronto pago vigente aplica a este pago\"}";
        }
        propuesta.setIdConversacion(conversacion.getIdConversacion());
        NotaCreditoGenerada guardada = notaCreditoGeneradaRepository.save(propuesta);
        return "{\"califica\": true, \"idNotaGenerada\": " + guardada.getIdNotaGenerada()
                + ", \"montoDescuento\": " + guardada.getMontoDescuento()
                + ", \"estatus\": \"" + guardada.getEstatus() + "\"}";
    }

    private String marcarAlerta(JsonNode input, Conversacion conversacion) {
        AlertaCliente alerta = alertaClienteRepository.save(AlertaCliente.builder()
                .idCliente(conversacion.getIdCliente())
                .idConversacion(conversacion.getIdConversacion())
                .tipo(TipoAlerta.valueOf(input.get("tipo").asText()))
                .detalle(textoOrNull(input, "detalle"))
                .origen("IA")
                .build());
        conversacion.setRequiereAtencionHumana(true);
        conversacion.setEstatus(EstatusConversacion.ESPERANDO_REVISION_HUMANA);
        conversacionRepository.save(conversacion);
        return "{\"idAlerta\": " + alerta.getIdAlerta() + ", \"registrada\": true}";
    }

    private String escalarAtencionHumana(JsonNode input, Conversacion conversacion) {
        conversacion.setRequiereAtencionHumana(true);
        conversacion.setEstatus(EstatusConversacion.ESPERANDO_REVISION_HUMANA);
        conversacionRepository.save(conversacion);
        log.info("Conversación {} escalada a humano. Motivo: {}",
                conversacion.getIdConversacion(), textoOrNull(input, "motivo"));
        return "{\"escalado\": true}";
    }

    private String textoOrNull(JsonNode input, String campo) {
        return input.has(campo) ? input.get(campo).asText() : null;
    }
}
