package mx.com.biochemsystems.cobranzaia.controller;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.ComprobantePago;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusComprobante;
import mx.com.biochemsystems.cobranzaia.service.ComprobantePagoService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

/**
 * Bandeja de comprobantes de pago que la IA detectó en fotos/documentos
 * de WhatsApp. Ninguno se aplica solo: un humano confirma (o rechaza)
 * cada uno desde aquí antes de que se registre el pago real en ventas.
 */
@RestController
@RequestMapping("/comprobantes")
@RequiredArgsConstructor
public class ComprobantePagoController {

    private final ComprobantePagoService comprobantePagoService;

    @GetMapping
    public ResponseEntity<List<ComprobantePago>> listar(
            @RequestParam(defaultValue = "PENDIENTE_VALIDACION") EstatusComprobante estatus) {
        return ResponseEntity.ok(comprobantePagoService.listarPorEstatus(estatus));
    }

    @PostMapping("/{idComprobante}/aplicar")
    public ResponseEntity<ComprobantePago> aplicar(@PathVariable Long idComprobante, @RequestParam String cveUsuario) {
        return ResponseEntity.ok(comprobantePagoService.aplicar(idComprobante, cveUsuario));
    }

    @PostMapping("/{idComprobante}/rechazar")
    public ResponseEntity<ComprobantePago> rechazar(@PathVariable Long idComprobante, @RequestParam String motivo) {
        return ResponseEntity.ok(comprobantePagoService.rechazar(idComprobante, motivo));
    }
}
