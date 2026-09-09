package mx.com.biochemsystems.cobranzaia.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;

@Data
@ConfigurationProperties(prefix = "ventas-api")
public class VentasApiProperties {
    private String baseUrl;
    private String apiKey;
}
