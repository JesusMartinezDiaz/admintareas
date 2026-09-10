-- =====================================================================
-- Esquema MÍNIMO de las tablas de VENTAS que backend-cobranza-ia lee
-- directo por JDBC (ClienteLookupRepository) y que necesita para poder
-- levantar en una máquina de desarrollo SIN acceso a la base de datos
-- real de producción.
--
-- vnt_cat_clientes y vnt_cat_agentes usan la DDL EXACTA que compartió
-- el usuario. vnt_cat_agentes_parametros, vnt_rutas_maestras y
-- vnt_cat_rutas son versiones MÍNIMAS (solo las columnas que las
-- consultas de este módulo realmente usan) - NO son la DDL real
-- completa de apibackendventas, que tiene muchas más columnas.
--
-- NO ejecutar esto contra una base de datos que ya tenga estas tablas.
-- =====================================================================

CREATE TABLE vnt_cat_clientes (
    id_cliente bigint NOT NULL AUTO_INCREMENT,
    cve_cliente varchar(15) NOT NULL,
    id_colonia int unsigned NOT NULL DEFAULT 1,
    nombre varchar(70),
    nombre_comercial varchar(100),
    razon_social varchar(200),
    rfc varchar(15),
    password varchar(150),
    tipo_contribuyente char(1),
    calle_domicilio varchar(120),
    telefono_comercial varchar(60),
    telefono_validado tinyint DEFAULT 0,
    telefono_pagos varchar(60),
    telefono_bodega varchar(60),
    telefono_ventas varchar(60),
    email_comercial varchar(100),
    email_validado tinyint DEFAULT 0,
    email_ventas varchar(100),
    email_bodega varchar(100),
    email_pagos varchar(100),
    domicilio_bodega varchar(120),
    representante_legal varchar(35),
    atencion_ventas varchar(35),
    atencion_pagos varchar(35),
    comentarios varchar(255),
    domicilio_entregas varchar(200),
    estatus tinyint(1),
    id_colonia_fiscal int,
    domicilio_fiscal varchar(100),
    id_colonia_entregas int,
    ultima_actualizacion datetime,
    cve_usuario_actualizacion varchar(45),
    localizacion varchar(200),
    moroso tinyint(1) DEFAULT 0,
    forma_pago varchar(3) DEFAULT '99',
    fecha_registro datetime,
    es_prospecto tinyint(1),
    fecha_ultimo_movimiento date,
    id_uso_cfdi int,
    metodo_pago char(3) NOT NULL DEFAULT 'PUE',
    latitude varchar(45),
    longitude varchar(45),
    verificado char(1),
    img_ife varchar(200),
    img_comprobante varchar(200),
    familia char(3) NOT NULL DEFAULT 'V',
    ruta_extendida tinyint DEFAULT 0,
    id_almacen bigint unsigned,
    origen int DEFAULT 1,
    version int DEFAULT 0,
    esquema int NOT NULL DEFAULT 1,
    id_prospecto bigint,
    codigo_postal_fiscal char(7),
    cve_regimen char(3),
    validado char(1) DEFAULT '0',
    referencia_pagos text,
    referencia_entregas text,
    id_colonia_ruta int DEFAULT 0,
    direccion_ruta char(1) DEFAULT '1',
    logo_comercial varchar(100),
    PRIMARY KEY (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vnt_cat_agentes (
    id_agente bigint NOT NULL AUTO_INCREMENT,
    cve_agente varchar(16),
    comentarios varchar(255),
    id_cuenta varchar(20),
    usuario_captura varchar(15),
    modifico varchar(20),
    version int DEFAULT 0,
    id_empleado int NOT NULL,
    telefono_contacto varchar(15),
    estatus tinyint DEFAULT 1,
    PRIMARY KEY (id_agente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vnt_rutas_asociaciones (
    id_asociacion bigint unsigned NOT NULL AUTO_INCREMENT,
    id_ruta_maestra bigint NOT NULL,
    id_cliente bigint NOT NULL,
    version int DEFAULT 0,
    PRIMARY KEY (id_asociacion),
    KEY ix_rutasasoc_cliente (id_cliente),
    CONSTRAINT fk_rutasasoc_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Versión mínima (no es la DDL real completa de apibackendventas)
CREATE TABLE vnt_cat_agentes_parametros (
    id_agentesparametros bigint NOT NULL AUTO_INCREMENT,
    id_agente bigint NOT NULL,
    PRIMARY KEY (id_agentesparametros),
    CONSTRAINT fk_agparam_agente FOREIGN KEY (id_agente) REFERENCES vnt_cat_agentes (id_agente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Versión mínima (no es la DDL real completa de apibackendventas)
CREATE TABLE vnt_cat_rutas (
    id_cat_rutas bigint NOT NULL AUTO_INCREMENT,
    id_agentesparametros bigint,
    nombre_ruta varchar(100),
    id_compania bigint NOT NULL DEFAULT 19,
    id_compania_facturacion bigint,
    dias_vencimiento_cartera int DEFAULT 30,
    cuota_cobranza decimal(12,2) DEFAULT 0,
    PRIMARY KEY (id_cat_rutas),
    CONSTRAINT fk_catrutas_agparam FOREIGN KEY (id_agentesparametros) REFERENCES vnt_cat_agentes_parametros (id_agentesparametros)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Versión mínima (no es la DDL real completa de apibackendventas)
CREATE TABLE vnt_rutas_maestras (
    id_ruta_maestra bigint NOT NULL AUTO_INCREMENT,
    id_cat_rutas bigint NOT NULL,
    nombre_dia varchar(20),
    num_dia int,
    PRIMARY KEY (id_ruta_maestra),
    CONSTRAINT fk_rutamaestra_catruta FOREIGN KEY (id_cat_rutas) REFERENCES vnt_cat_rutas (id_cat_rutas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
