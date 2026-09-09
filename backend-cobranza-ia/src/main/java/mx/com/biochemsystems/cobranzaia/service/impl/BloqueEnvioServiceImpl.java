package mx.com.biochemsystems.cobranzaia.service.impl;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.domain.BloqueCliente;
import mx.com.biochemsystems.cobranzaia.domain.BloqueEnvio;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloque;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloqueCliente;
import mx.com.biochemsystems.cobranzaia.dto.ClienteCarteraDTO;
import mx.com.biochemsystems.cobranzaia.dto.CrearBloqueRequest;
import mx.com.biochemsystems.cobranzaia.repository.BloqueClienteRepository;
import mx.com.biochemsystems.cobranzaia.repository.BloqueEnvioRepository;
import mx.com.biochemsystems.cobranzaia.service.BloqueEnvioService;
import mx.com.biochemsystems.cobranzaia.service.CarteraConsultaService;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
@RequiredArgsConstructor
public class BloqueEnvioServiceImpl implements BloqueEnvioService {

    private final BloqueEnvioRepository bloqueEnvioRepository;
    private final BloqueClienteRepository bloqueClienteRepository;
    private final CarteraConsultaService carteraConsultaService;

    @Override
    public BloqueEnvio crearBloque(CrearBloqueRequest request, String cveUsuario) {
        BloqueEnvio bloque = BloqueEnvio.builder()
                .nombre(request.getNombre())
                .idCompania(request.getIdCompania())
                .idRuta(request.getIdRuta())
                .idPlantilla(request.getIdPlantilla())
                .tipoEnvio(request.getTipoEnvio() != null ? request.getTipoEnvio() : "RECORDATORIO_SALDO")
                .loteTamanio(request.getLoteTamanio())
                .intervaloSegundosLote(request.getIntervaloSegundosLote())
                .diasMinVencido(request.getDiasMinVencido())
                .cveUsuarioCreacion(cveUsuario)
                .build();
        return bloqueEnvioRepository.save(bloque);
    }

    @Override
    @Transactional
    public List<BloqueCliente> agregarClientes(Long idBloque, List<Long> idsCliente, boolean autocompletarDesdeRuta) {
        BloqueEnvio bloque = bloqueEnvioRepository.findById(idBloque)
                .orElseThrow(() -> new IllegalArgumentException("Bloque no encontrado: " + idBloque));

        List<Long> idsAAgregar = idsCliente;
        if (autocompletarDesdeRuta) {
            List<ClienteCarteraDTO> cartera = carteraConsultaService.obtenerCarteraVencidaPorRuta(
                    bloque.getIdRuta(), bloque.getIdCompania(), bloque.getDiasMinVencido());
            idsAAgregar = cartera.stream().map(ClienteCarteraDTO::getIdCliente).toList();
        }

        int orden = bloqueClienteRepository.findByIdBloqueOrderByOrdenEnvioAsc(idBloque).size();
        for (Long idCliente : idsAAgregar) {
            BloqueCliente bc = BloqueCliente.builder()
                    .idBloque(idBloque)
                    .idCliente(idCliente)
                    .ordenEnvio(orden++)
                    .build();
            bloqueClienteRepository.save(bc);
        }
        return bloqueClienteRepository.findByIdBloqueOrderByOrdenEnvioAsc(idBloque);
    }

    @Override
    public void quitarCliente(Long idBloque, Long idCliente) {
        bloqueClienteRepository.findByIdBloqueOrderByOrdenEnvioAsc(idBloque).stream()
                .filter(bc -> bc.getIdCliente().equals(idCliente))
                .forEach(bc -> {
                    bc.setEstatus(EstatusBloqueCliente.EXCLUIDO);
                    bc.setMotivoExclusion("Excluido manualmente por administrador");
                    bloqueClienteRepository.save(bc);
                });
    }

    @Override
    public BloqueEnvio iniciarEnvio(Long idBloque) {
        BloqueEnvio bloque = bloqueEnvioRepository.findById(idBloque)
                .orElseThrow(() -> new IllegalArgumentException("Bloque no encontrado: " + idBloque));
        bloque.setEstatus(EstatusBloque.EN_PROCESO);
        return bloqueEnvioRepository.save(bloque);
    }

    @Override
    public BloqueEnvio pausar(Long idBloque) {
        BloqueEnvio bloque = bloqueEnvioRepository.findById(idBloque)
                .orElseThrow(() -> new IllegalArgumentException("Bloque no encontrado: " + idBloque));
        bloque.setEstatus(EstatusBloque.PAUSADO);
        return bloqueEnvioRepository.save(bloque);
    }

    @Override
    public List<BloqueEnvio> listarPorRuta(Long idRuta) {
        return bloqueEnvioRepository.findByIdRuta(idRuta);
    }

    @Override
    public BloqueEnvio obtenerBloque(Long idBloque) {
        return bloqueEnvioRepository.findById(idBloque)
                .orElseThrow(() -> new IllegalArgumentException("Bloque no encontrado: " + idBloque));
    }

    @Override
    public List<BloqueCliente> listarClientesDeBloque(Long idBloque) {
        return bloqueClienteRepository.findByIdBloqueOrderByOrdenEnvioAsc(idBloque);
    }
}
