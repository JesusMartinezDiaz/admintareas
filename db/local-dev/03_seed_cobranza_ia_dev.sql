-- Ejecutar DESPUÉS de que backend-cobranza-ia haya arrancado al menos
-- una vez con el perfil "local" (Flyway crea las tablas cob_* desde
-- db/migrations/V1 y V2 automáticamente en ese primer arranque).

INSERT INTO cob_telefono_cliente (id_cliente, telefono_e164, origen, es_whatsapp, whatsapp_opt_in, preferido, estatus)
VALUES (1, '5215512340001', 'COMERCIAL', 1, 1, 1, 'A');
