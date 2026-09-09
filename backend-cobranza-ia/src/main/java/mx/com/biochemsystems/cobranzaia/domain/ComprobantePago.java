package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusComprobante;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;

@Entity
@Table(name = "cob_comprobante_pago")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class ComprobantePago {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_comprobante")
    private Long idComprobante;

    @Column(name = "id_mensaje", nullable = false)
    private Long idMensaje;

    @Column(name = "id_cliente", nullable = false)
    private Long idCliente;

    @Column(name = "monto_detectado")
    private BigDecimal montoDetectado;

    @Column(name = "fecha_pago_detectada")
    private LocalDate fechaPagoDetectada;

    @Column(name = "referencia_detectada", length = 100)
    private String referenciaDetectada;

    @Column(name = "banco_detectado", length = 100)
    private String bancoDetectado;

    @Column(name = "confianza_ia")
    private BigDecimal confianzaIa;

    @Enumerated(EnumType.STRING)
    @Column(name = "estatus", nullable = false, length = 25)
    private EstatusComprobante estatus;

    @Column(name = "id_abono_generado")
    private Long idAbonoGenerado;

    @Column(name = "observaciones_ia", length = 500)
    private String observacionesIa;

    @Column(name = "fecha_deteccion", nullable = false, updatable = false)
    private LocalDateTime fechaDeteccion;

    @Column(name = "fecha_aplicacion")
    private LocalDateTime fechaAplicacion;

    @PrePersist
    void prePersist() {
        if (fechaDeteccion == null) {
            fechaDeteccion = LocalDateTime.now();
        }
        if (estatus == null) {
            estatus = EstatusComprobante.PENDIENTE_VALIDACION;
        }
    }
}
