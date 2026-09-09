package mx.com.biochemsystems.cobranzaia.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloque;

import java.time.LocalDateTime;

@Entity
@Table(name = "cob_bloque_envio")
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class BloqueEnvio {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_bloque")
    private Long idBloque;

    @Column(name = "nombre", nullable = false, length = 150)
    private String nombre;

    @Column(name = "id_compania", nullable = false)
    private Long idCompania;

    @Column(name = "id_ruta")
    private Long idRuta;

    @Column(name = "id_plantilla")
    private Long idPlantilla;

    @Column(name = "tipo_envio", nullable = false, length = 30)
    private String tipoEnvio;

    @Enumerated(EnumType.STRING)
    @Column(name = "estatus", nullable = false, length = 20)
    private EstatusBloque estatus;

    @Column(name = "fecha_programada")
    private LocalDateTime fechaProgramada;

    @Column(name = "lote_tamanio", nullable = false)
    private Integer loteTamanio;

    @Column(name = "intervalo_segundos_lote", nullable = false)
    private Integer intervaloSegundosLote;

    @Column(name = "dias_min_vencido", nullable = false)
    private Integer diasMinVencido;

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
        if (estatus == null) {
            estatus = EstatusBloque.BORRADOR;
        }
        if (loteTamanio == null) {
            loteTamanio = 25;
        }
        if (intervaloSegundosLote == null) {
            intervaloSegundosLote = 60;
        }
        if (diasMinVencido == null) {
            diasMinVencido = 1;
        }
    }
}
