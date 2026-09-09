package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;

import java.math.BigDecimal;
import java.time.LocalDate;

public interface ProntoPagoRuleEngine {

    /**
     * Evalúa si un pago (fechaFactura -> fechaPago) califica para una nota
     * de crédito por pronto pago, según las reglas activas de
     * cob_regla_pronto_pago para la compañía/ruta/cliente indicados.
     *
     * Devuelve una NotaCreditoGenerada en estatus PROPUESTA (si califica y
     * el monto excede el tope de auto-aprobación => REQUIERE_APROBACION),
     * o null si ninguna regla aplica.
     */
    NotaCreditoGenerada evaluar(Long idCliente, Long idCompania, Long idRuta,
                                 Long idCargoOriginal, BigDecimal montoFactura,
                                 LocalDate fechaFactura, LocalDate fechaPago);
}
