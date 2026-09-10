# Agente Virtual de Cobranza — Biochem Systems

Sistema para contactar por WhatsApp, de forma controlada y por bloques,
a los clientes con cartera vencida de Biochem Systems: informarles su
saldo, negociar el pago, detectar y aplicar comprobantes de pago, ofrecer
notas de crédito por pronto pago cuando la política lo permite, y marcar
alertas de posible fraude o clientes inconsistentes — todo con un agente
de IA (Claude) y con un panel de administración para curar a quién se
contacta y en qué orden.

Este repo es el punto de partida: **scaffold funcional, no un sistema
terminado**. Compila y arranca, pero varias integraciones externas
(WhatsApp, aplicación real de pagos en ventas) están dejadas como stubs
explícitos — ver "Qué falta" más abajo.

**¿Quieres correrlo ya?** Ver [`RUN_LOCAL.md`](./RUN_LOCAL.md) — guía
validada paso a paso (Docker Compose o Maven/MySQL directo), con los
bugs reales que salieron al probarlo y su fix ya aplicado en el repo.

## Estructura del repo

```
admintareas/
├── backend-cobranza-ia/          Spring Boot (Java 17) - agente IA + orquestación WhatsApp + API admin
├── biochem-frontend-integration/ Pantallas + parches para el Angular 6 real de Biochem (ver INSTRUCCIONES.md)
├── apibackendventas-local-run/   Perfil para correr el backend de ventas real fuera de WildFly/JBoss
├── db/migrations/                Flyway - esquema nuevo (tablas cob_*)
├── db/local-dev/                 Esquema mínimo + datos sintéticos para desarrollo local
├── docker-compose.yml            Levanta MySQL + backend-cobranza-ia en modo mock con un comando
└── RUN_LOCAL.md                  Guía de arranque local, validada paso a paso
```

## Cómo se decidió la arquitectura

Antes de escribir código se revisó el backend de ventas existente
(`apibackendventas`, adjuntado como zip) para no duplicar ni contradecir
su lógica de negocio. Hallazgos clave que moldearon el diseño:

- **`id_compania`**: 19 = Biochem Systems, 20 = All 4 Pet's. Este módulo
  opera por defecto sobre 19.
- **La antigüedad de saldos y la conciliación FIFO de pagos ya existen**
  en `apibackendventas` (`VntEstadoCuentaImpl`, `CarteraCompaniaService`,
  `VntReportesCobranzaController`), con varias reglas de "días vencidos"
  ligeramente distintas entre reportes. En vez de reimplementarlas (y
  arriesgar inconsistencias), **este módulo las consume vía HTTP**
  (`VentasApiClient`) y no vuelve a calcular saldos por su cuenta.
- **No existe una "regla de pronto pago" (cuándo/cuánto descontar) hoy**
  en ventas (lo más parecido son comisiones de cobranza escalonadas, que
  premian al agente, no al cliente) - esa decisión es lógica nueva de
  este módulo (`cob_regla_pronto_pago`, `ProntoPagoRuleEngine`). Pero el
  mecanismo para *aplicar* la nota de crédito **sí existe y ya se usa**:
  `POST /vntAbono/saveNotaCreditoPP` en `apibackendventas` calcula el
  prorrateo fiscal (IEPS/IVA) contra una factura específica y descuenta
  su saldo de inmediato. Este módulo decide el %/monto y quién califica;
  ventas hace la aplicación fiscal real. Dentro del tope de
  auto-aprobación (`cobranza.monto-max-nota-credito-auto-sin-aprobacion`)
  se aplica sola; si lo excede, o si no se identificó la factura, queda
  `REQUIERE_APROBACION` para que un humano la apruebe desde
  `POST /notas-credito/{id}/aprobar`.
- **Identificación de cliente por teléfono** ya existe para llamadas
  Twilio (`TwilioController.normalizarTelefono` + `getClientByTelefono`):
  normaliza a los últimos 10 dígitos y busca en `vnt_cat_clientes`. Se
  replicó el mismo criterio para WhatsApp (`ClienteLookupRepository`),
  revisando además los 4 campos de teléfono del cliente, no solo
  `telefono_comercial`.
- **Cliente → Ruta → Agente**: `vnt_cat_clientes → vnt_rutas_asociaciones
  → vnt_rutas_maestras → vnt_cat_rutas → vnt_cat_agentes`. `vnt_cat_rutas`
  ya trae `dias_vencimiento_cartera`, un umbral de "vencido" por ruta que
  vale la pena usar en vez de inventar buckets nuevos.
- ⚠️ **Seguridad detectada**: `apibackendventas/application.properties`
  trae credenciales reales de Twilio, AWS y OpenPay en texto plano,
  commiteadas al repo. No es parte de este módulo, pero **se recomienda
  rotarlas** antes de seguir exponiendo ese repo, y usar variables de
  entorno/vault para las credenciales nuevas de WhatsApp y Claude (así
  quedaron `application.yml` de este módulo).

## Diseño del agente de IA

Se usa **Claude** (Anthropic) en dos roles distintos, ambos con *tool
use* (function calling) para que el modelo nunca invente cifras y solo
pueda actuar dentro de límites explícitos:

1. **Agente conversacional de WhatsApp** (`ConversationOrchestratorService`)
   — uno por conversación cliente↔empresa. Recibe el mensaje entrante
   (texto o imagen de comprobante), tiene el saldo del cliente en su
   system prompt, y puede usar 4 herramientas:
   - `registrar_comprobante_pago` — registra lo que detecta en la imagen
     (monto, fecha, banco, referencia, confianza). **Nunca aplica el
     pago**: queda `PENDIENTE_VALIDACION`.
   - `evaluar_pronto_pago` — consulta `cob_regla_pronto_pago` antes de
     ofrecer cualquier descuento; el modelo no puede inventar un %.
   - `marcar_alerta_cliente` — cliente no reconoce la deuda, datos
     inconsistentes, posible fraude.
   - `escalar_atencion_humana` — corta la automatización y notifica al
     panel de conversaciones.

2. **Agente de datos para el administrador** (`CarteraAdminAiService`,
   endpoint `POST /agente-ia/chat`) — responde preguntas en lenguaje
   natural sobre la cartera usando herramientas de solo lectura
   (`consultar_antiguedad_saldos`, `consultar_estado_cuenta_cliente`,
   `consultar_alertas_abiertas`, `consultar_bloques_activos`).

Ambos usan un **loop manual de tool use** (no el `ToolRunner` beta),
porque las herramientas necesitan inyección de dependencias de Spring
(acceso a repositorios/servicios) y el `ToolRunner` instancia las
herramientas por reflexión sin pasar por el contenedor de Spring.

Modelo por defecto: `claude-opus-5` (configurable en `application.yml`,
`anthropic.modelo-conversacional` / `anthropic.modelo-agente-datos`).
Se probó la integración compilando contra `anthropic-java:2.34.0` real
(ver "Qué se validó" abajo).

## Envío por bloques y por ruta

- El administrador filtra la cartera vencida de una ruta
  (`GET /bloques/cartera-vencida`), selecciona clientes (o autocompleta),
  y crea un **bloque** (`POST /bloques`) con una plantilla de WhatsApp,
  tamaño de lote e intervalo entre lotes.
- Un scheduler (`CobranzaScheduler`, tick cada 15s) revisa los bloques
  `EN_PROCESO` y despacha el siguiente lote cuando ya pasó el intervalo
  configurado — así se cumple el requisito de "controlado y por bloques"
  sin necesitar colas/infra adicional en esta primera versión.
- Cada envío abre una `cob_conversacion` y registra el mensaje saliente;
  las respuestas del cliente llegan por el webhook de WhatsApp y las
  procesa el agente conversacional.

## Aplicación de pagos y notas de crédito (ya conectado a los endpoints reales)

`VentasApiClient` ya llama a los endpoints reales de `apibackendventas`
(confirmados leyendo `VntAbonoController`/`VntAbonoImpl` fuente, no
inventados):

- **`registrarPago(...)`** → `POST /vntAbono/savePagos`. Un comprobante
  de pago detectado por visión en WhatsApp **nunca se aplica solo**: la
  IA solo lo registra (`PENDIENTE_VALIDACION`); un humano lo confirma
  desde `POST /comprobantes/{id}/aplicar`, que es lo que dispara este
  llamado real. El riesgo de que la IA lea mal un monto en una foto es
  demasiado alto para saltarse esa revisión.
- **`aplicarNotaCreditoProntoPago(...)`** → `POST /vntAbono/saveNotaCreditoPP`.
  Esta sí se auto-aplica cuando la IA identifica la factura
  (`idCargoOriginal`) y el descuento cae dentro del tope configurado -
  es la única automatización de dinero real que corre sin humano en el
  loop, tal como pediste ("se les puede aplicar... de manera automática,
  siempre y cuando esté dentro de las reglas").

**Pendiente de confirmar con negocio**: `ventas-api.id-tipo-documento-pago`
(qué `id_tipo_documento` usar al registrar un pago - `apibackendventas`
no lo fija solo). Sin configurar, se resuelve dinámicamente consultando
`vnt_cat_tipo_documento`, lo cual es correcto solo si existe un único
tipo de pago genérico - revísalo antes de producción.

**Nota sobre validación en esta sesión**: el código de
`VentasApiClientImpl` se construyó leyendo directamente el DTO/entidad/
endpoint reales (nombres de campo y rutas exactos, no adivinados).
Intenté además una prueba en vivo end-to-end contra `apibackendventas`
corriendo en este sandbox con un esquema mínimo sintético; el proceso
resultó inestable en este entorno (aplicación muy grande, recursos
limitados del sandbox) y no llegué a una confirmación 200 limpia antes
de que el proceso muriera. Recomiendo la primera prueba real contra un
ambiente de desarrollo/staging de verdad, no replicar este intento aquí.

## Qué más falta (deliberadamente dejado como TODO)

- **Firma del webhook de WhatsApp no verificada** (`X-Hub-Signature-256`)
  — necesario antes de producción.
- **Sin colas/reintentos** para envío de WhatsApp — para volumen alto,
  cambiar `EnvioLoteServiceImpl` por una cola real.
- **Catálogo de teléfonos** (`cob_telefono_cliente`) está vacío hasta que
  se corra una carga inicial desde los 4 campos de `vnt_cat_clientes`
  (`telefono_comercial/pagos/bodega/ventas`) con opt-in de WhatsApp.
- Sin autenticación en los endpoints del propio `cobranza-ia` (pensado
  para exponerse detrás del mismo API Gateway/SSO que ya use el resto
  del sistema).
- **Sin pantallas en el admin** para la bandeja de comprobantes
  pendientes (`GET /comprobantes`) ni notas de crédito que requieren
  aprobación (`GET /notas-credito`) - los endpoints ya existen, falta
  el Angular. Es el siguiente paso lógico de UI.

## ¿Puedo conectarme yo directamente a tu base de datos?

Técnicamente sí es posible (hay conectividad de red saliente en este
entorno), pero **no lo recomiendo para su base de datos productiva**, por
dos razones:

1. **Seguridad**: compartir credenciales de una base de datos con datos
   financieros reales de clientes en una sesión de este tipo amplía
   innecesariamente la superficie de exposición de esas credenciales.
2. **No hace falta para el diseño elegido**: el agente de datos ya
   consulta la cartera a través de la API Java (`VentasApiClient` /
   `AdminToolDispatcher`), nunca con SQL directo generado por el modelo.
   Es el patrón más seguro y auditable: el modelo nunca ve una
   contraseña ni puede ejecutar una consulta arbitraria.

Si igual quieren que analice la base de datos puntualmente (por ejemplo
para validar el esquema real contra las migraciones de este repo), lo
más seguro es:

- Un **usuario de solo lectura**, con permisos limitados a las tablas
  necesarias, en un ambiente de **desarrollo/staging** (no productivo).
- Compartir esas credenciales como variables de entorno de la sesión,
  no pegadas en el chat.
- Revocarlas/rotarlas después del análisis.

## Puesta en marcha (desarrollo)

```bash
# Backend
cd backend-cobranza-ia
export DB_URL=jdbc:mysql://localhost:3306/biochem_ventas
export DB_USER=cobranza_ia_app
export DB_PASSWORD=...
export ANTHROPIC_API_KEY=...
export WHATSAPP_PHONE_NUMBER_ID=...
export WHATSAPP_ACCESS_TOKEN=...
export WHATSAPP_WEBHOOK_VERIFY_TOKEN=...
export VENTAS_API_BASE_URL=http://localhost:8080/apibackendventas
export VENTAS_API_ID_TIPO_DOCUMENTO_PAGO=...   # confirmar con negocio, ver arriba
mvn spring-boot:run   # aplica db/migrations/*.sql vía Flyway automáticamente
```

`mvn -q compile` se validó en esta sesión (SDK real `anthropic-java:2.34.0`,
sin errores). Para correr todo junto sin tocar tu base de datos real, ver
[`RUN_LOCAL.md`](./RUN_LOCAL.md). Para el frontend real (Angular 6 de
Biochem, no un scaffold aparte), ver
[`biochem-frontend-integration/INSTRUCCIONES.md`](./biochem-frontend-integration/INSTRUCCIONES.md).

## Roadmap sugerido (siguiente)

1. Confirmar con negocio `ventas-api.id-tipo-documento-pago` y los
   umbrales reales de `cob_regla_pronto_pago` (hoy hay un ejemplo de 3%
   a 5 días en `db/migrations/V2__...seed_dev.sql`).
2. Probar `registrarPago`/`aplicarNotaCreditoProntoPago` contra un
   ambiente de desarrollo/staging real de `apibackendventas` (ver nota
   arriba sobre por qué no se pudo validar en vivo en este sandbox).
3. Pantallas de admin para la bandeja de comprobantes pendientes y
   notas de crédito que requieren aprobación (los endpoints ya existen).
4. Cargar `cob_telefono_cliente` desde los teléfonos existentes +
   confirmar opt-in de WhatsApp por cliente (requisito legal de Meta).
5. Verificar firma de webhook + mover el procesamiento a una cola.
6. Pruebas end-to-end con el WhatsApp Business Sandbox de Meta antes de
   pedir el número de producción.
