package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.domain.BloqueCliente;
import mx.com.biochemsystems.cobranzaia.domain.BloqueEnvio;
import mx.com.biochemsystems.cobranzaia.dto.CrearBloqueRequest;

import java.util.List;

public interface BloqueEnvioService {

    BloqueEnvio crearBloque(CrearBloqueRequest request, String cveUsuario);

    /** Agrega clientes manualmente o autocompleta desde la cartera vencida de la ruta del bloque. */
    List<BloqueCliente> agregarClientes(Long idBloque, List<Long> idsCliente, boolean autocompletarDesdeRuta);

    void quitarCliente(Long idBloque, Long idCliente);

    /** Pasa el bloque a PROGRAMADO/EN_PROCESO; el envío real lo hace el scheduler por lotes. */
    BloqueEnvio iniciarEnvio(Long idBloque);

    BloqueEnvio pausar(Long idBloque);

    List<BloqueEnvio> listarPorRuta(Long idRuta);

    BloqueEnvio obtenerBloque(Long idBloque);

    List<BloqueCliente> listarClientesDeBloque(Long idBloque);
}
