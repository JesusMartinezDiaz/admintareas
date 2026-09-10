package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.config.CobranzaProperties;
import mx.com.biochemsystems.cobranzaia.domain.ComprobantePago;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusComprobante;
import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;
import mx.com.biochemsystems.cobranzaia.integration.ventas.VentasApiClient;
import mx.com.biochemsystems.cobranzaia.repository.ComprobantePagoRepository;
import mx.com.biochemsystems.cobranzaia.service.CarteraConsultaService;
import mx.com.biochemsystems.cobranzaia.service.ComprobantePagoService;
import org.springframework.stereotype.Service;

import java.time.LocalDateTime;
import java.util.List;

/**
 * Un comprobante detectado por visión NUNCA se aplica solo: siempre pasa
 * por aquí, invocado por un humano desde el panel (ver ComprobantePagoController).
 * El riesgo de que la IA lea mal un monto/referencia en una foto es
 * demasiado alto para saltarse esa revisión.
 */
@Service
@RequiredArgsConstructor
public class ComprobantePagoServiceImpl implements ComprobantePagoService {

    private final ComprobantePagoRepository comprobantePagoRepository;
    private final CarteraConsultaService carteraConsultaService;
    private final VentasApiClient ventasApiClient;
    private final CobranzaProperties cobranzaProperties;

    @Override
    public List<ComprobantePago> listarPorEstatus(EstatusComprobante estatus) {
        return comprobantePagoRepository.findByEstatus(estatus);
    }

    @Override
    public ComprobantePago aplicar(Long idComprobante, String cveUsuario) {
        ComprobantePago comprobante = comprobantePagoRepository.findById(idComprobante)
                .orElseThrow(() -> new IllegalArgumentException("Comprobante no encontrado: " + idComprobante));

        if (comprobante.getEstatus() != EstatusComprobante.PENDIENTE_VALIDACION) {
            throw new IllegalStateException("El comprobante ya fue resuelto (" + comprobante.getEstatus() + ")");
        }
        if (comprobante.getMontoDetectado() == null) {
            throw new IllegalStateException("El comprobante no tiene un monto detectado, no se puede aplicar");
        }

        Long idCompania = cobranzaProperties.getCompaniaDefault();
        ClienteCarteraDTO cliente = carteraConsultaService.obtenerClientePorId(comprobante.getIdCliente(), idCompania)
                .orElseThrow(() -> new IllegalStateException("No se encontró el cliente del comprobante"));

        Long idAbono = ventasApiClient.registrarPago(
                comprobante.getIdCliente(),
                idCompania,
                cliente.getIdAgente(),
                comprobante.getMontoDetectado(),
                cveUsuario,
                comprobante.getReferenciaDetectada(),
                "Pago aplicado vía comprobante de WhatsApp, confirmado por " + cveUsuario);

        comprobante.setIdAbonoGenerado(idAbono);
        comprobante.setEstatus(EstatusComprobante.APLICADO);
        comprobante.setFechaAplicacion(LocalDateTime.now());
        return comprobantePagoRepository.save(comprobante);
    }

    @Override
    public ComprobantePago rechazar(Long idComprobante, String motivo) {
        ComprobantePago comprobante = comprobantePagoRepository.findById(idComprobante)
                .orElseThrow(() -> new IllegalArgumentException("Comprobante no encontrado: " + idComprobante));
        comprobante.setEstatus(EstatusComprobante.RECHAZADO);
        comprobante.setObservacionesIa((comprobante.getObservacionesIa() != null ? comprobante.getObservacionesIa() + " | " : "")
                + "Rechazado: " + motivo);
        return comprobantePagoRepository.save(comprobante);
    }
}
