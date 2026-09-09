package mx.com.biochemsystems.cobranzaia.controller;

import com.fasterxml.jackson.databind.JsonNode;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.config.WhatsAppProperties;
import mx.com.biochemsystems.cobranzaia.service.ConversationOrchestratorService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.Iterator;

/**
 * Webhook de WhatsApp Business Cloud API.
 * https://developers.facebook.com/docs/graph-api/webhooks/getting-started
 *
 * GET  -> verificación inicial (hub.challenge).
 * POST -> eventos de mensajes/estatus entrantes.
 *
 * TODO seguridad: validar la firma X-Hub-Signature-256 contra
 * whatsapp.cloud-api.app-secret antes de procesar el payload (no
 * implementado en este scaffold).
 */
@Slf4j
@RestController
@RequestMapping("/whatsapp/webhook")
@RequiredArgsConstructor
public class WhatsAppWebhookController {

    private final WhatsAppProperties whatsAppProperties;
    private final ConversationOrchestratorService conversationOrchestratorService;

    @GetMapping
    public ResponseEntity<String> verificar(
            @RequestParam("hub.mode") String modo,
            @RequestParam("hub.verify_token") String token,
            @RequestParam("hub.challenge") String challenge) {
        if ("subscribe".equals(modo) && whatsAppProperties.getWebhookVerifyToken().equals(token)) {
            return ResponseEntity.ok(challenge);
        }
        return ResponseEntity.status(403).build();
    }

    @PostMapping
    public ResponseEntity<Void> recibirEvento(@RequestBody JsonNode payload) {
        try {
            procesarPayload(payload);
        } catch (Exception e) {
            log.error("Error procesando webhook de WhatsApp: {}", e.getMessage(), e);
        }
        // WhatsApp exige 200 rápido; el procesamiento pesado ya ocurrió arriba de forma síncrona
        // en este scaffold. Para volumen alto, mover a una cola async antes de responder.
        return ResponseEntity.ok().build();
    }

    private void procesarPayload(JsonNode payload) {
        Iterator<JsonNode> entradas = payload.path("entry").elements();
        while (entradas.hasNext()) {
            Iterator<JsonNode> cambios = entradas.next().path("changes").elements();
            while (cambios.hasNext()) {
                JsonNode valor = cambios.next().path("value");
                Iterator<JsonNode> mensajes = valor.path("messages").elements();
                while (mensajes.hasNext()) {
                    procesarMensaje(mensajes.next());
                }
                // valor.path("statuses") trae confirmaciones de entrega/lectura de mensajes
                // salientes; se puede usar para actualizar cob_mensaje.whatsapp_status.
            }
        }
    }

    private void procesarMensaje(JsonNode mensaje) {
        String from = mensaje.path("from").asText(null);
        String whatsappMsgId = mensaje.path("id").asText(null);
        String tipo = mensaje.path("type").asText("text");

        String texto = null;
        String mediaId = null;
        switch (tipo) {
            case "text" -> texto = mensaje.path("text").path("body").asText(null);
            case "image" -> mediaId = mensaje.path("image").path("id").asText(null);
            case "document" -> mediaId = mensaje.path("document").path("id").asText(null);
            default -> log.info("Tipo de mensaje de WhatsApp no manejado: {}", tipo);
        }

        if (from != null) {
            conversationOrchestratorService.procesarMensajeEntrante(from, texto, mediaId, whatsappMsgId);
        }
    }
}
