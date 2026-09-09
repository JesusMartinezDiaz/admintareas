package mx.com.biochemsystems.cobranzaia.service;

/** Ejecuta el envío controlado (por lotes/intervalo) de los bloques EN_PROCESO. */
public interface EnvioLoteService {

    /** Procesa un tick: revisa todos los bloques activos y envía el siguiente lote que ya cumplió su intervalo. */
    void procesarTick();
}
