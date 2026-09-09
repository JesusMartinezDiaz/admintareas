package mx.com.biochemsystems.cobranzaia.service;

/** Punto de entrada del webhook de WhatsApp: procesa un mensaje entrante de un cliente. */
public interface ConversationOrchestratorService {

    /**
     * @param telefonoE164   teléfono del remitente, ya en formato WhatsApp (con código de país)
     * @param textoMensaje   texto del mensaje, o null si es solo media
     * @param mediaId        id de media de WhatsApp (imagen/documento de comprobante), o null
     * @param whatsappMsgId  id de mensaje de WhatsApp, para idempotencia
     */
    void procesarMensajeEntrante(String telefonoE164, String textoMensaje, String mediaId, String whatsappMsgId);
}
