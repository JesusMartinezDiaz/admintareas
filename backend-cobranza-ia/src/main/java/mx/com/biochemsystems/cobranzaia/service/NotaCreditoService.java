package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusNotaCredito;

import java.util.List;

public interface NotaCreditoService {

    List<NotaCreditoGenerada> listarPorEstatus(EstatusNotaCredito estatus);

    /** Un humano aprueba una NDC que excedía el tope de auto-aprobación: la aplica en ventas. */
    NotaCreditoGenerada aprobar(Long idNotaGenerada, String cveUsuario);

    NotaCreditoGenerada rechazar(Long idNotaGenerada, String cveUsuario);
}
