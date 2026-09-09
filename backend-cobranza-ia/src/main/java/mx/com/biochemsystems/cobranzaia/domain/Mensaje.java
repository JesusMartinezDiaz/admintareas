package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.DireccionMensaje;
import mx.com.biochemsystems.cobranzaia.domain.enums.GeneradoPor;
import mx.com.biochemsystems.cobranzaia.domain.enums.TipoContenidoMensaje;
import org.hibernate.annotations.JdbcTypeCode;
import org.hibernate.type.SqlTypes;

import java.time.LocalDateTime;

@Entity
@Table(name = "cob_mensaje")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class Mensaje {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_mensaje")
    private Long idMensaje;

    @Column(name = "id_conversacion", nullable = false)
    private Long idConversacion;

    @Enumerated(EnumType.STRING)
    @Column(name = "direccion", nullable = false, length = 10)
    private DireccionMensaje direccion;

    @Enumerated(EnumType.STRING)
    @Column(name = "tipo_contenido", nullable = false, length = 20)
    private TipoContenidoMensaje tipoContenido;

    @Column(name = "contenido_texto", columnDefinition = "TEXT")
    private String contenidoTexto;

    @Column(name = "url_media", length = 500)
    private String urlMedia;

    @Column(name = "whatsapp_message_id", length = 100)
    private String whatsappMessageId;

    @Column(name = "whatsapp_status", length = 20)
    private String whatsappStatus;

    @Enumerated(EnumType.STRING)
    @Column(name = "generado_por", nullable = false, length = 20)
    private GeneradoPor generadoPor;

    @JdbcTypeCode(SqlTypes.JSON)
    @Column(name = "metadata_json")
    private String metadataJson;

    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @PrePersist
    void prePersist() {
        if (fechaCreacion == null) {
            fechaCreacion = LocalDateTime.now();
        }
        if (tipoContenido == null) {
            tipoContenido = TipoContenidoMensaje.TEXTO;
        }
    }
}
