package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;

@Entity
@Table(name = "cob_regla_pronto_pago")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class ReglaProntoPago {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_regla")
    private Long idRegla;

    @Column(name = "id_compania", nullable = false)
    private Long idCompania;

    @Column(name = "nombre", nullable = false, length = 150)
    private String nombre;

    @Column(name = "dias_max_desde_factura", nullable = false)
    private Integer diasMaxDesdeFactura;

    @Column(name = "pct_descuento", nullable = false)
    private BigDecimal pctDescuento;

    @Column(name = "monto_max_autorizado_automatico", nullable = false)
    private BigDecimal montoMaxAutorizadoAutomatico;

    @Column(name = "aplica_a", nullable = false, length = 15)
    private String aplicaA;

    @Column(name = "id_referencia")
    private Long idReferencia;

    @Column(name = "activo", nullable = false)
    private Boolean activo;

    @Column(name = "vigencia_inicio", nullable = false)
    private LocalDate vigenciaInicio;

    @Column(name = "vigencia_fin")
    private LocalDate vigenciaFin;

    @Column(name = "cve_usuario_creacion", length = 45)
    private String cveUsuarioCreacion;

    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @PrePersist
    void prePersist() {
        if (fechaCreacion == null) {
            fechaCreacion = LocalDateTime.now();
        }
        if (activo == null) {
            activo = true;
        }
        if (aplicaA == null) {
            aplicaA = "TODOS";
        }
    }
}
