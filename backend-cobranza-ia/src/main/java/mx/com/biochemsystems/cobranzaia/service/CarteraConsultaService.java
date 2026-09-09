package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;

import java.util.List;
import java.util.Optional;

public interface CarteraConsultaService {

    /** Cartera vencida de una ruta (o de toda la compañía si idRuta es null), lista para armar un bloque. */
    List<ClienteCarteraDTO> obtenerCarteraVencidaPorRuta(Long idRuta, Long idCompania, int diasMinVencido);

    Optional<ClienteCarteraDTO> obtenerClientePorTelefono(String telefono, Long idCompania);

    /** Resuelve datos + teléfono de WhatsApp preferido a partir del id de cliente (usado al despachar un bloque). */
    Optional<ClienteCarteraDTO> obtenerClientePorId(Long idCliente, Long idCompania);
}
