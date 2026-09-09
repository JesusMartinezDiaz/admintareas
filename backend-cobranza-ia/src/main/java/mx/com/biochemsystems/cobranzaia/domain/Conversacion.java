package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusConversacion;

import java.math.BigDecimal;
import java.time.LocalDateTime;

@Entity
@Table(name = "cob_conversacion")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class Conversacion {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_conversacion")
    private Long idConversacion;

    @Column(name = "id_cliente", nullable = false)
    private Long idCliente;

    @Column(name = "id_bloque")
    private Long idBloque;

    @Column(name = "telefono_e164", nullable = false, length = 20)
    private String telefonoE164;

    @Enumerated(EnumType.STRING)
    @Column(name = "estatus", nullable = false, length = 25)
    private EstatusConversacion estatus;

    @Column(name = "requiere_atencion_humana", nullable = false)
    private Boolean requiereAtencionHumana;

    @Column(name = "saldo_al_abrir")
    private BigDecimal saldoAlAbrir;

    @Column(name = "id_agente_asignado")
    private Long idAgenteAsignado;

    @Column(name = "fecha_apertura", nullable = false, updatable = false)
    private LocalDateTime fechaApertura;

    @Column(name = "fecha_ultima_actividad", nullable = false)
    private LocalDateTime fechaUltimaActividad;

    @Column(name = "fecha_cierre")
    private LocalDateTime fechaCierre;

    @PrePersist
    void prePersist() {
        LocalDateTime ahora = LocalDateTime.now();
        if (fechaApertura == null) {
            fechaApertura = ahora;
        }
        if (fechaUltimaActividad == null) {
            fechaUltimaActividad = ahora;
        }
        if (estatus == null) {
            estatus = EstatusConversacion.ABIERTA;
        }
        if (requiereAtencionHumana == null) {
            requiereAtencionHumana = false;
        }
    }
}
