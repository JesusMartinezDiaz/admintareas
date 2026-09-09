package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.PlantillaMensaje;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.Optional;

public interface PlantillaMensajeRepository extends JpaRepository<PlantillaMensaje, Long> {

    Optional<PlantillaMensaje> findByCodigoAndActivoTrue(String codigo);
}
