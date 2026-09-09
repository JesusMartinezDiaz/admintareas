package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloqueCliente;

import java.math.BigDecimal;
import java.time.LocalDateTime;

@Entity
@Table(name = "cob_bloque_cliente")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class BloqueCliente {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_bloque_cliente")
    private Long idBloqueCliente;

    @Column(name = "id_bloque", nullable = false)
    private Long idBloque;

    @Column(name = "id_cliente", nullable = false)
    private Long idCliente;

    @Column(name = "id_telefono")
    private Long idTelefono;

    @Column(name = "saldo_snapshot")
    private BigDecimal saldoSnapshot;

    @Column(name = "dias_vencido_snapshot")
    private Integer diasVencidoSnapshot;

    @Column(name = "orden_envio", nullable = false)
    private Integer ordenEnvio;

    @Enumerated(EnumType.STRING)
    @Column(name = "estatus", nullable = false, length = 20)
    private EstatusBloqueCliente estatus;

    @Column(name = "motivo_exclusion", length = 255)
    private String motivoExclusion;

    @Column(name = "fecha_envio")
    private LocalDateTime fechaEnvio;

    @Column(name = "fecha_actualizacion")
    private LocalDateTime fechaActualizacion;

    @PrePersist
    void prePersist() {
        if (estatus == null) {
            estatus = EstatusBloqueCliente.PENDIENTE;
        }
        if (ordenEnvio == null) {
            ordenEnvio = 0;
        }
    }
}
