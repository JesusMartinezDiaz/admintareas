package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.config.CobranzaProperties;
import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;
import mx.com.biochemsystems.cobranzaia.domain.ReglaProntoPago;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusNotaCredito;
import mx.com.biochemsystems.cobranzaia.repository.ReglaProntoPagoRepository;
import mx.com.biochemsystems.cobranzaia.service.ProntoPagoRuleEngine;
import org.springframework.stereotype.Service;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.Comparator;
import java.util.List;

/**
 * Motor de reglas de "pronto pago". No existe un concepto equivalente en
 * apibackendventas (lo más cercano son las comisiones de cobranza
 * escalonadas por días, que benefician al agente, no al cliente) - esta
 * es lógica nueva y debe mantenerse simple y auditable: nunca aplica una
 * NDC directamente en el sistema de ventas, solo la PROPONE; la propia
 * regla decide si puede auto-aprobarse por monto o si requiere revisión
 * humana (ver cob_regla_pronto_pago.monto_max_autorizado_automatico).
 */
@Service
@RequiredArgsConstructor
public class ProntoPagoRuleEngineImpl implements ProntoPagoRuleEngine {

    private final ReglaProntoPagoRepository reglaProntoPagoRepository;
    private final CobranzaProperties cobranzaProperties;

    @Override
    public NotaCreditoGenerada evaluar(Long idCliente, Long idCompania, Long idRuta,
                                        Long idCargoOriginal, BigDecimal montoFactura,
                                        LocalDate fechaFactura, LocalDate fechaPago) {

        long diasParaPagar = ChronoUnit.DAYS.between(fechaFactura, fechaPago);

        List<ReglaProntoPago> reglasVigentes = reglaProntoPagoRepository
                .findByIdCompaniaAndActivoTrueAndVigenciaInicioLessThanEqual(idCompania, LocalDate.now())
                .stream()
                .filter(r -> r.getVigenciaFin() == null || !r.getVigenciaFin().isBefore(LocalDate.now()))
                .filter(r -> diasParaPagar <= r.getDiasMaxDesdeFactura())
                .filter(r -> aplicaAlCliente(r, idCliente, idRuta))
                .sorted(Comparator.comparing(ReglaProntoPago::getPctDescuento).reversed())
                .toList();

        if (reglasVigentes.isEmpty()) {
            return null;
        }

        ReglaProntoPago mejorRegla = reglasVigentes.get(0);
        BigDecimal montoDescuento = montoFactura
                .multiply(mejorRegla.getPctDescuento())
                .divide(BigDecimal.valueOf(100), 2, RoundingMode.HALF_UP);

        boolean autoAprobable = montoDescuento.compareTo(mejorRegla.getMontoMaxAutorizadoAutomatico()) <= 0
                && montoDescuento.compareTo(cobranzaProperties.getMontoMaxNotaCreditoAutoSinAprobacion()) <= 0;

        return NotaCreditoGenerada.builder()
                .idRegla(mejorRegla.getIdRegla())
                .idCliente(idCliente)
                .idCargoOriginal(idCargoOriginal)
                .montoDescuento(montoDescuento)
                .estatus(autoAprobable ? EstatusNotaCredito.PROPUESTA : EstatusNotaCredito.REQUIERE_APROBACION)
                .justificacionIa("Pago recibido a los " + diasParaPagar + " días de la factura (límite regla: "
                        + mejorRegla.getDiasMaxDesdeFactura() + " días, " + mejorRegla.getNombre() + ")")
                .build();
    }

    private boolean aplicaAlCliente(ReglaProntoPago regla, Long idCliente, Long idRuta) {
        return switch (regla.getAplicaA()) {
            case "CLIENTE" -> regla.getIdReferencia() != null && regla.getIdReferencia().equals(idCliente);
            case "RUTA" -> regla.getIdReferencia() != null && regla.getIdReferencia().equals(idRuta);
            default -> true; // TODOS
        };
    }
}
