package mx.com.biochemsystems.cobranzaia.dto;

import jakarta.validation.constraints.NotBlank;
import lombok.Data;

@Data
public class ChatAdminRequest {

    @NotBlank
    private String pregunta;

    private String cveUsuario;
}
