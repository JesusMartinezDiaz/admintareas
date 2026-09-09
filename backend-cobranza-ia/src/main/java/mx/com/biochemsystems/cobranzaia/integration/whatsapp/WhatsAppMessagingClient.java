package mx.com.biochemsystems.cobranzaia.integration.whatsapp;

import java.util.List;
import java.util.Map;

/** Cliente hacia la WhatsApp Business Cloud API (Meta). */
public interface WhatsAppMessagingClient {

    /** Envía un mensaje de texto libre (solo dentro de la ventana de 24h post-respuesta del cliente). */
    String enviarTexto(String telefonoE164, String texto);

    /** Envía un mensaje de plantilla (HSM) aprobado, fuera o dentro de la ventana de 24h. */
    String enviarPlantilla(String telefonoE164, String codigoPlantilla, String idioma, List<String> parametros);

    /** Descarga la URL temporal de un media (imagen/documento de comprobante) recibido por webhook. */
    Map<String, Object> obtenerUrlMedia(String mediaId);

    byte[] descargarMedia(String urlMedia);
}
