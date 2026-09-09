package mx.com.biochemsystems.cobranzaia.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;

@Data
@ConfigurationProperties(prefix = "whatsapp.cloud-api")
public class WhatsAppProperties {
    private String baseUrl;
    private String phoneNumberId;
    private String accessToken;
    private String webhookVerifyToken;
    private String appSecret;
}
