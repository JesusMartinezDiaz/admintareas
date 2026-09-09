package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.time.LocalDateTime;

@Entity
@Table(name = "cob_telefono_cliente")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class TelefonoCliente {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_telefono")
    private Long idTelefono;

    @Column(name = "id_cliente", nullable = false)
    private Long idCliente;

    @Column(name = "telefono_e164", nullable = false, length = 20)
    private String telefonoE164;

    @Column(name = "origen", nullable = false, length = 20)
    private String origen;

    @Column(name = "es_whatsapp", nullable = false)
    private Boolean esWhatsapp;

    @Column(name = "whatsapp_opt_in", nullable = false)
    private Boolean whatsappOptIn;

    @Column(name = "preferido", nullable = false)
    private Boolean preferido;

    @Column(name = "estatus", nullable = false, length = 1)
    private String estatus;

    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @Column(name = "fecha_actualizacion")
    private LocalDateTime fechaActualizacion;

    @PrePersist
    void prePersist() {
        if (fechaCreacion == null) {
            fechaCreacion = LocalDateTime.now();
        }
        if (estatus == null) {
            estatus = "A";
        }
    }
}
