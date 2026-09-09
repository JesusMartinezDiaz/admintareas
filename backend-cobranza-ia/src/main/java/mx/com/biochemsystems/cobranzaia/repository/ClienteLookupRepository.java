package mx.com.biochemsystems.cobranzaia.repository;

import lombok.RequiredArgsConstructor;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Map;
import java.util.Optional;

/**
 * Lecturas de solo consulta contra tablas de vnt_cat_clientes / vnt_cat_rutas
 * del backend de ventas, que vive en la misma base de datos MySQL.
 *
 * No se usa JPA aquí a propósito: este módulo no es dueño de esas tablas
 * y solo necesita lecturas puntuales, no un modelo de entidades completo.
 */
@Repository
@RequiredArgsConstructor
public class ClienteLookupRepository {

    private final JdbcTemplate jdbcTemplate;

    private static final String SQL_BUSCAR_POR_TELEFONO = """
            SELECT id_cliente, nombre, nombre_comercial, razon_social,
                   telefono_comercial, telefono_pagos, telefono_bodega, telefono_ventas
            FROM vnt_cat_clientes
            WHERE estatus = 1
              AND (
                    RIGHT(REGEXP_REPLACE(telefono_comercial, '[^0-9]', ''), 10) = ?
                 OR RIGHT(REGEXP_REPLACE(telefono_pagos, '[^0-9]', ''), 10) = ?
                 OR RIGHT(REGEXP_REPLACE(telefono_bodega, '[^0-9]', ''), 10) = ?
                 OR RIGHT(REGEXP_REPLACE(telefono_ventas, '[^0-9]', ''), 10) = ?
              )
            LIMIT 1
            """;

    private static final String SQL_POR_ID = """
            SELECT id_cliente, nombre, nombre_comercial, razon_social,
                   telefono_comercial, telefono_pagos, telefono_bodega, telefono_ventas
            FROM vnt_cat_clientes
            WHERE id_cliente = ?
            LIMIT 1
            """;

    private static final String SQL_RUTA_AGENTE_POR_CLIENTE = """
            SELECT r.id_cliente, cr.id_cat_rutas AS id_ruta, cr.nombre_ruta,
                   cr.dias_vencimiento_cartera, ag.id_agente
            FROM vnt_rutas_asociaciones r
            JOIN vnt_rutas_maestras rm ON rm.id_ruta_maestra = r.id_ruta_maestra
            JOIN vnt_cat_rutas cr ON cr.id_cat_rutas = rm.id_cat_rutas
            LEFT JOIN vnt_cat_agentes_parametros ap ON ap.id_agentesparametros = cr.id_agentesparametros
            LEFT JOIN vnt_cat_agentes ag ON ag.id_agente = ap.id_agente
            WHERE r.id_cliente = ?
            LIMIT 1
            """;

    /**
     * Normaliza a los últimos 10 dígitos, igual que
     * TwilioController#normalizarTelefono en apibackendventas, para
     * mantener consistencia entre canales (voz y WhatsApp).
     */
    public String normalizar(String telefono) {
        if (telefono == null) {
            return null;
        }
        String soloDigitos = telefono.replaceAll("\\D", "");
        return soloDigitos.length() > 10
                ? soloDigitos.substring(soloDigitos.length() - 10)
                : soloDigitos;
    }

    public Optional<Map<String, Object>> buscarClientePorTelefono(String telefono) {
        String normalizado = normalizar(telefono);
        List<Map<String, Object>> resultado = jdbcTemplate.queryForList(
                SQL_BUSCAR_POR_TELEFONO, normalizado, normalizado, normalizado, normalizado);
        return resultado.isEmpty() ? Optional.empty() : Optional.of(resultado.get(0));
    }

    public Optional<Map<String, Object>> buscarClientePorId(Long idCliente) {
        List<Map<String, Object>> resultado = jdbcTemplate.queryForList(SQL_POR_ID, idCliente);
        return resultado.isEmpty() ? Optional.empty() : Optional.of(resultado.get(0));
    }

    public Optional<Map<String, Object>> obtenerRutaYAgentePorCliente(Long idCliente) {
        List<Map<String, Object>> resultado = jdbcTemplate.queryForList(SQL_RUTA_AGENTE_POR_CLIENTE, idCliente);
        return resultado.isEmpty() ? Optional.empty() : Optional.of(resultado.get(0));
    }
}
