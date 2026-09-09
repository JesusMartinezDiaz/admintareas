package mx.com.biochemsystems.cobranzaia.controller;

import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.dto.ChatAdminRequest;
import mx.com.biochemsystems.cobranzaia.dto.ChatAdminResponse;
import mx.com.biochemsystems.cobranzaia.service.CarteraAdminAiService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

/** Chat de "agente de datos" para que el administrador pregunte sobre la cartera en lenguaje natural. */
@RestController
@RequestMapping("/agente-ia")
@RequiredArgsConstructor
public class CarteraAdminAiController {

    private final CarteraAdminAiService carteraAdminAiService;

    @PostMapping("/chat")
    public ResponseEntity<ChatAdminResponse> chat(@Valid @RequestBody ChatAdminRequest request) {
        return ResponseEntity.ok(carteraAdminAiService.responder(request.getPregunta(), request.getCveUsuario()));
    }
}
