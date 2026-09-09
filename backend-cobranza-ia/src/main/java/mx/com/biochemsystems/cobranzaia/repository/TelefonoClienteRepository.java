package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.TelefonoCliente;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface TelefonoClienteRepository extends JpaRepository<TelefonoCliente, Long> {

    List<TelefonoCliente> findByIdClienteAndEstatus(Long idCliente, String estatus);

    Optional<TelefonoCliente> findByTelefonoE164AndEstatus(String telefonoE164, String estatus);
}
