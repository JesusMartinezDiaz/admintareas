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
@Table(name = "cob_agente_ia_consulta_log")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class AgenteIaConsultaLog {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_consulta")
    private Long idConsulta;

    @Column(name = "cve_usuario", nullable = false, length = 45)
    private String cveUsuario;

    @Column(name = "pregunta", nullable = false, columnDefinition = "TEXT")
    private String pregunta;

    @Column(name = "respuesta", columnDefinition = "TEXT")
    private String respuesta;

    @JdbcTypeCode(SqlTypes.JSON)
    @Column(name = "tool_calls_json")
    private String toolCallsJson;

    @Column(name = "modelo", length = 50)
    private String modelo;

    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @PrePersist
    void prePersist() {
        if (fechaCreacion == null) {
            fechaCreacion = LocalDateTime.now();
        }
    }
}
