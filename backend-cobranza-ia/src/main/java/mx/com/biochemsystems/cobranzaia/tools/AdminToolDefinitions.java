package mx.com.biochemsystems.cobranzaia.tools;

import com.anthropic.core.JsonValue;
import com.anthropic.models.messages.Tool;

import java.util.List;
import java.util.Map;

/**
 * Herramientas (function calling) que el agente de datos de cartera puede
 * usar para responder preguntas del administrador. Cada nombre debe
 * coincidir exactamente con el switch en AdminToolDispatcher.
 */
public final class AdminToolDefinitions {

    private AdminToolDefinitions() {
    }

    public static List<Tool> todas() {
        return List.of(
                consultarAntiguedadSaldos(),
                consultarEstadoCuentaCliente(),
                consultarAlertasAbiertas(),
                consultarBloquesActivos()
        );
    }

    private static Tool consultarAntiguedadSaldos() {
        return Tool.builder()
                .name("consultar_antiguedad_saldos")
                .description("Obtiene la antigüedad de saldos (cartera vencida) de una ruta específica, "
                        + "o de toda la compañía si no se indica ruta. Usa esto para preguntas sobre "
                        + "cuánto se debe, quién debe más, o cómo está la cartera de una ruta.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder()
                                .putAdditionalProperty("idRuta", JsonValue.from(Map.of(
                                        "type", "integer",
                                        "description", "id_cat_rutas de la ruta a consultar. Omitir para toda la compañía.")))
                                .putAdditionalProperty("idCompania", JsonValue.from(Map.of(
                                        "type", "integer",
                                        "description", "19 = Biochem Systems, 20 = All 4 Pet's")))
                                .build())
                        .required(List.of("idCompania"))
                        .build())
                .build();
    }

    private static Tool consultarEstadoCuentaCliente() {
        return Tool.builder()
                .name("consultar_estado_cuenta_cliente")
                .description("Obtiene el estado de cuenta detallado (movimientos: facturas, pagos, notas) "
                        + "de un cliente específico en una compañía.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder()
                                .putAdditionalProperty("idCliente", JsonValue.from(Map.of("type", "integer")))
                                .putAdditionalProperty("idCompania", JsonValue.from(Map.of("type", "integer")))
                                .build())
                        .required(List.of("idCliente", "idCompania"))
                        .build())
                .build();
    }

    private static Tool consultarAlertasAbiertas() {
        return Tool.builder()
                .name("consultar_alertas_abiertas")
                .description("Lista las alertas abiertas de posible fraude, teléfono inválido o clientes "
                        + "que no reconocen su deuda, detectadas por el agente de WhatsApp.")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder().build())
                        .build())
                .build();
    }

    private static Tool consultarBloquesActivos() {
        return Tool.builder()
                .name("consultar_bloques_activos")
                .description("Lista los bloques de envío de WhatsApp en proceso o programados, con su "
                        + "avance (cuántos clientes ya fueron contactados vs. pendientes).")
                .inputSchema(Tool.InputSchema.builder()
                        .properties(Tool.InputSchema.Properties.builder().build())
                        .build())
                .build();
    }
}
