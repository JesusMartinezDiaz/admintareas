package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.NotaCreditoGenerada;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusNotaCredito;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface NotaCreditoGeneradaRepository extends JpaRepository<NotaCreditoGenerada, Long> {

    List<NotaCreditoGenerada> findByEstatus(EstatusNotaCredito estatus);
}
