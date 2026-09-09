package mx.com.biochemsystems.cobranzaia.service.impl;

import com.anthropic.client.AnthropicClient;
import com.anthropic.models.messages.ContentBlock;
import com.anthropic.models.messages.ContentBlockParam;
import com.anthropic.models.messages.Message;
import com.anthropic.models.messages.MessageCreateParams;
import com.anthropic.models.messages.MessageParam;
import com.anthropic.models.messages.StopReason;
import com.anthropic.models.messages.ToolResultBlockParam;
import com.anthropic.models.messages.ToolUseBlock;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.config.AnthropicProperties;
import mx.com.biochemsystems.cobranzaia.domain.AgenteIaConsultaLog;
import mx.com.biochemsystems.cobranzaia.dto.ChatAdminResponse;
import mx.com.biochemsystems.cobranzaia.repository.AgenteIaConsultaLogRepository;
import mx.com.biochemsystems.cobranzaia.service.CarteraAdminAiService;
import mx.com.biochemsystems.cobranzaia.tools.AdminToolDefinitions;
import mx.com.biochemsystems.cobranzaia.tools.AdminToolDispatcher;
import org.springframework.stereotype.Service;

import java.util.ArrayList;
import java.util.List;

/**
 * "Agente de datos" para el administrador: responde preguntas en lenguaje
 * natural sobre la cartera usando tool use manual (no el ToolRunner beta,
 * para poder inyectar dependencias de Spring en el dispatcher).
 *
 * NOTA DE IMPLEMENTACIÓN: los nombres exactos de accessor en ToolUseBlock
 * (id()/name()/input()) siguen el patrón documentado para el SDK Java de
 * Anthropic 2.34.0; validar con `mvn -q compile` al integrar credenciales
 * reales, ya que la documentación no lista el accessor de ToolUseBlock
 * (no-beta) literal, solo el de ServerToolUseBlock.
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class CarteraAdminAiServiceImpl implements CarteraAdminAiService {

    private static final String SYSTEM_PROMPT = """
            Eres el asistente de datos de cartera y cobranza de Biochem Systems.
            Respondes preguntas del equipo administrativo sobre cartera vencida,
            antigüedad de saldos, bloques de envío de WhatsApp y alertas de
            posible fraude, usando EXCLUSIVAMENTE las herramientas disponibles
            para obtener datos reales; nunca inventes cifras. Si una pregunta
            requiere datos que ninguna herramienta puede dar, dilo explícitamente.
            Responde en español, de forma breve y con números concretos.
            """;

    private final AnthropicClient anthropicClient;
    private final AnthropicProperties anthropicProperties;
    private final AdminToolDispatcher adminToolDispatcher;
    private final AgenteIaConsultaLogRepository consultaLogRepository;
    private final ObjectMapper objectMapper;

    @Override
    public ChatAdminResponse responder(String pregunta, String cveUsuario) {
        List<MessageParam> historial = new ArrayList<>();
        historial.add(MessageParam.builder().role(MessageParam.Role.USER).content(pregunta).build());

        List<String> herramientasInvocadas = new ArrayList<>();
        String textoFinal = "";

        for (int vuelta = 0; vuelta < 6; vuelta++) {
            MessageCreateParams.Builder paramsBuilder = MessageCreateParams.builder()
                    .model(anthropicProperties.getModeloAgenteDatos())
                    .maxTokens(2048L)
                    .system(SYSTEM_PROMPT)
                    .messages(historial);
            AdminToolDefinitions.todas().forEach(paramsBuilder::addTool);
            MessageCreateParams params = paramsBuilder.build();

            Message respuesta = anthropicClient.messages().create(params);

            List<ContentBlockParam> bloquesAsistente = new ArrayList<>();
            List<ContentBlockParam> resultadosHerramientas = new ArrayList<>();
            StringBuilder textoVuelta = new StringBuilder();
            for (ContentBlock block : respuesta.content()) {
                if (block.text().isPresent()) {
                    textoVuelta.append(block.text().get().text());
                    bloquesAsistente.add(ContentBlockParam.ofText(
                            com.anthropic.models.messages.TextBlockParam.builder()
                                    .text(block.text().get().text()).build()));
                } else if (block.toolUse().isPresent()) {
                    ToolUseBlock toolUse = block.toolUse().get();
                    herramientasInvocadas.add(toolUse.name());
                    JsonNode input = parsearInput(toolUse);
                    String resultado = adminToolDispatcher.ejecutar(toolUse.name(), input);

                    bloquesAsistente.add(ContentBlockParam.ofToolUse(
                            com.anthropic.models.messages.ToolUseBlockParam.builder()
                                    .id(toolUse.id())
                                    .name(toolUse.name())
                                    .input(toolUse._input())
                                    .build()));
                    resultadosHerramientas.add(ContentBlockParam.ofToolResult(
                            ToolResultBlockParam.builder()
                                    .toolUseId(toolUse.id())
                                    .content(resultado)
                                    .build()));
                }
            }
            textoFinal = textoVuelta.toString();

            historial.add(MessageParam.builder()
                    .role(MessageParam.Role.ASSISTANT)
                    .contentOfBlockParams(bloquesAsistente)
                    .build());

            if (respuesta.stopReason().orElse(null) != StopReason.TOOL_USE || resultadosHerramientas.isEmpty()) {
                break;
            }

            historial.add(MessageParam.builder()
                    .role(MessageParam.Role.USER)
                    .contentOfBlockParams(resultadosHerramientas)
                    .build());
        }

        AgenteIaConsultaLog logGuardado = consultaLogRepository.save(AgenteIaConsultaLog.builder()
                .cveUsuario(cveUsuario != null ? cveUsuario : "desconocido")
                .pregunta(pregunta)
                .respuesta(textoFinal)
                .modelo(anthropicProperties.getModeloAgenteDatos())
                .toolCallsJson(escribirJsonSeguro(herramientasInvocadas))
                .build());

        return ChatAdminResponse.builder()
                .respuesta(textoFinal)
                .idConsultaLog(logGuardado.getIdConsulta())
                .build();
    }

    private JsonNode parsearInput(ToolUseBlock toolUse) {
        try {
            return objectMapper.readTree(toolUse._input().toString());
        } catch (Exception e) {
            log.error("No se pudo parsear el input de la herramienta {}: {}", toolUse.name(), e.getMessage());
            return objectMapper.createObjectNode();
        }
    }

    private String escribirJsonSeguro(Object valor) {
        try {
            return objectMapper.writeValueAsString(valor);
        } catch (Exception e) {
            return "[]";
        }
    }
}
