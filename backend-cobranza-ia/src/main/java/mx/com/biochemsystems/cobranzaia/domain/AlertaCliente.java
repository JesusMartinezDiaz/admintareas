package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusAlerta;
import mx.com.biochemsystems.cobranzaia.domain.enums.TipoAlerta;

import java.time.LocalDateTime;

@Entity
@Table(name = "cob_alerta_cliente")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class AlertaCliente {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_alerta")
    private Long idAlerta;

    @Column(name = "id_cliente", nullable = false)
    private Long idCliente;

    @Column(name = "id_conversacion")
    private Long idConversacion;

    @Enumerated(EnumType.STRING)
    @Column(name = "tipo", nullable = false, length = 30)
    private TipoAlerta tipo;

    @Column(name = "detalle", length = 500)
    private String detalle;

    @Column(name = "origen", nullable = false, length = 10)
    private String origen;

    @Enumerated(EnumType.STRING)
    @Column(name = "estatus", nullable = false, length = 20)
    private EstatusAlerta estatus;

    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @Column(name = "fecha_resolucion")
    private LocalDateTime fechaResolucion;

    @PrePersist
    void prePersist() {
        if (fechaCreacion == null) {
            fechaCreacion = LocalDateTime.now();
        }
        if (estatus == null) {
            estatus = EstatusAlerta.ABIERTA;
        }
        if (origen == null) {
            origen = "IA";
        }
    }
}
