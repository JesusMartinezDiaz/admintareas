package mx.com.biochemsystems.cobranzaia.controller;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusNotaCredito;
import mx.com.biochemsystems.cobranzaia.service.NotaCreditoService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

/**
 * Notas de crédito por pronto pago que la IA propuso pero no pudo (o no
 * debía) aplicar sola - porque exceden el tope de auto-aprobación, o
 * porque no se identificó la factura. Un humano aprueba o rechaza.
 */
@RestController
@RequestMapping("/notas-credito")
@RequiredArgsConstructor
public class NotaCreditoController {

    private final NotaCreditoService notaCreditoService;

    @GetMapping
    public ResponseEntity<List<NotaCreditoGenerada>> listar(
            @RequestParam(defaultValue = "REQUIERE_APROBACION") EstatusNotaCredito estatus) {
        return ResponseEntity.ok(notaCreditoService.listarPorEstatus(estatus));
    }

    @PostMapping("/{idNotaGenerada}/aprobar")
    public ResponseEntity<NotaCreditoGenerada> aprobar(@PathVariable Long idNotaGenerada, @RequestParam String cveUsuario) {
        return ResponseEntity.ok(notaCreditoService.aprobar(idNotaGenerada, cveUsuario));
    }

    @PostMapping("/{idNotaGenerada}/rechazar")
    public ResponseEntity<NotaCreditoGenerada> rechazar(@PathVariable Long idNotaGenerada, @RequestParam String cveUsuario) {
        return ResponseEntity.ok(notaCreditoService.rechazar(idNotaGenerada, cveUsuario));
    }
}
