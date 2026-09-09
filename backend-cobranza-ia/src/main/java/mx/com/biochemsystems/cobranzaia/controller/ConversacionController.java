package mx.com.biochemsystems.cobranzaia.controller;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.Mensaje;
import mx.com.biochemsystems.cobranzaia.service.ConversacionService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

/** Panel de supervisión: conversaciones de WhatsApp que la IA escaló a un humano. */
@RestController
@RequestMapping("/conversaciones")
@RequiredArgsConstructor
public class ConversacionController {

    private final ConversacionService conversacionService;

    @GetMapping("/pendientes-atencion")
    public ResponseEntity<List<Conversacion>> pendientesAtencion() {
        return ResponseEntity.ok(conversacionService.listarQueRequierenAtencion());
    }

    @GetMapping("/{idConversacion}/mensajes")
    public ResponseEntity<List<Mensaje>> mensajes(@PathVariable Long idConversacion) {
        return ResponseEntity.ok(conversacionService.listarMensajes(idConversacion));
    }

    @PostMapping("/{idConversacion}/tomar")
    public ResponseEntity<Conversacion> tomar(@PathVariable Long idConversacion, @RequestParam Long idAgente) {
        return ResponseEntity.ok(conversacionService.tomarConversacion(idConversacion, idAgente));
    }

    @PostMapping("/{idConversacion}/cerrar")
    public ResponseEntity<Conversacion> cerrar(@PathVariable Long idConversacion) {
        return ResponseEntity.ok(conversacionService.cerrarConversacion(idConversacion));
    }
}
