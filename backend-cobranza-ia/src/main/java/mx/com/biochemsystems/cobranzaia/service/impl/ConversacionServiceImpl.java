package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.Mensaje;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusConversacion;
import mx.com.biochemsystems.cobranzaia.repository.ConversacionRepository;
import mx.com.biochemsystems.cobranzaia.repository.MensajeRepository;
import mx.com.biochemsystems.cobranzaia.service.ConversacionService;
import org.springframework.stereotype.Service;

import java.time.LocalDateTime;
import java.util.List;

@Service
@RequiredArgsConstructor
public class ConversacionServiceImpl implements ConversacionService {

    private final ConversacionRepository conversacionRepository;
    private final MensajeRepository mensajeRepository;

    @Override
    public List<Conversacion> listarQueRequierenAtencion() {
        return conversacionRepository.findByRequiereAtencionHumanaTrueAndEstatusNot(EstatusConversacion.CERRADA);
    }

    @Override
    public List<Mensaje> listarMensajes(Long idConversacion) {
        return mensajeRepository.findByIdConversacionOrderByFechaCreacionAsc(idConversacion);
    }

    @Override
    public Conversacion tomarConversacion(Long idConversacion, Long idAgente) {
        Conversacion conversacion = conversacionRepository.findById(idConversacion)
                .orElseThrow(() -> new IllegalArgumentException("Conversación no encontrada: " + idConversacion));
        conversacion.setIdAgenteAsignado(idAgente);
        return conversacionRepository.save(conversacion);
    }

    @Override
    public Conversacion cerrarConversacion(Long idConversacion) {
        Conversacion conversacion = conversacionRepository.findById(idConversacion)
                .orElseThrow(() -> new IllegalArgumentException("Conversación no encontrada: " + idConversacion));
        conversacion.setEstatus(EstatusConversacion.CERRADA);
        conversacion.setRequiereAtencionHumana(false);
        conversacion.setFechaCierre(LocalDateTime.now());
        return conversacionRepository.save(conversacion);
    }
}
