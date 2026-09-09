package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.BloqueEnvio;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloque;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface BloqueEnvioRepository extends JpaRepository<BloqueEnvio, Long> {

    List<BloqueEnvio> findByIdRutaAndEstatus(Long idRuta, EstatusBloque estatus);

    List<BloqueEnvio> findByIdRuta(Long idRuta);

    List<BloqueEnvio> findByEstatus(EstatusBloque estatus);
}
