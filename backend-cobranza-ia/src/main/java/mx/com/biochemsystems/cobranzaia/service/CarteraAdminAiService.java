package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.dto.ChatAdminResponse;

public interface CarteraAdminAiService {

    /** Responde una pregunta en lenguaje natural del administrador sobre la cartera, usando tool use. */
    ChatAdminResponse responder(String pregunta, String cveUsuario);
}
