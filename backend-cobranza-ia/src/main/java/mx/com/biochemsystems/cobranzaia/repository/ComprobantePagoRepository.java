package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.ComprobantePago;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusComprobante;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface ComprobantePagoRepository extends JpaRepository<ComprobantePago, Long> {

    List<ComprobantePago> findByIdClienteAndEstatus(Long idCliente, EstatusComprobante estatus);

    List<ComprobantePago> findByEstatus(EstatusComprobante estatus);
}
