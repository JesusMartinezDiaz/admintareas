package mx.com.biochemsystems.cobranzaia.dto;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class ChatAdminResponse {
    private String respuesta;
    private Long idConsultaLog;
}
