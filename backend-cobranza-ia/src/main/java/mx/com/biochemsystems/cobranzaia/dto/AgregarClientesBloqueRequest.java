package mx.com.biochemsystems.cobranzaia.dto;

import lombok.Data;

import java.util.List;

@Data
public class AgregarClientesBloqueRequest {

    /** Si se envía, se agregan automáticamente todos los clientes de la ruta con saldo vencido. */
    private Boolean autocompletarDesdeRuta;

    /** Clientes agregados/curados manualmente por el administrador. */
    private List<Long> idsCliente;
}
