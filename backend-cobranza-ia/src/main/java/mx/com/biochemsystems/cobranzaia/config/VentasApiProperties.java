package mx.com.biochemsystems.cobranzaia.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;

@Data
@ConfigurationProperties(prefix = "ventas-api")
public class VentasApiProperties {
    private String baseUrl;
    private String apiKey;

    /**
     * id_tipo_documento a usar al registrar un pago (vnt_abono_encabezado,
     * id_cat_documento=3). apibackendventas no lo fija automáticamente;
     * si se deja vacío, VentasApiClientImpl lo resuelve consultando
     * vnt_cat_tipo_documento directamente - confírmalo con negocio y
     * fíjalo aquí antes de producción. String (no Long) a propósito:
     * así un valor vacío en la variable de entorno no rompe el binding.
     */
    private String idTipoDocumentoPago;
}
