package mx.com.biochemsystems.cobranzaia.controller;

import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.BloqueCliente;
import mx.com.biochemsystems.cobranzaia.domain.BloqueEnvio;
import mx.com.biochemsystems.cobranzaia.dto.AgregarClientesBloqueRequest;
import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;
import mx.com.biochemsystems.cobranzaia.dto.CrearBloqueRequest;
import mx.com.biochemsystems.cobranzaia.service.BloqueEnvioService;
import mx.com.biochemsystems.cobranzaia.service.CarteraConsultaService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

/**
 * Administración de bloques de envío de WhatsApp por ruta - la pantalla
 * principal que pide el negocio para curar, por bloques, a qué clientes
 * se les va a contactar.
 */
@RestController
@RequestMapping("/bloques")
@RequiredArgsConstructor
public class BloqueEnvioController {

    private final BloqueEnvioService bloqueEnvioService;
    private final CarteraConsultaService carteraConsultaService;

    @GetMapping("/cartera-vencida")
    public ResponseEntity<List<ClienteCarteraDTO>> carteraVencidaPorRuta(
            @RequestParam(required = false) Long idRuta,
            @RequestParam Long idCompania,
            @RequestParam(defaultValue = "1") int diasMinVencido) {
        return ResponseEntity.ok(carteraConsultaService.obtenerCarteraVencidaPorRuta(idRuta, idCompania, diasMinVencido));
    }

    @PostMapping
    public ResponseEntity<BloqueEnvio> crear(@Valid @RequestBody CrearBloqueRequest request,
                                              @RequestParam(required = false) String cveUsuario) {
        return ResponseEntity.ok(bloqueEnvioService.crearBloque(request, cveUsuario));
    }

    @GetMapping("/{idBloque}")
    public ResponseEntity<BloqueEnvio> obtener(@PathVariable Long idBloque) {
        return ResponseEntity.ok(bloqueEnvioService.obtenerBloque(idBloque));
    }

    @GetMapping("/{idBloque}/clientes")
    public ResponseEntity<List<BloqueCliente>> listarClientes(@PathVariable Long idBloque) {
        return ResponseEntity.ok(bloqueEnvioService.listarClientesDeBloque(idBloque));
    }

    @PostMapping("/{idBloque}/clientes")
    public ResponseEntity<List<BloqueCliente>> agregarClientes(
            @PathVariable Long idBloque, @RequestBody AgregarClientesBloqueRequest request) {
        boolean autocompletar = Boolean.TRUE.equals(request.getAutocompletarDesdeRuta());
        return ResponseEntity.ok(bloqueEnvioService.agregarClientes(
                idBloque, request.getIdsCliente(), autocompletar));
    }

    @DeleteMapping("/{idBloque}/clientes/{idCliente}")
    public ResponseEntity<Void> quitarCliente(@PathVariable Long idBloque, @PathVariable Long idCliente) {
        bloqueEnvioService.quitarCliente(idBloque, idCliente);
        return ResponseEntity.noContent().build();
    }

    @PostMapping("/{idBloque}/iniciar")
    public ResponseEntity<BloqueEnvio> iniciar(@PathVariable Long idBloque) {
        return ResponseEntity.ok(bloqueEnvioService.iniciarEnvio(idBloque));
    }

    @PostMapping("/{idBloque}/pausar")
    public ResponseEntity<BloqueEnvio> pausar(@PathVariable Long idBloque) {
        return ResponseEntity.ok(bloqueEnvioService.pausar(idBloque));
    }

    @GetMapping
    public ResponseEntity<List<BloqueEnvio>> listarPorRuta(@RequestParam Long idRuta) {
        return ResponseEntity.ok(bloqueEnvioService.listarPorRuta(idRuta));
    }
}
