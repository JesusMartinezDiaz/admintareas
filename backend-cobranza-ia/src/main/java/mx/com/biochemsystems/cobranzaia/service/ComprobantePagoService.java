package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.domain.ComprobantePago;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusComprobante;

import java.util.List;

public interface ComprobantePagoService {

    List<ComprobantePago> listarPorEstatus(EstatusComprobante estatus);

    /** Un humano confirma el comprobante detectado por la IA: aplica el pago real en ventas. */
    ComprobantePago aplicar(Long idComprobante, String cveUsuario);

    ComprobantePago rechazar(Long idComprobante, String motivo);
}
