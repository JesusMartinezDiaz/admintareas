package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusNotaCredito;

import java.math.BigDecimal;
import java.time.LocalDateTime;

@Entity
@Table(name = "cob_nota_credito_generada")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class NotaCreditoGenerada {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_nota_generada")
    private Long idNotaGenerada;

    @Column(name = "id_regla", nullable = false)
    private Long idRegla;

    @Column(name = "id_cliente", nullable = false)
    private Long idCliente;

    @Column(name = "id_conversacion")
    private Long idConversacion;

    @Column(name = "id_cargo_original")
    private Long idCargoOriginal;

    @Column(name = "id_abono_ndc")
    private Long idAbonoNdc;

    @Column(name = "monto_descuento", nullable = false)
    private BigDecimal montoDescuento;

    @Enumerated(EnumType.STRING)
    @Column(name = "estatus", nullable = false, length = 25)
    private EstatusNotaCredito estatus;

    @Column(name = "justificacion_ia", length = 500)
    private String justificacionIa;

    @Column(name = "cve_usuario_aprobo", length = 45)
    private String cveUsuarioAprobo;

    @Column(name = "fecha_generacion", nullable = false, updatable = false)
    private LocalDateTime fechaGeneracion;

    @Column(name = "fecha_resolucion")
    private LocalDateTime fechaResolucion;

    @PrePersist
    void prePersist() {
        if (fechaGeneracion == null) {
            fechaGeneracion = LocalDateTime.now();
        }
        if (estatus == null) {
            estatus = EstatusNotaCredito.PROPUESTA;
        }
    }
}
