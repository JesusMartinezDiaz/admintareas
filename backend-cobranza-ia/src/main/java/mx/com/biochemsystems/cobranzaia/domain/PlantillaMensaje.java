package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import org.hibernate.annotations.JdbcTypeCode;
import org.hibernate.type.SqlTypes;

import java.time.LocalDateTime;

@Entity
@Table(name = "cob_plantilla_mensaje")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class PlantillaMensaje {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_plantilla")
    private Long idPlantilla;

    @Column(name = "codigo", nullable = false, length = 100)
    private String codigo;

    @Column(name = "nombre_visible", nullable = false, length = 150)
    private String nombreVisible;

    @Column(name = "categoria", nullable = false, length = 30)
    private String categoria;

    @Column(name = "idioma", nullable = false, length = 10)
    private String idioma;

    @Column(name = "cuerpo_texto", nullable = false, columnDefinition = "TEXT")
    private String cuerpoTexto;

    @JdbcTypeCode(SqlTypes.JSON)
    @Column(name = "variables_json")
    private String variablesJson;

    @Column(name = "activo", nullable = false)
    private Boolean activo;

    @Column(name = "cve_usuario_creacion", length = 45)
    private String cveUsuarioCreacion;

    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @Column(name = "fecha_actualizacion")
    private LocalDateTime fechaActualizacion;

    @PrePersist
    void prePersist() {
        if (fechaCreacion == null) {
            fechaCreacion = LocalDateTime.now();
        }
        if (activo == null) {
            activo = true;
        }
    }
}
