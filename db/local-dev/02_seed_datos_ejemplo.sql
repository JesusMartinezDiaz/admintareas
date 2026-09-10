-- Datos ficticios de desarrollo. NO son clientes reales.

INSERT INTO vnt_cat_agentes (id_agente, cve_agente, id_empleado, telefono_contacto, estatus) VALUES
    (1, 'AG-001', 1001, '5511111111', 1);

INSERT INTO vnt_cat_agentes_parametros (id_agentesparametros, id_agente) VALUES
    (1, 1);

INSERT INTO vnt_cat_rutas (id_cat_rutas, id_agentesparametros, nombre_ruta, id_compania, dias_vencimiento_cartera, cuota_cobranza) VALUES
    (12, 1, 'Ruta 12 - CDMX Sur', 19, 30, 50000.00);

INSERT INTO vnt_rutas_maestras (id_ruta_maestra, id_cat_rutas, nombre_dia, num_dia) VALUES
    (100, 12, 'LUNES', 1);

INSERT INTO vnt_cat_clientes
    (id_cliente, cve_cliente, id_colonia, nombre, nombre_comercial, razon_social, telefono_comercial, telefono_pagos, estatus, esquema, metodo_pago, familia)
VALUES
    (1, 'CLI-0001', 1, 'Veterinaria Ejemplo del Norte', 'Vet. del Norte', 'VETERINARIA DEL NORTE SA DE CV', '5512340001', '5512340001', 1, 1, 'PUE', 'V'),
    (2, 'CLI-0002', 1, 'Agropecuaria Ejemplo Dos',      'Agro Dos',      'AGROPECUARIA DOS SA DE CV',      '5512340002', '5512340002', 1, 1, 'PUE', 'V'),
    (3, 'CLI-0003', 1, 'Rancho Ejemplo Tres',            'Rancho Tres',   'RANCHO TRES SA DE CV',           '5512340003', '5512340003', 1, 1, 'PUE', 'V');

INSERT INTO vnt_rutas_asociaciones (id_ruta_maestra, id_cliente) VALUES
    (100, 1),
    (100, 2),
    (100, 3);
