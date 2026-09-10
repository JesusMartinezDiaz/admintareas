# Integración de Cobranza IA en `biochem-systems-front-end`

Este paquete integra las pantallas de **Cobranza IA** (bloques de envío
de WhatsApp por ruta, conversaciones escaladas a humano, y el chat con
el agente de datos de cartera) dentro de tu frontend Angular 6 real,
respetando sus patrones existentes: todo en `AppModule` (sin lazy
loading), `ngx-spinner` + `SweetAlert2` para loading/errores,
`GeneralService.getParams` para query params, y el `AuthInterceptor`
que ya agrega el token — tus servicios nuevos no necesitan tocar
headers de autenticación.

## 1. Copiar los archivos nuevos

Copia toda la carpeta `nuevos-archivos/src/` sobre la raíz de tu
proyecto (son archivos nuevos, no pisan nada existente):

```bash
cp -r nuevos-archivos/src/app/services/ventas/cobranza-ia \
      /ruta/a/tu/proyecto/src/app/services/ventas/

cp -r nuevos-archivos/src/app/modules/ventas/cobranza-ia \
      /ruta/a/tu/proyecto/src/app/modules/ventas/
```

Esto agrega:
- `services/ventas/cobranza-ia/cobranza-ia.service.ts`
- `modules/ventas/cobranza-ia/bloques-cobranza-ia/` (pantalla principal: cartera vencida por ruta + crear bloque)
- `modules/ventas/cobranza-ia/bloque-detalle-cobranza-ia/` (avance del bloque, iniciar/pausar)
- `modules/ventas/cobranza-ia/conversaciones-cobranza-ia/` (bandeja de conversaciones escaladas a humano)
- `modules/ventas/cobranza-ia/agente-ia-chat-cobranza-ia/` (chat con el agente de datos de cartera)

## 2. Aplicar los parches a los 4 archivos compartidos

Desde la raíz de tu proyecto (`biochem-systems-front-end/`):

```bash
git apply /ruta/a/este/paquete/parches/app.module.ts.patch
git apply /ruta/a/este/paquete/parches/app.routing.ts.patch
git apply /ruta/a/este/paquete/parches/aside.component.html.patch
git apply /ruta/a/este/paquete/parches/environment.ts.patch
git apply /ruta/a/este/paquete/parches/environment.prod.ts.patch
git apply /ruta/a/este/paquete/parches/environment.dev.ts.patch
git apply /ruta/a/este/paquete/parches/environment.stage.ts.patch
```

**Ya validé en esta sesión que los 7 parches aplican limpio** contra los
archivos exactos que subiste (`git apply --check` sin errores). Si tu
proyecto cambió desde que lo subiste, `git apply` te va a avisar con un
conflicto en vez de romper algo en silencio — en ese caso, aplica los
cambios a mano viendo el `.patch` (son diffs chicos y legibles).

Qué hace cada parche:
- **`app.module.ts`**: agrega el `import` y la entrada en `declarations`
  de los 4 componentes nuevos (mismo patrón que `EstadoCuentaGeneralComponent`).
- **`app.routing.ts`**: agrega 4 rutas nuevas como hijas de `dashboard`
  (`ventas/cobranza-ia/bloques`, `.../bloques/:idBloque`,
  `.../conversaciones`, `.../agente-ia`) — heredan `AuthGuard` del padre,
  no hace falta agregarlo por ruta.
- **`aside.component.html`**: agrega 3 ítems de menú bajo la sección de
  reportes de Ventas (junto a "Antigüedad de Saldos Nuevo"), apuntando
  a las rutas de arriba.
- **`environment*.ts` (los 4)**: agrega la clave `cobranzaIA` con la URL
  del backend nuevo. **Ajusta las URLs de `dev`/`stage`/`prod` a donde
  realmente vayas a desplegar `backend-cobranza-ia`** (dejé placeholders
  siguiendo el patrón de tus otros backends, pero no son URLs reales).

## 3. Backend

El backend que consumen estas pantallas es `backend-cobranza-ia/` (Spring
Boot), ya en este mismo repo. Necesita estar corriendo y apuntando a la
misma base de datos de ventas — ver su `README.md` (raíz del repo) para
variables de entorno (`ANTHROPIC_API_KEY`, `WHATSAPP_*`, `VENTAS_API_BASE_URL`,
`DB_URL`, etc.).

## 4. Validado de verdad en esta sesión

Instalé Node 10.24.1 (vía `nvm`, Angular CLI 6/7 no corre con Node
18+/22), hice `npm install` de tu proyecto real y corrí
`ng build --prod` con estos 4 componentes + el servicio ya copiados y
los 7 parches ya aplicados. **Resultado: cero errores atribuibles a
`cobranza-ia`** - los ~160 errores que sí salen son 100% pre-existentes
en tu proyecto (namespace `DataTables` no configurado en varios
módulos, `file-saver` sin instalar, dos servicios de `inventarios` que
no vinieron en el zip que subiste, y `environment.ts` sin la propiedad
`redirectUri` que sí usa `auth-service.ts`) - no tienen nada que ver con
este cambio, y no los toqué.

### Bug real que sí es mío (ya corregido en los archivos de este paquete)

Usé la API nueva de SweetAlert2 (`Swal.fire({ icon: 'warning', ... })`)
pero tu proyecto tiene una versión más vieja cuyos tipos usan `type` en
vez de `icon`. Ya está corregido en
`bloque-detalle-cobranza-ia.component.ts` y
`conversaciones-cobranza-ia.component.ts`.

### Hallazgo aparte, no relacionado a este cambio (para que no te sorprenda)

Tu `package.json` fija `@types/jquery` en `^3.3.29`, pero un `npm
install` fresco HOY resuelve esa versión a `3.5.34` (publicada varios
años después), cuyo `.d.ts` usa sintaxis de TypeScript que el
compilador que trae este proyecto (TypeScript 3.2.4) no puede ni
parsear - el build truena antes de llegar a tu propio código, con
errores en `node_modules/@types/jquery/misc.d.ts`. Esto no lo causó
este cambio y le pasaría a cualquiera que instale el proyecto desde
cero hoy. El fix que validé:

```bash
npm install --no-save @types/jquery@3.3.31
```

(o fijar esa versión exacta en `package.json` para que quede
permanente). `skipLibCheck: true` en `tsconfig.json` NO alcanza porque
es un error de sintaxis (parseo), no de chequeo de tipos.

## 5. Pendiente para que sea 100% funcional

- El backend `VentasApiClient.registrarAbono(...)` todavía es un stub
  (ver README raíz) — hace falta exponer un endpoint en
  `apibackendventas` para aplicar pagos/notas de crédito reales.
- Cargar `cob_telefono_cliente` con los teléfonos de WhatsApp reales de
  tus clientes (ver `db/migrations/`).
- Configurar el número de WhatsApp Business y las plantillas (HSM)
  reales en Meta, y crear su fila correspondiente en `cob_plantilla_mensaje`.
