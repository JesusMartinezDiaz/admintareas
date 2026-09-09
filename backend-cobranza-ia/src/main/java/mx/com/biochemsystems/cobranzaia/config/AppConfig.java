package mx.com.biochemsystems.cobranzaia.config;

import com.anthropic.client.AnthropicClient;
import com.anthropic.client.okhttp.AnthropicOkHttpClient;
import org.springframework.boot.context.properties.EnableConfigurationProperties;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.reactive.function.client.WebClient;

@Configuration
@EnableConfigurationProperties({
        WhatsAppProperties.class,
        AnthropicProperties.class,
        VentasApiProperties.class,
        CobranzaProperties.class
})
public class AppConfig {

    @Bean
    public AnthropicClient anthropicClient(AnthropicProperties properties) {
        return AnthropicOkHttpClient.builder()
                .apiKey(properties.getApiKey())
                .build();
    }

    @Bean
    public WebClient whatsAppWebClient(WhatsAppProperties properties) {
        return WebClient.builder()
                .baseUrl(properties.getBaseUrl())
                .defaultHeader("Authorization", "Bearer " + properties.getAccessToken())
                .build();
    }

    @Bean
    public WebClient ventasApiWebClient(VentasApiProperties properties) {
        WebClient.Builder builder = WebClient.builder().baseUrl(properties.getBaseUrl());
        if (properties.getApiKey() != null && !properties.getApiKey().isBlank()) {
            builder.defaultHeader("X-Api-Key", properties.getApiKey());
        }
        return builder.build();
    }
}
