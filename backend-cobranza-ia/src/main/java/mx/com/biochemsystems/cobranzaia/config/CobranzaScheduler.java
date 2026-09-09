package mx.com.biochemsystems.cobranzaia.config;

import lombok.RequiredArgsConstructor;
import mx.com.biochemsystems.cobranzaia.service.EnvioLoteService;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

@Component
@RequiredArgsConstructor
public class CobranzaScheduler {

    private final EnvioLoteService envioLoteService;

    /** Tick corto: el propio EnvioLoteService respeta el intervalo configurado por bloque. */
    @Scheduled(fixedDelay = 15000)
    public void tickEnvioBloques() {
        envioLoteService.procesarTick();
    }
}
