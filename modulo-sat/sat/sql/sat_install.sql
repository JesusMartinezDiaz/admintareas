-- =====================================================================
-- Módulo SAT (descarga masiva CFDI + reporte fiscal + contabilidad)
-- Esquema completo. Compatible con MySQL 5.6+/MariaDB 10.1+.
--
-- Instalación nueva:   ejecutar este archivo completo.
-- Instalación previa:  ejecutar `php sat/cron/instalar.php` (o abrir
--                      sat/instalar.php como administrador). El instalador
--                      ejecuta este archivo (todas las tablas usan
--                      IF NOT EXISTS) y después agrega las columnas nuevas
--                      que falten en las tablas que ya existían.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Empresas y e.firma
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sat_empresas (
  id_empresa      INT AUTO_INCREMENT PRIMARY KEY,
  razon_social    VARCHAR(255) NOT NULL,
  rfc             VARCHAR(13)  NOT NULL,
  regimen_fiscal  VARCHAR(10)  NULL,
  tipo_persona    ENUM('MORAL','FISICA') NOT NULL DEFAULT 'MORAL',
  email_contacto  VARCHAR(150) NULL,
  telefono        VARCHAR(30)  NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_alta      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_sat_empresas_rfc (rfc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_certificados (
  id_certificado    INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa        INT NOT NULL,
  archivo_cer       VARCHAR(255) NOT NULL,
  archivo_key       VARCHAR(255) NOT NULL,
  password_key      TEXT NOT NULL,
  no_certificado    VARCHAR(60) NULL,
  rfc_certificado   VARCHAR(13) NULL,
  fecha_inicio      DATETIME NULL,
  fecha_vencimiento DATETIME NULL,
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  usuario_alta      VARCHAR(60) NULL,
  fecha_alta        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sat_cert_empresa (id_empresa, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Programaciones (peticiones automáticas al SAT)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sat_programaciones (
  id_programacion    INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa         INT NOT NULL,
  nombre             VARCHAR(120) NOT NULL,
  tipo_descarga      ENUM('EMITIDAS','RECIBIDAS','AMBAS') NOT NULL DEFAULT 'AMBAS',
  tipo_archivo       ENUM('CFDI','METADATA') NOT NULL DEFAULT 'CFDI',
  tipo_comprobante   VARCHAR(1) NULL COMMENT 'NULL=todos, I,E,P,N,T',
  -- Cuándo se ejecuta
  frecuencia         ENUM('CADA_HORAS','DIARIA','SEMANAL','MENSUAL') NOT NULL DEFAULT 'DIARIA',
  intervalo_horas    INT NOT NULL DEFAULT 6,
  hora_ejecucion     TIME NOT NULL DEFAULT '06:00:00',
  dias_semana        VARCHAR(20) NULL COMMENT 'ISO 1=lunes..7=domingo, separado por comas',
  dia_mes            TINYINT NOT NULL DEFAULT 1 COMMENT '1-28, 31 = último día',
  -- Qué periodo se le pide al SAT
  periodo            ENUM('DIAS_ATRAS','AYER','MES_ACTUAL','MES_ANTERIOR','MES_ACTUAL_Y_ANTERIOR') NOT NULL DEFAULT 'DIAS_ATRAS',
  dias_atras         INT NOT NULL DEFAULT 3,
  -- Qué hacer con el resultado
  auto_descargar     TINYINT(1) NOT NULL DEFAULT 1,
  auto_procesar      TINYINT(1) NOT NULL DEFAULT 1,
  activo             TINYINT(1) NOT NULL DEFAULT 1,
  ultima_ejecucion   DATETIME NULL,
  proxima_ejecucion  DATETIME NULL,
  ultimo_resultado   VARCHAR(500) NULL,
  ejecuciones        INT NOT NULL DEFAULT 0,
  usuario_alta       VARCHAR(60) NULL,
  fecha_alta         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion DATETIME NULL,
  KEY idx_sat_prog_proxima (activo, proxima_ejecucion),
  KEY idx_sat_prog_empresa (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Solicitudes y paquetes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sat_solicitudes_descarga (
  id_solicitud              INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa                INT NOT NULL,
  id_programacion           INT NULL,
  origen                    ENUM('MANUAL','PROGRAMADA') NOT NULL DEFAULT 'MANUAL',
  tipo_descarga             VARCHAR(10) NOT NULL,
  tipo_archivo              VARCHAR(10) NOT NULL DEFAULT 'CFDI',
  tipo_comprobante          VARCHAR(1) NULL,
  fecha_inicial             DATE NOT NULL,
  fecha_final               DATE NOT NULL,
  fecha_hora_inicial        DATETIME NULL,
  fecha_hora_final          DATETIME NULL,
  request_id                VARCHAR(60) NULL,
  estado_solicitud          VARCHAR(30) NOT NULL DEFAULT 'SOLICITADA',
  codigo_estado             VARCHAR(10) NULL,
  mensaje_sat               VARCHAR(500) NULL,
  total_cfdi                INT NOT NULL DEFAULT 0,
  verificaciones            INT NOT NULL DEFAULT 0,
  reintentos                INT NOT NULL DEFAULT 0,
  auto_descargar            TINYINT(1) NOT NULL DEFAULT 0,
  auto_procesar             TINYINT(1) NOT NULL DEFAULT 0,
  procesada                 TINYINT(1) NOT NULL DEFAULT 0,
  xml_procesados            INT NOT NULL DEFAULT 0,
  fecha_ultima_verificacion DATETIME NULL,
  fecha_procesado           DATETIME NULL,
  usuario_alta              VARCHAR(60) NULL,
  fecha_alta                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sat_sol_estado (estado_solicitud),
  KEY idx_sat_sol_empresa (id_empresa, fecha_alta),
  KEY idx_sat_sol_prog (id_programacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_paquetes_descarga (
  id_paquete      INT AUTO_INCREMENT PRIMARY KEY,
  id_solicitud    INT NOT NULL,
  id_paquete_sat  VARCHAR(80) NOT NULL,
  descargado      TINYINT(1) NOT NULL DEFAULT 0,
  intentos        INT NOT NULL DEFAULT 0,
  ultimo_error    VARCHAR(500) NULL,
  archivo_zip     VARCHAR(255) NULL,
  fecha_descarga  DATETIME NULL,
  fecha_alta      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_sat_paquete (id_solicitud, id_paquete_sat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- CFDI
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sat_cfdi (
  id_cfdi                     INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa                  INT NOT NULL,
  id_solicitud                INT NULL,
  uuid                        VARCHAR(40) NOT NULL,
  version                     VARCHAR(5) NULL,
  tipo_relacion_empresa       ENUM('EMITIDA','RECIBIDA') NOT NULL,
  rfc_emisor                  VARCHAR(13) NULL,
  nombre_emisor               VARCHAR(255) NULL,
  regimen_emisor              VARCHAR(10) NULL,
  rfc_receptor                VARCHAR(13) NULL,
  nombre_receptor             VARCHAR(255) NULL,
  regimen_receptor            VARCHAR(10) NULL,
  fecha_emision               DATETIME NULL,
  fecha_timbrado              DATETIME NULL,
  tipo_comprobante            VARCHAR(1) NULL,
  serie                       VARCHAR(40) NULL,
  folio                       VARCHAR(60) NULL,
  moneda                      VARCHAR(5) NULL,
  tipo_cambio                 DECIMAL(18,6) NOT NULL DEFAULT 1,
  subtotal                    DECIMAL(18,2) NOT NULL DEFAULT 0,
  descuento                   DECIMAL(18,2) NOT NULL DEFAULT 0,
  iva                         DECIMAL(18,2) NOT NULL DEFAULT 0,
  ieps                        DECIMAL(18,2) NOT NULL DEFAULT 0,
  iva_retenido                DECIMAL(18,2) NOT NULL DEFAULT 0,
  isr_retenido                DECIMAL(18,2) NOT NULL DEFAULT 0,
  base_iva_16                 DECIMAL(18,2) NOT NULL DEFAULT 0,
  base_iva_8                  DECIMAL(18,2) NOT NULL DEFAULT 0,
  base_iva_0                  DECIMAL(18,2) NOT NULL DEFAULT 0,
  base_iva_exento             DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_impuestos_retenidos   DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_impuestos_trasladados DECIMAL(18,2) NOT NULL DEFAULT 0,
  total                       DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_pagos                 DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Solo tipo P: monto total de pagos (MXN)',
  metodo_pago                 VARCHAR(3) NULL,
  forma_pago                  VARCHAR(3) NULL,
  uso_cfdi                    VARCHAR(5) NULL,
  lugar_expedicion            VARCHAR(5) NULL,
  estatus_sat                 VARCHAR(20) NOT NULL DEFAULT 'VIGENTE',
  fecha_cancelacion           DATETIME NULL,
  archivo_xml                 VARCHAR(500) NULL,
  origen_dato                 ENUM('XML','METADATA') NOT NULL DEFAULT 'XML' COMMENT 'METADATA = aún no se descarga el XML',
  fecha_alta                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion         DATETIME NULL,
  UNIQUE KEY uk_sat_cfdi_empresa_uuid (id_empresa, uuid),
  KEY idx_sat_cfdi_fecha (id_empresa, fecha_emision),
  KEY idx_sat_cfdi_tipo (id_empresa, tipo_relacion_empresa, tipo_comprobante),
  KEY idx_sat_cfdi_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_cfdi_conceptos (
  id_concepto      INT AUTO_INCREMENT PRIMARY KEY,
  id_cfdi          INT NOT NULL,
  clave_prod_serv  VARCHAR(10) NULL,
  no_identificacion VARCHAR(100) NULL,
  descripcion      TEXT NULL,
  cantidad         DECIMAL(18,6) NOT NULL DEFAULT 0,
  clave_unidad     VARCHAR(10) NULL,
  unidad           VARCHAR(50) NULL,
  valor_unitario   DECIMAL(18,6) NOT NULL DEFAULT 0,
  importe          DECIMAL(18,2) NOT NULL DEFAULT 0,
  descuento        DECIMAL(18,2) NOT NULL DEFAULT 0,
  objeto_imp       VARCHAR(2) NULL,
  KEY idx_sat_conc_cfdi (id_cfdi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Se conserva por compatibilidad con la versión anterior (ya no se llena).
CREATE TABLE IF NOT EXISTS sat_cfdi_impuestos (
  id_impuesto INT AUTO_INCREMENT PRIMARY KEY,
  id_cfdi     INT NOT NULL,
  tipo        VARCHAR(20) NULL,
  impuesto    VARCHAR(5) NULL,
  importe     DECIMAL(18,2) NOT NULL DEFAULT 0,
  KEY idx_sat_imp_cfdi (id_cfdi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_cfdi_impuestos_detalle (
  id_impuesto_detalle INT AUTO_INCREMENT PRIMARY KEY,
  id_cfdi      INT NOT NULL,
  id_concepto  INT NULL,
  nivel        ENUM('CFDI','CONCEPTO') NOT NULL,
  movimiento   ENUM('TRASLADO','RETENCION') NOT NULL,
  impuesto     VARCHAR(5) NULL,
  tipo_factor  VARCHAR(10) NULL,
  tasa_cuota   DECIMAL(10,6) NULL,
  base         DECIMAL(18,2) NOT NULL DEFAULT 0,
  importe      DECIMAL(18,2) NOT NULL DEFAULT 0,
  KEY idx_sat_impd_cfdi (id_cfdi, nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CFDI relacionados (notas de crédito -> factura, sustituciones, etc.)
CREATE TABLE IF NOT EXISTS sat_cfdi_relacionados (
  id_relacionado    INT AUTO_INCREMENT PRIMARY KEY,
  id_cfdi           INT NOT NULL,
  tipo_relacion     VARCHAR(3) NULL COMMENT '01 nota de crédito, 04 sustitución, 07 anticipo...',
  uuid_relacionado  VARCHAR(40) NOT NULL,
  KEY idx_sat_rel_cfdi (id_cfdi),
  KEY idx_sat_rel_uuid (uuid_relacionado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Complemento de pagos (1.0 y 2.0)
CREATE TABLE IF NOT EXISTS sat_cfdi_pagos (
  id_pago       INT AUTO_INCREMENT PRIMARY KEY,
  id_cfdi       INT NOT NULL,
  version_pago  VARCHAR(5) NULL,
  fecha_pago    DATETIME NULL,
  forma_pago    VARCHAR(3) NULL,
  moneda        VARCHAR(5) NULL,
  tipo_cambio   DECIMAL(18,6) NOT NULL DEFAULT 1,
  monto         DECIMAL(18,2) NOT NULL DEFAULT 0,
  num_operacion VARCHAR(100) NULL,
  KEY idx_sat_pago_cfdi (id_cfdi),
  KEY idx_sat_pago_fecha (fecha_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_cfdi_pagos_doctos (
  id_pago_docto      INT AUTO_INCREMENT PRIMARY KEY,
  id_pago            INT NOT NULL,
  id_cfdi            INT NOT NULL COMMENT 'CFDI tipo P',
  uuid_documento     VARCHAR(40) NOT NULL COMMENT 'Factura PPD pagada',
  serie              VARCHAR(40) NULL,
  folio              VARCHAR(60) NULL,
  moneda_dr          VARCHAR(5) NULL,
  equivalencia_dr    DECIMAL(18,6) NOT NULL DEFAULT 1,
  num_parcialidad    INT NULL,
  imp_saldo_ant      DECIMAL(18,2) NOT NULL DEFAULT 0,
  imp_pagado         DECIMAL(18,2) NOT NULL DEFAULT 0,
  imp_saldo_insoluto DECIMAL(18,2) NOT NULL DEFAULT 0,
  objeto_imp_dr      VARCHAR(2) NULL,
  -- Importes en MXN, ya convertidos con el tipo de cambio del pago
  base_iva_mxn       DECIMAL(18,2) NOT NULL DEFAULT 0,
  iva_mxn            DECIMAL(18,2) NOT NULL DEFAULT 0,
  ieps_mxn           DECIMAL(18,2) NOT NULL DEFAULT 0,
  iva_retenido_mxn   DECIMAL(18,2) NOT NULL DEFAULT 0,
  isr_retenido_mxn   DECIMAL(18,2) NOT NULL DEFAULT 0,
  pagado_mxn         DECIMAL(18,2) NOT NULL DEFAULT 0,
  iva_estimado       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = pagos 1.0, IVA prorrateado desde la factura',
  KEY idx_sat_pdoc_pago (id_pago),
  KEY idx_sat_pdoc_cfdi (id_cfdi),
  KEY idx_sat_pdoc_uuid (uuid_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bitácora
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sat_log_procesos (
  id_log        INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa    INT NULL,
  id_solicitud  INT NULL,
  proceso       VARCHAR(40) NOT NULL,
  estatus       VARCHAR(10) NOT NULL,
  mensaje       TEXT NULL,
  usuario       VARCHAR(60) NULL,
  fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sat_log_fecha (fecha),
  KEY idx_sat_log_sol (id_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Contabilidad automática (V2)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sat_catalogo_agrupador (
  codigo  VARCHAR(10) PRIMARY KEY,
  nombre  VARCHAR(200) NOT NULL,
  activo  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO sat_catalogo_agrupador(codigo,nombre) VALUES
('102.01','Bancos nacionales'),('105.01','Clientes nacionales'),('107.05','Otros deudores diversos'),
('113.01','IVA a favor'),('118.01','IVA acreditable pagado'),('119.01','IVA pendiente de pago'),
('201.01','Proveedores nacionales'),('205.06','Otros acreedores diversos'),('208.01','IVA trasladado cobrado'),
('209.01','IVA trasladado no cobrado'),('216.04','Impuestos retenidos de ISR por servicios profesionales'),
('216.10','Impuestos retenidos de IVA'),('401.01','Ventas y/o servicios gravados a la tasa general'),
('402.01','Devoluciones, descuentos o bonificaciones sobre ventas'),('501.01','Costo de venta'),
('601.84','Otros gastos generales'),('603.84','Otros gastos de administración');

CREATE TABLE IF NOT EXISTS sat_cuentas_contables (
  id_cuenta           INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa          INT NOT NULL,
  numero_cuenta       VARCHAR(30) NOT NULL,
  nombre              VARCHAR(200) NOT NULL,
  codigo_agrupador    VARCHAR(10) NULL,
  naturaleza          ENUM('D','A') NOT NULL DEFAULT 'D',
  tipo_cuenta         VARCHAR(10) NOT NULL DEFAULT 'GASTO',
  permite_movimientos TINYINT(1) NOT NULL DEFAULT 1,
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_sat_cuenta (id_empresa, numero_cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_config_contable (
  id_empresa                   INT PRIMARY KEY,
  id_cuenta_clientes           INT NULL,
  id_cuenta_proveedores        INT NULL,
  id_cuenta_ingresos_default   INT NULL,
  id_cuenta_gastos_default     INT NULL,
  id_cuenta_iva_trasladado     INT NULL,
  id_cuenta_iva_acreditable    INT NULL,
  id_cuenta_isr_retenido_favor INT NULL,
  id_cuenta_iva_retenido_favor INT NULL,
  id_cuenta_isr_retenido_pagar INT NULL,
  id_cuenta_iva_retenido_pagar INT NULL,
  tolerancia                   DECIMAL(10,2) NOT NULL DEFAULT 0.02,
  auto_contabilizar            TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_reglas_contables (
  id_regla           INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa         INT NOT NULL,
  nombre             VARCHAR(150) NOT NULL,
  tipo_relacion      ENUM('EMITIDA','RECIBIDA','AMBAS') NOT NULL DEFAULT 'AMBAS',
  tipo_comprobante   VARCHAR(1) NULL,
  rfc_tercero        VARCHAR(13) NULL,
  clave_prod_serv    VARCHAR(10) NULL,
  patron_descripcion VARCHAR(200) NULL,
  id_cuenta          INT NOT NULL,
  lado               ENUM('AUTO','CARGO','ABONO') NOT NULL DEFAULT 'AUTO',
  prioridad          INT NOT NULL DEFAULT 100,
  origen             VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
  confianza          DECIMAL(6,4) NOT NULL DEFAULT 1,
  usos               INT NOT NULL DEFAULT 0,
  activo             TINYINT(1) NOT NULL DEFAULT 1,
  fecha_alta         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_ultimo_uso   DATETIME NULL,
  KEY idx_sat_regla_emp (id_empresa, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_reglas_diferencias (
  id_regla_diferencia INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa          INT NOT NULL,
  tipo_relacion       VARCHAR(10) NOT NULL,
  tipo_comprobante    VARCHAR(1) NOT NULL,
  rfc_tercero         VARCHAR(13) NOT NULL,
  id_cuenta           INT NOT NULL,
  lado                ENUM('CARGO','ABONO') NOT NULL,
  confianza           DECIMAL(6,4) NOT NULL DEFAULT 1,
  usos                INT NOT NULL DEFAULT 0,
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  fecha_alta          DATETIME NULL,
  fecha_ultimo_uso    DATETIME NULL,
  UNIQUE KEY uk_sat_regla_dif (id_empresa, tipo_relacion, tipo_comprobante, rfc_tercero, lado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_polizas (
  id_poliza             INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa            INT NOT NULL,
  id_cfdi               INT NOT NULL,
  tipo_poliza           VARCHAR(10) NOT NULL,
  fecha                 DATE NOT NULL,
  concepto              VARCHAR(400) NULL,
  uuid                  VARCHAR(40) NULL,
  estatus               VARCHAR(15) NOT NULL DEFAULT 'BORRADOR',
  origen                VARCHAR(15) NOT NULL DEFAULT 'AUTOMATICA',
  total_cargos          DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_abonos          DECIMAL(18,2) NOT NULL DEFAULT 0,
  mensaje_revision      VARCHAR(400) NULL,
  fecha_contabilizacion DATETIME NULL,
  fecha_alta            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sat_pol_emp (id_empresa, fecha),
  KEY idx_sat_pol_cfdi (id_cfdi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sat_poliza_detalle (
  id_detalle    INT AUTO_INCREMENT PRIMARY KEY,
  id_poliza     INT NOT NULL,
  id_cuenta     INT NOT NULL,
  id_concepto   INT NULL,
  concepto      VARCHAR(400) NULL,
  cargo         DECIMAL(18,2) NOT NULL DEFAULT 0,
  abono         DECIMAL(18,2) NOT NULL DEFAULT 0,
  id_regla      INT NULL,
  confianza     DECIMAL(6,4) NOT NULL DEFAULT 0,
  clasificacion VARCHAR(20) NULL,
  KEY idx_sat_pold_pol (id_poliza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
