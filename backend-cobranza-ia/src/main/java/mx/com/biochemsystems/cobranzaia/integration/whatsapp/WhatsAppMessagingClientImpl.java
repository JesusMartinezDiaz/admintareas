package mx.com.biochemsystems.cobranzaia.integration.whatsapp;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.config.WhatsAppProperties;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.stereotype.Component;
import org.springframework.web.reactive.function.client.WebClient;

import java.util.List;
import java.util.Map;

/**
 * Implementación contra la Cloud API de WhatsApp Business
 * (https://developers.facebook.com/docs/whatsapp/cloud-api).
 *
 * Requiere en application.yml / variables de entorno:
 *  - WHATSAPP_PHONE_NUMBER_ID
 *  - WHATSAPP_ACCESS_TOKEN (token permanente de una System User de Meta)
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class WhatsAppMessagingClientImpl implements WhatsAppMessagingClient {

    @Qualifier("whatsAppWebClient")
    private final WebClient whatsAppWebClient;
    private final WhatsAppProperties properties;

    @Override
    public String enviarTexto(String telefonoE164, String texto) {
        Map<String, Object> payload = Map.of(
                "messaging_product", "whatsapp",
                "to", telefonoE164,
                "type", "text",
                "text", Map.of("body", texto)
        );
        return enviarYObtenerMessageId(payload);
    }

    @Override
    public String enviarPlantilla(String telefonoE164, String codigoPlantilla, String idioma, List<String> parametros) {
        List<Map<String, Object>> componentes = List.of(Map.of(
                "type", "body",
                "parameters", parametros.stream()
                        .map(p -> Map.of("type", "text", "text", p))
                        .toList()
        ));
        Map<String, Object> payload = Map.of(
                "messaging_product", "whatsapp",
                "to", telefonoE164,
                "type", "template",
                "template", Map.of(
                        "name", codigoPlantilla,
                        "language", Map.of("code", idioma),
                        "components", componentes
                )
        );
        return enviarYObtenerMessageId(payload);
    }

    @SuppressWarnings("unchecked")
    private String enviarYObtenerMessageId(Map<String, Object> payload) {
        Map<String, Object> respuesta = whatsAppWebClient.post()
                .uri("/{phoneNumberId}/messages", properties.getPhoneNumberId())
                .bodyValue(payload)
                .retrieve()
                .bodyToMono(Map.class)
                .block();

        if (respuesta == null || !respuesta.containsKey("messages")) {
            log.error("Respuesta inesperada de WhatsApp Cloud API: {}", respuesta);
            return null;
        }
        List<Map<String, Object>> mensajes = (List<Map<String, Object>>) respuesta.get("messages");
        return mensajes.isEmpty() ? null : (String) mensajes.get(0).get("id");
    }

    @Override
    public Map<String, Object> obtenerUrlMedia(String mediaId) {
        return whatsAppWebClient.get()
                .uri("/{mediaId}", mediaId)
                .retrieve()
                .bodyToMono(Map.class)
                .block();
    }

    @Override
    public byte[] descargarMedia(String urlMedia) {
        return whatsAppWebClient.get()
                .uri(urlMedia)
                .retrieve()
                .bodyToMono(byte[].class)
                .block();
    }
}
