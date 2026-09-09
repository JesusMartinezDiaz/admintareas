package mx.com.biochemsystems.cobranzaia.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;

import java.math.BigDecimal;

@Data
@ConfigurationProperties(prefix = "cobranza")
public class CobranzaProperties {
    private Long companiaDefault;
    private Integer diasVencidoParaContacto;
    private BigDecimal montoMaxNotaCreditoAutoSinAprobacion;
}
