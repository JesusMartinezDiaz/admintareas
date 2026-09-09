package mx.com.biochemsystems.cobranzaia.repository;

import mx.com.biochemsystems.cobranzaia.domain.Conversacion;
import mx.com.biochemsystems.cobranzaia.domain.enums.EstatusConversacion;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface ConversacionRepository extends JpaRepository<Conversacion, Long> {

    Optional<Conversacion> findFirstByTelefonoE164AndEstatusNotOrderByFechaAperturaDesc(
            String telefonoE164, EstatusConversacion estatusCerrada);

    List<Conversacion> findByRequiereAtencionHumanaTrueAndEstatusNot(EstatusConversacion estatusCerrada);
}
