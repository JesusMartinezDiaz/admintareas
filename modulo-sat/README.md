# Módulo SAT — Descarga masiva de CFDI, programación y reporte fiscal

Módulo PHP para anexar al sistema actual (mismo esquema que `seguimiento/`:
páginas con `../assets/head.php` / `../assets/footer.php` y endpoints en
`php/` con `../../assets/dbConection.php` + `control.php`).

Se conecta al Web Service de Descarga Masiva del SAT (v1.5) con la e.firma,
descarga CFDI **emitidos y recibidos** (facturas, notas de crédito,
complementos de pago, nómina), los procesa y genera un **reporte fiscal**
(IVA por flujo de efectivo, ISR estimado, PPD pendientes, alertas) y pólizas
contables automáticas.

Compatible con **PHP 7.1.33** (verificado con PHPCompatibility), MySQL 5.6+ / MariaDB, sin Composer.

## Qué cambió respecto al módulo original

| Tema | Antes | Ahora |
|---|---|---|
| Peticiones al SAT | Solo manuales, una por una | **Programación**: cada N horas, diaria, semanal (días elegidos) o mensual; emitidos, recibidos o ambos; periodo relativo (últimos N días, ayer, mes actual, mes anterior) con vista previa de las próximas ejecuciones |
| Cron | Solo verificaba; descarga y proceso manuales | Ejecuta programaciones, verifica, **descarga y procesa** automáticamente, con candado contra ejecuciones simultáneas y marca de vencidas |
| Errores del SAT | Reintento solo para recibidas 404/5006 | Reintento genérico, **división automática** del periodo si excede el tope (5003), "sin información" (5004) como estado propio, reintentos de red, re-agenda a 30 min si el SAT no responde, mensajes de códigos en español |
| Peticiones repetidas | Mismos parámetros → el SAT bloquea (5002) | Cada ejecución desplaza unos segundos el inicio (solo traslape, nunca huecos) |
| Procesamiento XML | Facturas I/E básicas | CFDI 3.3 y 4.0; **complemento de pagos 1.0 y 2.0** (con IVA por documento y conversión de moneda), **CFDI relacionados** (notas de crédito), bases IVA 16/8/0/exento, IEPS, retenciones, tipo de cambio, nómina; **Metadata** para detectar cancelaciones |
| Reporte | Sumaba todo (pagos, NC y PPD mezclados) | IVA **por flujo de efectivo** (PUE al emitir, PPD al cobrar/pagar, NC restando), IVA retenido, ISR (flujo y devengado), PPD pendientes (cuentas por cobrar/pagar), top clientes/proveedores, tendencia 12 meses con gráfica, detalle filtrable, exportación a Excel, impresión y alertas (cancelados, CFDI sin XML, pagos de facturas no descargadas, gastos en efectivo > $2,000, uso S01) |
| Contabilidad | Pólizas en moneda original; detalle huérfano al recalcular | Pólizas en MXN con el tipo de cambio; se borra el detalle al recalcular |
| e.firma | Se guardaba sin validar | Valida contraseña, que el .key corresponda al .cer, RFC de la empresa y vigencia; avisa vencimientos |
| Seguridad | Credenciales de BD en el código, logs SOAP siempre activos, sin control de acceso | Usa la conexión y sesión del sistema; config fuera del repo; logs SOAP solo con `debug_soap`; `.htaccess` en carpetas internas; extracción de ZIP segura; consultas preparadas en todo lo nuevo |
| BD | SQL de instalación no incluido | `sql/sat_install.sql` completo + instalador que **actualiza una instalación existente** sin borrar datos |

## Estructura

```
sat/
├── index.php              Inicio: indicadores, avisos (cron, e.firma por vencer, config)
├── empresas.php           Empresas y e.firma (alta, carga validada, probar conexión)
├── descargas.php          Solicitudes manuales y seguimiento de todas las solicitudes
├── programacion.php       Programaciones automáticas + estado e instrucciones del cron
├── reporte.php            Reporte fiscal
├── contabilidad.php ...   Pólizas, configuración contable, cuentas y reglas (módulo V2)
├── bitacora.php           Bitácora de procesos
├── php/                   Endpoints AJAX (JSON), como en seguimiento/php
├── js/  css/              Scripts y estilos del módulo (prefijo .sat-*)
├── lib/                   Servicios: SatService (SOAP), SatDownloadService, CfdiProcessor,
│                          SchedulerService, TaxReportService, AccountingService, SatSchema
├── cron/cron_sat.php      Cron (CLI) · cron_web.php (por URL con token) · instalar.php
├── config/                sat_config.example.php → copiar a sat_config.php
├── sql/sat_install.sql    Esquema completo
├── uploads/ logs/         Datos (protegidos con .htaccess)
└── tests/                 Prueba de punta a punta con CFDI sintéticos
```

## Instalación

1. Copia la carpeta `sat/` a la raíz del sistema, junto a `seguimiento/` y `assets/`.
   Si vienes del módulo anterior (`SAT/`), copia también su carpeta `uploads/`
   (certificados, paquetes y xml) dentro de `sat/uploads/` para conservar las e.firmas y XML ya descargados.
2. Copia `sat/config/sat_config.example.php` como `sat/config/sat_config.php` y ajusta:
   - `secret_key` (o variable de entorno `SAT_SECRET_KEY`): llave para cifrar las
     contraseñas de e.firma. **Si ya tenías e.firmas cargadas con el módulo anterior,
     usa la misma llave** (si nunca la cambiaste, déjala vacía y funcionarán; la pantalla
     de Inicio avisará que conviene cambiarla y volver a cargar las e.firmas).
   - `db`: `null` para usar la conexión del sistema (`$con`), o los datos de la BD
     donde ya tienes las tablas `sat_*` si están en otra base.
   - `dir_datos`: de preferencia una carpeta **fuera de `public_html`** para los certificados y XML.
   - `usuarios_admin`: usuarios (`$usuario_acceso`) que pueden administrar empresas,
     e.firma y programaciones (vacío = todos los que tengan sesión).
3. Crea/actualiza las tablas (cualquiera de las dos):
   - `php sat/cron/instalar.php`
   - o entra a **SAT → Inicio** y presiona *Instalar / actualizar ahora*.

   El instalador crea lo que falta y agrega columnas nuevas a las tablas existentes
   sin borrar datos. Haz respaldo de la BD antes, como siempre.
4. Agrega el enlace al menú del sistema (en `assets/head.php`), p. ej. `<a href="../sat/index.php">SAT</a>`.
5. Configura el cron cada 15 minutos (cPanel → Cron Jobs):

   ```
   */15 * * * * /usr/local/bin/php -q /home/USUARIO/public_html/sat/cron/cron_sat.php >> /home/USUARIO/public_html/sat/logs/cron_sat.log 2>&1
   ```

   La pantalla **Programación** muestra el comando exacto con las rutas del servidor y
   avisa si el cron dejó de correr. Si el hosting no permite cron por consola, define
   `cron_token` y llama `sat/cron/cron_web.php?token=...` cada 15 minutos.
6. En **Empresas** carga la e.firma (no el CSD) y presiona *Probar*.
7. En **Programación** crea, por ejemplo: *Diario 06:00 · emitidos y recibidos · últimos 3 días*.
   Opcional: una segunda *Semanal · Metadata · mes actual y anterior* para detectar cancelaciones.

**Nginx:** los `.htaccess` no aplican; agrega `location ~ ^/sat/(config|lib|sql|tests|uploads|logs|cron)/ { deny all; }`
(permitiendo `cron/cron_web.php` si lo usas).

## Cómo calcula el reporte

- **IVA trasladado cobrado** = IVA de facturas PUE emitidas en el periodo + IVA de
  los complementos de pago emitidos con `FechaPago` en el periodo − IVA de notas de crédito emitidas.
- **IVA acreditable pagado**: lo mismo con CFDI recibidos (excluye uso `S01`).
- **IVA a cargo / a favor** = trasladado cobrado − IVA retenido por clientes − acreditable pagado.
  El IVA retenido a proveedores se muestra aparte (se entera por separado).
- Pagos 2.0 traen el IVA por documento (`ImpuestosDR`); en pagos 1.0 se prorratea con la factura.
- Montos en otra moneda se convierten con `TipoCambio` / `TipoCambioP` / `EquivalenciaDR`.
- Se excluyen CFDI cancelados y los que solo existen en Metadata (sin XML).

Es una **estimación de apoyo**: no considera saldos a favor anteriores, proporción de
IVA acreditable, coeficiente de utilidad ni ajustes; debe validarlo el contador.

## Pruebas

`sat/tests/test_procesamiento.php --confirmar` (solo contra una BD de pruebas): procesa 7 CFDI
sintéticos (PUE, PPD en USD, pago 2.0, pago 1.0, nota de crédito, CFDI 3.3 con tasa 0%) y un
archivo de Metadata, y valida cada cifra del reporte, las pólizas y el cálculo de programaciones.

Lo que **no** se pudo probar en el entorno de desarrollo: la conexión real con el SAT (sin
acceso de red al SAT ni e.firma real). Esa parte conserva la implementación SOAP/firma del
módulo original, que ya autenticaba correctamente; los cambios en ella son caché del token,
filtro `TipoComprobante`, reintentos de red y logs opcionales. Primera prueba recomendada en
producción: *Empresas → Probar* y una solicitud manual de un día.
