# Cómo correr el proyecto en local

Esta guía está **validada en esta sesión**, no es teórica: cada paso se
ejecutó de verdad (MySQL/MariaDB local, `backend-cobranza-ia` arrancado
y probado con `curl`, `apibackendventas` arrancado también). Los
problemas reales que salieron en el camino (y su fix) están documentados
para que no los vuelvas a pisar.

## Opción A (recomendada): Docker Compose

```bash
docker compose up --build
```

Esto levanta:
- **`mysql`**: MySQL 8 con datos SINTÉTICOS de prueba (3 clientes, 1 ruta,
  1 agente - ver `db/local-dev/`). No es tu base de datos real.
- **`backend-cobranza-ia`**: en `http://localhost:8081/cobranza-ia`, con
  el perfil `local-mock` activo (no necesita `apibackendventas`
  corriendo: usa `MockVentasApiClientImpl`, datos de cartera ficticios).
- **`seed-cobranza-ia`**: corre una vez, después de que el backend ya
  creó sus tablas, para cargar un teléfono de WhatsApp de prueba.

Prueba rápida:
```bash
curl "http://localhost:8081/cobranza-ia/bloques/cartera-vencida?idCompania=19&idRuta=12"
```

Para usar el agente de IA de verdad (no solo bloques/cartera), exporta
tu API key antes de levantar:
```bash
export ANTHROPIC_API_KEY=sk-ant-...
docker compose up --build
```

> Nota: en esta sesión no pude ejecutar `docker compose` literal (el
> sandbox no tiene el daemon de Docker disponible), pero sí validé
> **cada pieza por separado y en el mismo orden** que ejecuta el compose
> (MySQL con los mismos scripts SQL, y el jar de `backend-cobranza-ia`
> con las mismas variables de entorno) - ver Opción B.

## Opción B: sin Docker, directo con Maven/MySQL local

1. Instala y arranca MySQL o MariaDB local, crea la base y un usuario:
   ```sql
   CREATE DATABASE biochem_local CHARACTER SET utf8mb4;
   CREATE USER 'cobranza_ia_app'@'%' IDENTIFIED BY 'localdev123';
   GRANT ALL PRIVILEGES ON biochem_local.* TO 'cobranza_ia_app'@'%';
   ```
2. Carga el esquema mínimo de ventas + datos de ejemplo (en ese orden):
   ```bash
   mysql -ucobranza_ia_app -plocaldev123 biochem_local < db/local-dev/01_schema_ventas_minimo.sql
   mysql -ucobranza_ia_app -plocaldev123 biochem_local < db/local-dev/02_seed_datos_ejemplo.sql
   ```
3. Arranca `backend-cobranza-ia` (Flyway crea las tablas `cob_*` solo):
   ```bash
   cd backend-cobranza-ia
   mvn -q -DskipTests package
   java -jar target/cobranza-ia-*.jar --spring.profiles.active=local,local-mock
   ```
4. **Ahora sí**, carga el seed que depende de las tablas `cob_*`:
   ```bash
   mysql -ucobranza_ia_app -plocaldev123 biochem_local < db/local-dev/03_seed_cobranza_ia_dev.sql
   ```

El perfil `local` (`application-local.yml`) ya trae la URL/usuario/password
de arriba por default. El perfil `local-mock` activa `MockVentasApiClientImpl`.

### Bugs reales que salieron al probar esto (ya corregidos en el repo)

- **Flyway + `baseline-on-migrate`**: con la baseline default (versión 1)
  en un esquema vacío, Flyway **saltaba `V1`** (la asumía ya aplicada) y
  tronaba en `V2` porque las tablas de `V1` nunca se crearon. Fix:
  `spring.flyway.baseline-version: 0` en `application.yml`.
- **`${{2}}` en el seed de plantillas**: Flyway interpreta `${...}` como
  su propio placeholder; el texto de ejemplo de la plantilla de WhatsApp
  tenía literalmente `${{2}}` (para representar "$" + variable). Fix:
  `spring.flyway.placeholder-replacement: false`.
- **Acceso a MySQL por TCP con `root` sin password**: funciona por socket
  pero no por `127.0.0.1:3306` (JDBC) sin un usuario con auth por
  password. Por eso el usuario dedicado `cobranza_ia_app`.

### `apibackendventas` en local (opcional, para probar sin modo mock)

Ver `apibackendventas-local-run/README.md`. Resumen: su
`application.properties` usa un datasource JNDI (piensa que corre dentro
de WildFly/JBoss); para correrlo suelto hace falta excluir
`JndiDataSourceAutoConfiguration` explícitamente (poner la propiedad en
vacío NO alcanza) y darle una URL JDBC directa - ya está armado el
`application-local.properties` que lo logra, **validado**: el WAR deja
de tronar por JNDI y arranca. Lo que NO se puede reproducir aquí es su
base de datos real completa (cientos de tablas) - sin un dump de una BD
de desarrollo/staging, sus endpoints responden error contra un esquema
mínimo.

**Límite de este sandbox**: al intentar una prueba en vivo de
`POST /vntAbono/savePagos` (con un esquema sintético mínimo agregado a
mano) el proceso de `apibackendventas` se volvió inestable en este
entorno - es una aplicación monolítica muy grande y el sandbox tiene
recursos limitados - y no llegué a una respuesta 200 limpia antes de
que el proceso terminara. El código de integración (`VentasApiClientImpl`)
se construyó leyendo el DTO/entidad/endpoint reales de
`apibackendventas`, así que los nombres de campo y rutas están
confirmados contra el código fuente; lo que falta es una prueba en vivo
en un ambiente con más recursos (tu máquina, o un dev/staging real).

## Frontend (`biochem-systems-front-end`, Angular 6)

Ver `biochem-frontend-integration/INSTRUCCIONES.md` para aplicar los
parches. Para correrlo:

```bash
nvm install 10.24.1 && nvm use 10.24.1   # Angular 6/CLI 6-7 no corren con Node 18+/22
npm install
npm install --no-save @types/jquery@3.3.31   # ver "Hallazgo" abajo, si no aplica el fix permanente
npm start   # ng serve, http://localhost:4200
```

Ajusta `src/environments/environment.ts` -> `cobranzaIA` a
`http://localhost:8081/cobranza-ia` (u otra URL si usaste Docker con
otro puerto).

**Validado en esta sesión**: `npm install` + `ng build --prod` con los
4 componentes de cobranza-ia y los 7 parches ya aplicados -> **cero
errores de compilación atribuibles a este cambio**. Los ~160 errores
que sí salen son 100% pre-existentes en el proyecto (namespace
`DataTables`, `file-saver`, dos servicios de `inventarios` faltantes en
el zip, `redirectUri` sin declarar en `environment.ts`) - no se tocaron.

**Hallazgo aparte, no relacionado a cobranza-ia**: `package.json` fija
`@types/jquery` en `^3.3.29`, pero un `npm install` fresco hoy resuelve
`3.5.34` (mucho más nueva), cuyo `.d.ts` usa sintaxis de TypeScript que
el compilador de este proyecto (3.2.4) ni siquiera puede parsear -
tronaría el build de cualquiera que instale el proyecto desde cero.
`npm install --no-save @types/jquery@3.3.31` lo resuelve (o fija esa
versión en `package.json` para que quede permanente).
