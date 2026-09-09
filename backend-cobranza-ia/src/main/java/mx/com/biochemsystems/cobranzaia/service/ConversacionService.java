package mx.com.biochemsystems.cobranzaia.service;

import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.Mensaje;

import java.util.List;

public interface ConversacionService {

    List<Conversacion> listarQueRequierenAtencion();

    List<Mensaje> listarMensajes(Long idConversacion);

    Conversacion tomarConversacion(Long idConversacion, Long idAgente);

    Conversacion cerrarConversacion(Long idConversacion);
}
