package mx.com.biochemsystems.cobranzaia.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;

@Data
@ConfigurationProperties(prefix = "anthropic")
public class AnthropicProperties {
    private String apiKey;
    private String modeloConversacional;
    private String modeloAgenteDatos;
}
