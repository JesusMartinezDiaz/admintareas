package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.AlertaCliente;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusAlerta;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface AlertaClienteRepository extends JpaRepository<AlertaCliente, Long> {

    List<AlertaCliente> findByEstatus(EstatusAlerta estatus);
}
