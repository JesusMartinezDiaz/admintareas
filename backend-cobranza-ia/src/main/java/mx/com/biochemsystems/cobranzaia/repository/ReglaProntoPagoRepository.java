package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.ReglaProntoPago;
import org.springframework.data.jpa.repository.JpaRepository;

import java.time.LocalDate;
import java.util.List;

public interface ReglaProntoPagoRepository extends JpaRepository<ReglaProntoPago, Long> {

    List<ReglaProntoPago> findByIdCompaniaAndActivoTrueAndVigenciaInicioLessThanEqual(
            Long idCompania, LocalDate hoy);
}
