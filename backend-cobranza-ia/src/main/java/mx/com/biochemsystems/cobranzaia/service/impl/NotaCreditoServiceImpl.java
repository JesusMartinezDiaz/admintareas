package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusNotaCredito;
import mx.com.biochemsystems.cobranzaia.integration.ventas.VentasApiClient;
import mx.com.biochemsystems.cobranzaia.repository.NotaCreditoGeneradaRepository;
import mx.com.biochemsystems.cobranzaia.service.NotaCreditoService;
import org.springframework.stereotype.Service;

import java.time.LocalDateTime;
import java.util.List;

@Service
@RequiredArgsConstructor
public class NotaCreditoServiceImpl implements NotaCreditoService {

    private final NotaCreditoGeneradaRepository notaCreditoGeneradaRepository;
    private final VentasApiClient ventasApiClient;

    @Override
    public List<NotaCreditoGenerada> listarPorEstatus(EstatusNotaCredito estatus) {
        return notaCreditoGeneradaRepository.findByEstatus(estatus);
    }

    @Override
    public NotaCreditoGenerada aprobar(Long idNotaGenerada, String cveUsuario) {
        NotaCreditoGenerada nota = notaCreditoGeneradaRepository.findById(idNotaGenerada)
                .orElseThrow(() -> new IllegalArgumentException("Nota de crédito no encontrada: " + idNotaGenerada));

        if (nota.getEstatus() != EstatusNotaCredito.REQUIERE_APROBACION) {
            throw new IllegalStateException("La nota ya fue resuelta (" + nota.getEstatus() + ")");
        }
        if (nota.getIdCargoOriginal() == null) {
            throw new IllegalStateException(
                    "La nota no tiene una factura asociada (idCargoOriginal); captúrala antes de aprobar");
        }

        Long idAbono = ventasApiClient.aplicarNotaCreditoProntoPago(
                nota.getIdCargoOriginal(), nota.getMontoDescuento(), cveUsuario);

        nota.setIdAbonoNdc(idAbono);
        nota.setEstatus(EstatusNotaCredito.APLICADA);
        nota.setCveUsuarioAprobo(cveUsuario);
        nota.setFechaResolucion(LocalDateTime.now());
        return notaCreditoGeneradaRepository.save(nota);
    }

    @Override
    public NotaCreditoGenerada rechazar(Long idNotaGenerada, String cveUsuario) {
        NotaCreditoGenerada nota = notaCreditoGeneradaRepository.findById(idNotaGenerada)
                .orElseThrow(() -> new IllegalArgumentException("Nota de crédito no encontrada: " + idNotaGenerada));
        nota.setEstatus(EstatusNotaCredito.RECHAZADA_HUMANO);
        nota.setCveUsuarioAprobo(cveUsuario);
        nota.setFechaResolucion(LocalDateTime.now());
        return notaCreditoGeneradaRepository.save(nota);
    }
}
