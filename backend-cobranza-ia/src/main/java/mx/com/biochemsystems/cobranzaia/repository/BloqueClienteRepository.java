package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.BloqueCliente;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusBloqueCliente;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface BloqueClienteRepository extends JpaRepository<BloqueCliente, Long> {

    List<BloqueCliente> findByIdBloqueOrderByOrdenEnvioAsc(Long idBloque);

    List<BloqueCliente> findByIdBloqueAndEstatus(Long idBloque, EstatusBloqueCliente estatus);
}
