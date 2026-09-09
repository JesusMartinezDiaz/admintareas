package mx.com.biochemsystems.cobranzaia.service.impl;

import com.anthropic.client.AnthropicClient;
import com.anthropic.models.messages.Base64ImageSource;
import com.anthropic.models.messages.ContentBlock;
import com.anthropic.models.messages.ContentBlockParam;
import com.anthropic.models.messages.ImageBlockParam;
import com.anthropic.models.messages.Message;
import com.anthropic.models.messages.MessageCreateParams;
import com.anthropic.models.messages.MessageParam;
import com.anthropic.models.messages.StopReason;
import com.anthropic.models.messages.TextBlockParam;
import com.anthropic.models.messages.ToolResultBlockParam;
import com.anthropic.models.messages.ToolUseBlock;
import com.anthropic.models.messages.ToolUseBlockParam;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.config.AnthropicProperties;
import mx.com.biochemsystems.cobranzaia.config.CobranzaProperties;
import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.Mensaje;
import mx.com.biochemsystems.cobranzaia.domain.enums.DireccionMensaje;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusConversacion;
import mx.com.biochemsystems.cobranzaia.domain.enums.GeneradoPor;
import mx.com.biochemsystems.cobranzaia.domain.enums.TipoContenidoMensaje;
import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;
import mx.com.biochemsystems.cobranzaia.integration.whatsapp.WhatsAppMessagingClient;
import mx.com.biochemsystems.cobranzaia.repository.ConversacionRepository;
import mx.com.biochemsystems.cobranzaia.repository.MensajeRepository;
import mx.com.biochemsystems.cobranzaia.service.CarteraConsultaService;
import mx.com.biochemsystems.cobranzaia.service.ConversationOrchestratorService;
import mx.com.biochemsystems.cobranzaia.tools.ConversationToolDefinitions;
import mx.com.biochemsystems.cobranzaia.tools.ConversationToolDispatcher;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.ArrayList;
import java.util.Base64;
import java.util.List;
import java.util.Map;
import java.util.Optional;

/**
 * Orquesta una conversación de cobranza por WhatsApp: recibe el mensaje
 * entrante, arma el contexto (saldo, historial) y deja que Claude
 * conduzca la plática con tool use, hasta producir una respuesta de
 * texto que se reenvía al cliente.
 *
 * Ver nota de compilación en CarteraAdminAiServiceImpl sobre los
 * accessors de ToolUseBlock; aquí aplica lo mismo, más
 * Base64ImageSource/ImageBlockParam para el comprobante de pago, cuyo
 * nombre exacto se infirió por analogía con Base64PdfSource (documentado)
 * y debe confirmarse con `mvn -q compile`.
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class ConversationOrchestratorServiceImpl implements ConversationOrchestratorService {

    private static final String SYSTEM_PROMPT_TEMPLATE = """
            Eres el agente virtual de cobranza de Biochem Systems, hablando por WhatsApp
            con %s (cliente #%s). Tu saldo vencido actual es $%s MXN.

            Reglas estrictas:
            - Sé cordial y profesional, nunca amenazante.
            - Tu objetivo es que el cliente reconozca su saldo y acuerde una fecha/forma de pago.
            - Si el cliente envía un comprobante de pago (imagen/documento), analízalo y usa la
              herramienta registrar_comprobante_pago con lo que puedas leer (monto, fecha, referencia,
              banco) y tu nivel de confianza. Nunca digas que el pago "ya quedó aplicado": siempre
              aclara que será validado.
            - Si el cliente pide un descuento por pago anticipado o dice que pagará pronto, usa
              evaluar_pronto_pago ANTES de prometer cualquier descuento. Nunca ofrezcas un
              porcentaje que no haya salido de esa herramienta.
            - Si el cliente dice que no debe nada, que no reconoce la deuda, que no es el cliente
              correcto, o algo no cuadra (nombre distinto, monto absurdo, etc.), usa
              marcar_alerta_cliente y sigue la conversación con cautela, sin acusarlo directamente.
            - Si la negociación se sale de lo que puedes decidir, o el cliente pide hablar con una
              persona, usa escalar_atencion_humana y avísale que un cobrador humano continuará.
            - Responde siempre en español, en mensajes cortos (son WhatsApp, no correos).
            """;

    private final AnthropicClient anthropicClient;
    private final AnthropicProperties anthropicProperties;
    private final CobranzaProperties cobranzaProperties;
    private final CarteraConsultaService carteraConsultaService;
    private final ConversacionRepository conversacionRepository;
    private final MensajeRepository mensajeRepository;
    private final WhatsAppMessagingClient whatsAppMessagingClient;
    private final ConversationToolDispatcher conversationToolDispatcher;
    private final ObjectMapper objectMapper;

    @Override
    @Transactional
    public void procesarMensajeEntrante(String telefonoE164, String textoMensaje, String mediaId, String whatsappMsgId) {
        Optional<ClienteCarteraDTO> clienteOpt = carteraConsultaService
                .obtenerClientePorTelefono(telefonoE164, cobranzaProperties.getCompaniaDefault());

        if (clienteOpt.isEmpty()) {
            log.warn("Mensaje de teléfono no identificado como cliente: {}. Se ignora (no se auto-responde a desconocidos).",
                    telefonoE164);
            return;
        }
        ClienteCarteraDTO cliente = clienteOpt.get();

        Conversacion conversacion = conversacionRepository
                .findFirstByTelefonoE164AndEstatusNotOrderByFechaAperturaDesc(telefonoE164, EstatusConversacion.CERRADA)
                .orElseGet(() -> conversacionRepository.save(Conversacion.builder()
                        .idCliente(cliente.getIdCliente())
                        .telefonoE164(telefonoE164)
                        .saldoAlAbrir(cliente.getSaldoTotal())
                        .build()));

        if (Boolean.TRUE.equals(conversacion.getRequiereAtencionHumana())) {
            // Un humano ya tomó la conversación; el agente no responde encima.
            guardarMensajeEntrante(conversacion, textoMensaje, mediaId, whatsappMsgId);
            return;
        }

        Mensaje mensajeEntrante = guardarMensajeEntrante(conversacion, textoMensaje, mediaId, whatsappMsgId);

        String respuestaTexto = ejecutarTurnoConversacional(conversacion, cliente, mensajeEntrante, textoMensaje, mediaId);

        if (respuestaTexto != null && !respuestaTexto.isBlank()) {
            String whatsappMessageIdSalida = whatsAppMessagingClient.enviarTexto(telefonoE164, respuestaTexto);
            mensajeRepository.save(Mensaje.builder()
                    .idConversacion(conversacion.getIdConversacion())
                    .direccion(DireccionMensaje.SALIENTE)
                    .tipoContenido(TipoContenidoMensaje.TEXTO)
                    .contenidoTexto(respuestaTexto)
                    .whatsappMessageId(whatsappMessageIdSalida)
                    .generadoPor(GeneradoPor.IA)
                    .build());
        }

        conversacion.setFechaUltimaActividad(java.time.LocalDateTime.now());
        conversacionRepository.save(conversacion);
    }

    private Mensaje guardarMensajeEntrante(Conversacion conversacion, String texto, String mediaId, String whatsappMsgId) {
        return mensajeRepository.save(Mensaje.builder()
                .idConversacion(conversacion.getIdConversacion())
                .direccion(DireccionMensaje.ENTRANTE)
                .tipoContenido(mediaId != null ? TipoContenidoMensaje.IMAGEN : TipoContenidoMensaje.TEXTO)
                .contenidoTexto(texto)
                .whatsappMessageId(whatsappMsgId)
                .generadoPor(GeneradoPor.CLIENTE)
                .build());
    }

    private String ejecutarTurnoConversacional(Conversacion conversacion, ClienteCarteraDTO cliente,
                                                Mensaje mensajeEntrante, String textoMensaje, String mediaId) {
        String systemPrompt = SYSTEM_PROMPT_TEMPLATE.formatted(
                cliente.getNombreComercial() != null ? cliente.getNombreComercial() : cliente.getNombreCliente(),
                cliente.getIdCliente(),
                cliente.getSaldoTotal() != null ? cliente.getSaldoTotal().toPlainString() : "0.00");

        List<MessageParam> historial = reconstruirHistorial(conversacion, textoMensaje, mediaId);

        String textoFinal = "";
        for (int vuelta = 0; vuelta < 6; vuelta++) {
            MessageCreateParams.Builder paramsBuilder = MessageCreateParams.builder()
                    .model(anthropicProperties.getModeloConversacional())
                    .maxTokens(1024L)
                    .system(systemPrompt)
                    .messages(historial);
            ConversationToolDefinitions.todas().forEach(paramsBuilder::addTool);

            Message respuesta = anthropicClient.messages().create(paramsBuilder.build());

            List<ContentBlockParam> bloquesAsistente = new ArrayList<>();
            List<ContentBlockParam> resultadosHerramientas = new ArrayList<>();
            StringBuilder textoVuelta = new StringBuilder();

            for (ContentBlock block : respuesta.content()) {
                if (block.text().isPresent()) {
                    textoVuelta.append(block.text().get().text());
                    bloquesAsistente.add(ContentBlockParam.ofText(
                            TextBlockParam.builder().text(block.text().get().text()).build()));
                } else if (block.toolUse().isPresent()) {
                    ToolUseBlock toolUse = block.toolUse().get();
                    JsonNode input = parsearInput(toolUse);
                    String resultado = conversationToolDispatcher.ejecutar(
                            toolUse.name(), input, conversacion, mensajeEntrante.getIdMensaje());

                    bloquesAsistente.add(ContentBlockParam.ofToolUse(ToolUseBlockParam.builder()
                            .id(toolUse.id())
                            .name(toolUse.name())
                            .input(toolUse._input())
                            .build()));
                    resultadosHerramientas.add(ContentBlockParam.ofToolResult(ToolResultBlockParam.builder()
                            .toolUseId(toolUse.id())
                            .content(resultado)
                            .build()));
                }
            }
            textoFinal = textoVuelta.toString();

            historial.add(MessageParam.builder().role(MessageParam.Role.ASSISTANT)
                    .contentOfBlockParams(bloquesAsistente).build());

            if (respuesta.stopReason().orElse(null) != StopReason.TOOL_USE || resultadosHerramientas.isEmpty()) {
                break;
            }
            historial.add(MessageParam.builder().role(MessageParam.Role.USER)
                    .contentOfBlockParams(resultadosHerramientas).build());
        }
        return textoFinal;
    }

    /**
     * Reconstruye el historial en formato Claude a partir de cob_mensaje.
     * Simplificado a texto para mensajes previos; el mensaje ENTRANTE actual
     * incluye la imagen (si la hay) como content block de visión.
     */
    private List<MessageParam> reconstruirHistorial(Conversacion conversacion, String textoActual, String mediaId) {
        List<MessageParam> historial = new ArrayList<>();
        List<Mensaje> previos = mensajeRepository.findByIdConversacionOrderByFechaCreacionAsc(conversacion.getIdConversacion());

        for (Mensaje m : previos.subList(0, Math.max(0, previos.size() - 1))) {
            if (m.getContenidoTexto() == null) {
                continue;
            }
            MessageParam.Role role = m.getDireccion() == DireccionMensaje.ENTRANTE
                    ? MessageParam.Role.USER : MessageParam.Role.ASSISTANT;
            historial.add(MessageParam.builder().role(role).content(m.getContenidoTexto()).build());
        }

        if (mediaId != null) {
            byte[] media = descargarMediaSeguro(mediaId);
            List<ContentBlockParam> contenido = new ArrayList<>();
            if (media != null) {
                contenido.add(ContentBlockParam.ofImage(ImageBlockParam.builder()
                        .source(Base64ImageSource.builder()
                                .mediaType(Base64ImageSource.MediaType.IMAGE_JPEG)
                                .data(Base64.getEncoder().encodeToString(media))
                                .build())
                        .build()));
            }
            contenido.add(ContentBlockParam.ofText(TextBlockParam.builder()
                    .text(textoActual != null ? textoActual : "(cliente envió un comprobante de pago)")
                    .build()));
            historial.add(MessageParam.builder().role(MessageParam.Role.USER)
                    .contentOfBlockParams(contenido).build());
        } else {
            historial.add(MessageParam.builder().role(MessageParam.Role.USER)
                    .content(textoActual != null ? textoActual : "").build());
        }
        return historial;
    }

    private byte[] descargarMediaSeguro(String mediaId) {
        try {
            Map<String, Object> info = whatsAppMessagingClient.obtenerUrlMedia(mediaId);
            String url = info != null ? (String) info.get("url") : null;
            return url != null ? whatsAppMessagingClient.descargarMedia(url) : null;
        } catch (Exception e) {
            log.error("No se pudo descargar el media {} de WhatsApp: {}", mediaId, e.getMessage());
            return null;
        }
    }

    private JsonNode parsearInput(ToolUseBlock toolUse) {
        try {
            return objectMapper.readTree(toolUse._input().toString());
        } catch (Exception e) {
            log.error("No se pudo parsear el input de la herramienta {}: {}", toolUse.name(), e.getMessage());
            return objectMapper.createObjectNode();
        }
    }
}
