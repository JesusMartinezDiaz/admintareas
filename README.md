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

## Estructura del repo

```
admintareas/
├── backend-cobranza-ia/   Spring Boot (Java 17) - agente IA + orquestación WhatsApp + API admin
├── frontend-cobranza-ia/  Angular 18 (standalone) - panel de administración
└── db/migrations/         Flyway - esquema nuevo (tablas cob_*)
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
- **No existe "pronto pago" ni nota de crédito automática hoy** en
  ventas (lo más parecido son comisiones de cobranza escalonadas, que
  premian al agente, no al cliente). Es lógica nueva de este módulo
  (`cob_regla_pronto_pago`, `ProntoPagoRuleEngine`) y por diseño **nunca
  aplica una NDC directamente**: siempre queda en `PROPUESTA` o
  `REQUIERE_APROBACION` según el tope configurado, para revisión humana.
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

## Qué falta (deliberadamente dejado como TODO)

Este scaffold es honesto sobre lo que no está terminado:

- **`VentasApiClient.registrarAbono(...)` lanza `UnsupportedOperationException`.**
  `apibackendventas` no expone hoy un endpoint para que un sistema externo
  registre un abono/NDC (la lógica de folios + conciliación FIFO en
  `VntAbonoImpl` se invoca solo desde su propio frontend). Hace falta
  que el equipo de ventas exponga ese endpoint (o un endpoint de
  integración con API key) antes de que este módulo pueda aplicar pagos
  o notas de crédito automáticamente. Mientras tanto, todo queda en
  `PENDIENTE_VALIDACION` / `PROPUESTA` para aplicación manual.
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
mvn spring-boot:run   # aplica db/migrations/*.sql vía Flyway automáticamente

# Frontend
cd frontend-cobranza-ia
npm install
npm start   # http://localhost:4200, apunta a environment.ts (localhost:8081)
```

Ambos se validaron en esta sesión: `mvn -q compile` (backend, contra el
SDK real `anthropic-java:2.34.0`) y `ng build` (frontend) terminan sin
errores.

## Roadmap sugerido (siguiente)

1. Exponer en `apibackendventas` un endpoint de integración para aplicar
   abonos/NDC (con API key), y quitar el stub de `VentasApiClient`.
2. Cargar `cob_telefono_cliente` desde los teléfonos existentes +
   confirmar opt-in de WhatsApp por cliente (requisito legal de Meta).
3. Verificar firma de webhook + mover el procesamiento a una cola.
4. Definir en negocio los umbrales reales de `cob_regla_pronto_pago`
   (hoy hay un ejemplo de 3% a 5 días en `db/migrations/V2__...seed_dev.sql`).
5. Pruebas end-to-end con el WhatsApp Business Sandbox de Meta antes de
   pedir el número de producción.
