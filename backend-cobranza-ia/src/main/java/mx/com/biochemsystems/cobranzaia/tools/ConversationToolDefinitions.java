package mx.com.biochemsystems.cobranzaia.tools;

import com.anthropic.core.JsonValue;
import com.anthropic.models.messages.Tool;

import java.util.List;
import java.util.Map;

/**
 * Herramientas que el agente conversacional de WhatsApp puede usar durante
 * una plática de cobranza con un cliente. El cliente/conversación ya son
 * conocidos por el orquestador (no se piden como parámetro al modelo).
 */
public final class ConversationToolDefinitions {

    private ConversationToolDefinitions() {
    }

    public static List<Tool> todas() {
        return List.of(
                registrarComprobantePago(),
                evaluarProntoPago(),
                marcarAlertaCliente(),
                escalarAtencionHumana()
        );
    }

    private static Tool registrarComprobantePago() {
        return Tool.builder()
                .name("registrar_comprobante_pago")
                .description("Registra los datos que extrajiste de una imagen/documento de comprobante de "
                        + "pago que el cliente envió. NO aplica el pago automáticamente: queda pendiente de "
                        + "validación humana o de conciliación. Úsalo solo cuando el cliente haya adjuntado "
                        + "un comprobante en el mensaje.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder()
                                .putAdditionalProperty("montoDetectado", JsonValue.from(Map.of("type", "number")))
                                .putAdditionalProperty("fechaPagoDetectada", JsonValue.from(Map.of(
                                        "type", "string", "description", "Formato YYYY-MM-DD")))
                                .putAdditionalProperty("referenciaDetectada", JsonValue.from(Map.of("type", "string")))
                                .putAdditionalProperty("bancoDetectado", JsonValue.from(Map.of("type", "string")))
                                .putAdditionalProperty("confianza", JsonValue.from(Map.of(
                                        "type", "number",
                                        "description", "0.0 a 1.0, qué tan seguro estás de la lectura del comprobante")))
                                .build())
                        .required(List.of("montoDetectado", "confianza"))
                        .build())
                .build();
    }

    private static Tool evaluarProntoPago() {
        return Tool.builder()
                .name("evaluar_pronto_pago")
                .description("Evalúa si el pago que el cliente está reportando (o va a hacer) califica para "
                        + "una nota de crédito por pronto pago, según las reglas vigentes. Úsalo antes de "
                        + "ofrecer un descuento al cliente; nunca ofrezcas un % de tu propia cuenta.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder()
                                .putAdditionalProperty("idCargoOriginal", JsonValue.from(Map.of("type", "integer")))
                                .putAdditionalProperty("montoFactura", JsonValue.from(Map.of("type", "number")))
                                .putAdditionalProperty("fechaFactura", JsonValue.from(Map.of("type", "string")))
                                .putAdditionalProperty("fechaPago", JsonValue.from(Map.of(
                                        "type", "string",
                                        "description", "Fecha en que el cliente dice que pagará/pagó, YYYY-MM-DD")))
                                .build())
                        .required(List.of("montoFactura", "fechaFactura", "fechaPago"))
                        .build())
                .build();
    }

    private static Tool marcarAlertaCliente() {
        return Tool.builder()
                .name("marcar_alerta_cliente")
                .description("Registra una alerta sobre este cliente cuando detectes algo anómalo: dice no "
                        + "reconocer la deuda, datos que no cuadran, o señales de posible fraude/cliente "
                        + "inexistente. No acuses directamente al cliente en el chat; solo registra la alerta "
                        + "para que un humano la revise.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder()
                                .putAdditionalProperty("tipo", JsonValue.from(Map.of(
                                        "type", "string",
                                        "enum", List.of("TELEFONO_INVALIDO", "CLIENTE_NO_RECONOCE_DEUDA",
                                                "DATOS_INCONSISTENTES", "SIN_RESPUESTA_REPETIDA", "POSIBLE_FRAUDE"))))
                                .putAdditionalProperty("detalle", JsonValue.from(Map.of("type", "string")))
                                .build())
                        .required(List.of("tipo", "detalle"))
                        .build())
                .build();
    }

    private static Tool escalarAtencionHumana() {
        return Tool.builder()
                .name("escalar_atencion_humana")
                .description("Marca la conversación para que un cobrador humano la tome, cuando el cliente "
                        + "pide hablar con una persona, la negociación se sale de las políticas permitidas, "
                        + "o tú no tienes suficiente confianza para continuar solo.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder()
                                .putAdditionalProperty("motivo", JsonValue.from(Map.of("type", "string")))
                                .build())
                        .required(List.of("motivo"))
                        .build())
                .build();
    }
}
