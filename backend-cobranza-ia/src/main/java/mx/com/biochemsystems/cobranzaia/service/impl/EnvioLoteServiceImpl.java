package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import mx.com.biochemsystems.cobranzaia.domain.BloqueCliente;
import mx.com.biochemsystems.cobranzaia.domain.BloqueEnvio;
import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.Mensaje;
import mx.com.biochemsystems.cobranzaia.domain.PlantillaMensaje;
import mx.com.biochemsystems.cobranzaia.domain.enums.DireccionMensaje;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloque;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloqueCliente;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusConversacion;
import mx.com.biochemsystems.cobranzaia.domain.enums.GeneradoPor;
import mx.com.biochemsystems.cobranzaia.domain.enums.TipoContenidoMensaje;
import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;
import mx.com.biochemsystems.cobranzaia.integration.whatsapp.WhatsAppMessagingClient;
import mx.com.biochemsystems.cobranzaia.repository.BloqueClienteRepository;
import mx.com.biochemsystems.cobranzaia.repository.BloqueEnvioRepository;
import mx.com.biochemsystems.cobranzaia.repository.ConversacionRepository;
import mx.com.biochemsystems.cobranzaia.repository.MensajeRepository;
import mx.com.biochemsystems.cobranzaia.repository.PlantillaMensajeRepository;
import mx.com.biochemsystems.cobranzaia.service.CarteraConsultaService;
import mx.com.biochemsystems.cobranzaia.service.EnvioLoteService;
import org.springframework.stereotype.Service;

import java.time.LocalDateTime;
import java.util.List;
import java.util.Optional;

/**
 * Implementación simple del ritmo "por bloques": cada tick del scheduler
 * revisa los bloques EN_PROCESO y, si ya pasó el intervalo configurado
 * desde el último envío, dispara hasta loteTamanio mensajes pendientes.
 *
 * Un tick corto (ver CobranzaScheduler) + este chequeo de "último envío"
 * evita necesitar hilos/colas dedicados por bloque en esta primera
 * versión; para volumen alto conviene migrar a una cola real (p.ej. con
 * Spring Batch o un broker) sin cambiar el contrato de este servicio.
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class EnvioLoteServiceImpl implements EnvioLoteService {

    private final BloqueEnvioRepository bloqueEnvioRepository;
    private final BloqueClienteRepository bloqueClienteRepository;
    private final PlantillaMensajeRepository plantillaMensajeRepository;
    private final CarteraConsultaService carteraConsultaService;
    private final ConversacionRepository conversacionRepository;
    private final MensajeRepository mensajeRepository;
    private final WhatsAppMessagingClient whatsAppMessagingClient;

    @Override
    public void procesarTick() {
        for (BloqueEnvio bloque : bloqueEnvioRepository.findByEstatus(EstatusBloque.EN_PROCESO)) {
            try {
                procesarBloque(bloque);
            } catch (Exception e) {
                log.error("Error procesando bloque {}: {}", bloque.getIdBloque(), e.getMessage(), e);
            }
        }
    }

    private void procesarBloque(BloqueEnvio bloque) {
        List<BloqueCliente> pendientes = bloqueClienteRepository
                .findByIdBloqueAndEstatus(bloque.getIdBloque(), EstatusBloqueCliente.PENDIENTE);

        if (pendientes.isEmpty()) {
            bloque.setEstatus(EstatusBloque.COMPLETADO);
            bloqueEnvioRepository.save(bloque);
            return;
        }

        if (!yaPasoElIntervalo(bloque)) {
            return;
        }

        PlantillaMensaje plantilla = plantillaMensajeRepository.findById(bloque.getIdPlantilla())
                .orElseThrow(() -> new IllegalStateException("Plantilla no encontrada: " + bloque.getIdPlantilla()));

        pendientes.stream()
                .limit(bloque.getLoteTamanio())
                .forEach(bc -> enviarACliente(bloque, plantilla, bc));
    }

    private boolean yaPasoElIntervalo(BloqueEnvio bloque) {
        Optional<LocalDateTime> ultimoEnvio = bloqueClienteRepository
                .findByIdBloqueOrderByOrdenEnvioAsc(bloque.getIdBloque()).stream()
                .map(BloqueCliente::getFechaEnvio)
                .filter(f -> f != null)
                .max(LocalDateTime::compareTo);

        return ultimoEnvio.isEmpty()
                || ultimoEnvio.get().plusSeconds(bloque.getIntervaloSegundosLote()).isBefore(LocalDateTime.now());
    }

    private void enviarACliente(BloqueEnvio bloque, PlantillaMensaje plantilla, BloqueCliente bc) {
        Optional<ClienteCarteraDTO> clienteOpt = carteraConsultaService
                .obtenerClientePorId(bc.getIdCliente(), bloque.getIdCompania());

        if (clienteOpt.isEmpty() || clienteOpt.get().getTelefonoWhatsapp() == null) {
            bc.setEstatus(EstatusBloqueCliente.ERROR);
            bc.setMotivoExclusion("Sin teléfono de WhatsApp válido");
            bloqueClienteRepository.save(bc);
            return;
        }
        ClienteCarteraDTO cliente = clienteOpt.get();

        List<String> parametros = List.of(
                cliente.getNombreComercial() != null ? cliente.getNombreComercial() : cliente.getNombreCliente(),
                cliente.getSaldoTotal() != null ? cliente.getSaldoTotal().toPlainString() : "0.00"
        );

        String whatsappMessageId = whatsAppMessagingClient.enviarPlantilla(
                cliente.getTelefonoWhatsapp(), plantilla.getCodigo(), plantilla.getIdioma(), parametros);

        Conversacion conversacion = conversacionRepository.save(Conversacion.builder()
                .idCliente(bc.getIdCliente())
                .idBloque(bloque.getIdBloque())
                .telefonoE164(cliente.getTelefonoWhatsapp())
                .estatus(EstatusConversacion.ESPERANDO_CLIENTE)
                .saldoAlAbrir(cliente.getSaldoTotal())
                .build());

        mensajeRepository.save(Mensaje.builder()
                .idConversacion(conversacion.getIdConversacion())
                .direccion(DireccionMensaje.SALIENTE)
                .tipoContenido(TipoContenidoMensaje.PLANTILLA)
                .contenidoTexto(plantilla.getCodigo())
                .whatsappMessageId(whatsappMessageId)
                .whatsappStatus("SENT")
                .generadoPor(GeneradoPor.SISTEMA)
                .build());

        bc.setEstatus(EstatusBloqueCliente.ENVIADO);
        bc.setFechaEnvio(LocalDateTime.now());
        bloqueClienteRepository.save(bc);
    }
}
