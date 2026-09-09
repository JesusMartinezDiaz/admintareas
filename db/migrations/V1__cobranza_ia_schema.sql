-- =====================================================================
-- Agente Virtual de Cobranza (WhatsApp + IA) - Biochem Systems
-- Esquema nuevo: tablas prefijadas "cob_"
--
-- Supuestos de diseño:
--  * Estas tablas viven en la MISMA base de datos MySQL que el backend
--    de ventas (apibackendventas), para poder hacer JOIN directo contra
--    vnt_cat_clientes, vnt_cat_rutas, vnt_cat_agentes y edo_cta_biochem.
--  * Solo se agrega FK real hacia vnt_cat_clientes(id_cliente) porque es
--    la única tabla de la que se confirmó la DDL exacta en esta sesión.
--    Las demás referencias (ruta, agente, cargo, abono) se guardan como
--    columnas BIGINT "lógicas" sin constraint física, para no arriesgar
--    la migración por diferencias de tipo no verificadas.
--  * id_compania: 19 = BIOCHEM SYSTEMS, 20 = ALL 4 PET'S (confirmado en
--    apibackendventas / VntAbonoImpl). El agente de cobranza arranca
--    operando solo sobre id_compania = 19.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Catálogo de teléfonos por cliente (normalizado, propio del módulo).
-- vnt_cat_clientes solo tiene 4 columnas fijas de teléfono; aquí se
-- mantiene una versión normalizada + validada + con opt-in de WhatsApp,
-- alimentada inicialmente desde esas 4 columnas.
-- ---------------------------------------------------------------------
CREATE TABLE cob_telefono_cliente (
    id_telefono           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cliente            BIGINT NOT NULL,
    telefono_e164         VARCHAR(20) NOT NULL COMMENT 'Formato +52XXXXXXXXXX',
    origen                VARCHAR(20) NOT NULL COMMENT 'COMERCIAL, PAGOS, BODEGA, VENTAS, MANUAL',
    es_whatsapp           TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_opt_in       TINYINT(1) NOT NULL DEFAULT 0,
    preferido             TINYINT(1) NOT NULL DEFAULT 0,
    estatus               CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A=Activo, X=Baja',
    fecha_creacion        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_telefono),
    KEY ix_tel_cliente (id_cliente),
    UNIQUE KEY ux_tel_cliente_numero (id_cliente, telefono_e164),
    CONSTRAINT fk_tel_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Plantillas de mensaje (deben existir/estar aprobadas como HSM template
-- en WhatsApp Business Cloud API con el mismo "codigo").
-- ---------------------------------------------------------------------
CREATE TABLE cob_plantilla_mensaje (
    id_plantilla          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo                VARCHAR(100) NOT NULL COMMENT 'Nombre exacto del template en WhatsApp',
    nombre_visible        VARCHAR(150) NOT NULL,
    categoria             VARCHAR(30) NOT NULL COMMENT 'SALDO, RECORDATORIO, NEGOCIACION, CONFIRMACION_PAGO, PRONTO_PAGO',
    idioma                VARCHAR(10) NOT NULL DEFAULT 'es_MX',
    cuerpo_texto          TEXT NOT NULL COMMENT 'Texto con placeholders {{1}} {{2}} ...',
    variables_json        JSON NULL COMMENT 'Orden y origen de negocio de cada placeholder',
    activo                TINYINT(1) NOT NULL DEFAULT 1,
    cve_usuario_creacion  VARCHAR(45) NULL,
    fecha_creacion        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_plantilla),
    UNIQUE KEY ux_plantilla_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bloques de envío: unidad administrable de "por ruta / por bloques"
-- que pide el negocio para controlar el ritmo de contacto.
-- ---------------------------------------------------------------------
CREATE TABLE cob_bloque_envio (
    id_bloque                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre                     VARCHAR(150) NOT NULL,
    id_compania                BIGINT NOT NULL DEFAULT 19,
    id_ruta                    BIGINT NULL COMMENT 'vnt_cat_rutas.id_cat_rutas (referencia lógica)',
    id_plantilla                BIGINT UNSIGNED NULL,
    tipo_envio                 VARCHAR(30) NOT NULL DEFAULT 'RECORDATORIO_SALDO',
    estatus                    VARCHAR(20) NOT NULL DEFAULT 'BORRADOR' COMMENT 'BORRADOR,PROGRAMADO,EN_PROCESO,PAUSADO,COMPLETADO,CANCELADO',
    fecha_programada            DATETIME NULL,
    lote_tamanio                INT NOT NULL DEFAULT 25 COMMENT 'clientes enviados por lote',
    intervalo_segundos_lote     INT NOT NULL DEFAULT 60 COMMENT 'espera entre lotes',
    dias_min_vencido            INT NOT NULL DEFAULT 1 COMMENT 'filtro de antigüedad para armar el bloque',
    cve_usuario_creacion        VARCHAR(45) NULL,
    fecha_creacion               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_bloque),
    KEY ix_bloque_estatus (estatus),
    CONSTRAINT fk_bloque_plantilla FOREIGN KEY (id_plantilla) REFERENCES cob_plantilla_mensaje (id_plantilla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Clientes incluidos/curados dentro de un bloque (aquí es donde el
-- administrador agrega/quita clientes antes de disparar el envío).
-- ---------------------------------------------------------------------
CREATE TABLE cob_bloque_cliente (
    id_bloque_cliente     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_bloque             BIGINT UNSIGNED NOT NULL,
    id_cliente            BIGINT NOT NULL,
    id_telefono           BIGINT UNSIGNED NULL,
    saldo_snapshot         DECIMAL(12,2) NULL,
    dias_vencido_snapshot  INT NULL,
    orden_envio            INT NOT NULL DEFAULT 0,
    estatus                VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE,ENVIADO,ENTREGADO,LEIDO,RESPONDIDO,ERROR,EXCLUIDO',
    motivo_exclusion       VARCHAR(255) NULL,
    fecha_envio            DATETIME NULL,
    fecha_actualizacion    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_bloque_cliente),
    UNIQUE KEY ux_bloque_cliente (id_bloque, id_cliente),
    KEY ix_bloquecliente_estatus (id_bloque, estatus),
    CONSTRAINT fk_bc_bloque FOREIGN KEY (id_bloque) REFERENCES cob_bloque_envio (id_bloque),
    CONSTRAINT fk_bc_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente),
    CONSTRAINT fk_bc_telefono FOREIGN KEY (id_telefono) REFERENCES cob_telefono_cliente (id_telefono)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Conversación lógica de cobranza por cliente (agrupa mensajes; una
-- conversación puede reabrirse fuera de la ventana de 24h de WhatsApp).
-- ---------------------------------------------------------------------
CREATE TABLE cob_conversacion (
    id_conversacion        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cliente             BIGINT NOT NULL,
    id_bloque              BIGINT UNSIGNED NULL,
    telefono_e164          VARCHAR(20) NOT NULL,
    estatus                VARCHAR(25) NOT NULL DEFAULT 'ABIERTA' COMMENT 'ABIERTA,ESPERANDO_CLIENTE,ESPERANDO_REVISION_HUMANA,CERRADA',
    requiere_atencion_humana TINYINT(1) NOT NULL DEFAULT 0,
    saldo_al_abrir          DECIMAL(12,2) NULL,
    id_agente_asignado      BIGINT NULL COMMENT 'vnt_cat_agentes.id_agente cuando se escala a humano',
    fecha_apertura           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_ultima_actividad   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre             DATETIME NULL,
    PRIMARY KEY (id_conversacion),
    KEY ix_conv_cliente (id_cliente),
    KEY ix_conv_estatus (estatus),
    CONSTRAINT fk_conv_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente),
    CONSTRAINT fk_conv_bloque FOREIGN KEY (id_bloque) REFERENCES cob_bloque_envio (id_bloque)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Mensajes individuales (entrantes y salientes) de cada conversación.
-- ---------------------------------------------------------------------
CREATE TABLE cob_mensaje (
    id_mensaje             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_conversacion        BIGINT UNSIGNED NOT NULL,
    direccion              VARCHAR(10) NOT NULL COMMENT 'ENTRANTE, SALIENTE',
    tipo_contenido         VARCHAR(20) NOT NULL DEFAULT 'TEXTO' COMMENT 'TEXTO,IMAGEN,DOCUMENTO,PLANTILLA,AUDIO',
    contenido_texto        TEXT NULL,
    url_media              VARCHAR(500) NULL,
    whatsapp_message_id    VARCHAR(100) NULL,
    whatsapp_status        VARCHAR(20) NULL COMMENT 'SENT,DELIVERED,READ,FAILED',
    generado_por           VARCHAR(20) NOT NULL DEFAULT 'SISTEMA' COMMENT 'SISTEMA,IA,AGENTE_HUMANO,CLIENTE',
    metadata_json          JSON NULL COMMENT 'payload crudo del proveedor / análisis de IA',
    fecha_creacion         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_mensaje),
    KEY ix_msg_conversacion (id_conversacion, fecha_creacion),
    UNIQUE KEY ux_msg_whatsapp_id (whatsapp_message_id),
    CONSTRAINT fk_msg_conversacion FOREIGN KEY (id_conversacion) REFERENCES cob_conversacion (id_conversacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Comprobantes de pago detectados dentro de una conversación (visión IA)
-- ---------------------------------------------------------------------
CREATE TABLE cob_comprobante_pago (
    id_comprobante         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_mensaje             BIGINT UNSIGNED NOT NULL,
    id_cliente             BIGINT NOT NULL,
    monto_detectado        DECIMAL(12,2) NULL,
    fecha_pago_detectada   DATE NULL,
    referencia_detectada   VARCHAR(100) NULL,
    banco_detectado        VARCHAR(100) NULL,
    confianza_ia           DECIMAL(4,3) NULL COMMENT '0.000 - 1.000',
    estatus                VARCHAR(25) NOT NULL DEFAULT 'PENDIENTE_VALIDACION' COMMENT 'PENDIENTE_VALIDACION,APLICADO,RECHAZADO,DUPLICADO',
    id_abono_generado      BIGINT NULL COMMENT 'vnt_abono_encabezado.id_abono una vez aplicado en ventas',
    observaciones_ia       VARCHAR(500) NULL,
    fecha_deteccion        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_aplicacion       DATETIME NULL,
    PRIMARY KEY (id_comprobante),
    KEY ix_comprobante_cliente (id_cliente, estatus),
    CONSTRAINT fk_comp_mensaje FOREIGN KEY (id_mensaje) REFERENCES cob_mensaje (id_mensaje),
    CONSTRAINT fk_comp_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Reglas de pronto pago / nota de crédito automática (no existe hoy en
-- apibackendventas; es lógica nueva de este módulo).
-- ---------------------------------------------------------------------
CREATE TABLE cob_regla_pronto_pago (
    id_regla                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_compania                    BIGINT NOT NULL DEFAULT 19,
    nombre                         VARCHAR(150) NOT NULL,
    dias_max_desde_factura         INT NOT NULL COMMENT 'pagar dentro de estos días para calificar',
    pct_descuento                  DECIMAL(5,2) NOT NULL,
    monto_max_autorizado_automatico DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'tope para aplicar SIN revisión humana',
    aplica_a                       VARCHAR(15) NOT NULL DEFAULT 'TODOS' COMMENT 'TODOS,RUTA,CLIENTE',
    id_referencia                  BIGINT NULL COMMENT 'id_ruta o id_cliente según aplica_a',
    activo                         TINYINT(1) NOT NULL DEFAULT 1,
    vigencia_inicio                DATE NOT NULL,
    vigencia_fin                   DATE NULL,
    cve_usuario_creacion           VARCHAR(45) NULL,
    fecha_creacion                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_regla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bitácora de notas de crédito propuestas/generadas automáticamente.
-- ---------------------------------------------------------------------
CREATE TABLE cob_nota_credito_generada (
    id_nota_generada       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_regla               BIGINT UNSIGNED NOT NULL,
    id_cliente             BIGINT NOT NULL,
    id_conversacion        BIGINT UNSIGNED NULL,
    id_cargo_original      BIGINT NULL COMMENT 'vnt_cargo_encabezado.id_cargo',
    id_abono_ndc           BIGINT NULL COMMENT 'vnt_abono_encabezado.id_abono generado en ventas',
    monto_descuento        DECIMAL(12,2) NOT NULL,
    estatus                VARCHAR(25) NOT NULL DEFAULT 'PROPUESTA' COMMENT 'PROPUESTA,APLICADA,RECHAZADA_POR_REGLA,REQUIERE_APROBACION,RECHAZADA_HUMANO',
    justificacion_ia       VARCHAR(500) NULL,
    cve_usuario_aprobo     VARCHAR(45) NULL,
    fecha_generacion       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion       DATETIME NULL,
    PRIMARY KEY (id_nota_generada),
    CONSTRAINT fk_ndc_regla FOREIGN KEY (id_regla) REFERENCES cob_regla_pronto_pago (id_regla),
    CONSTRAINT fk_ndc_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Alertas de fraude / cliente inconsistente detectadas por la IA o
-- reportadas manualmente.
-- ---------------------------------------------------------------------
CREATE TABLE cob_alerta_cliente (
    id_alerta              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cliente             BIGINT NOT NULL,
    id_conversacion        BIGINT UNSIGNED NULL,
    tipo                   VARCHAR(30) NOT NULL COMMENT 'TELEFONO_INVALIDO,CLIENTE_NO_RECONOCE_DEUDA,DATOS_INCONSISTENTES,SIN_RESPUESTA_REPETIDA,POSIBLE_FRAUDE',
    detalle                VARCHAR(500) NULL,
    origen                 VARCHAR(10) NOT NULL DEFAULT 'IA' COMMENT 'IA, MANUAL',
    estatus                VARCHAR(20) NOT NULL DEFAULT 'ABIERTA' COMMENT 'ABIERTA,EN_REVISION,DESCARTADA,CONFIRMADA',
    fecha_creacion         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion       DATETIME NULL,
    PRIMARY KEY (id_alerta),
    KEY ix_alerta_cliente (id_cliente),
    CONSTRAINT fk_alerta_cliente FOREIGN KEY (id_cliente) REFERENCES vnt_cat_clientes (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bitácora de consultas del administrador al agente de datos de cartera
-- ---------------------------------------------------------------------
CREATE TABLE cob_agente_ia_consulta_log (
    id_consulta            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cve_usuario            VARCHAR(45) NOT NULL,
    pregunta               TEXT NOT NULL,
    respuesta              TEXT NULL,
    tool_calls_json        JSON NULL,
    modelo                 VARCHAR(50) NULL,
    fecha_creacion         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_consulta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
