package mx.com.biochemsystems.cobranzaia.dto;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.math.BigDecimal;

/**
 * Vista de cartera de un cliente, obtenida del backend de ventas
 * (VntReportesCobranzaController / VntEstadoCuentaController) y
 * complementada con el teléfono a usar para WhatsApp.
 */
@Data
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class ClienteCarteraDTO {
    private Long idCliente;
    private String nombreCliente;
    private String nombreComercial;
    private Long idRuta;
    private String nombreRuta;
    private Long idAgente;
    private BigDecimal saldoTotal;
    private Integer diasVencidoMax;
    private String telefonoWhatsapp;
}
