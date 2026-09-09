package mx.com.biochemsystems.cobranzaia.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import lombok.Data;

@Data
public class CrearBloqueRequest {

    @NotBlank
    private String nombre;

    @NotNull
    private Long idCompania;

    private Long idRuta;

    @NotNull
    private Long idPlantilla;

    private String tipoEnvio;

    private Integer loteTamanio;

    private Integer intervaloSegundosLote;

    private Integer diasMinVencido;
}
