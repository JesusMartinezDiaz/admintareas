# Correr `apibackendventas` en local (fuera de WildFly/JBoss)

`apibackendventas` usa `spring.datasource.jndi-name=java:/Blade`, es
decir espera que un servidor de aplicaciones (WildFly/JBoss) le inyecte
el datasource "Blade" ya configurado. Para desarrollo local (sin ese
servidor) hace falta un datasource JDBC directo.

**Validado en esta sesión**: con el archivo `application-local.properties`
de esta carpeta, el WAR arranca completo (Tomcat embebido + todos los
controladores) contra una base de datos MySQL local vacía. Los
endpoints individuales seguirán devolviendo error hasta que la base de
datos tenga las tablas y datos reales (ver "Limitación" abajo) - eso ya
no es un problema de arranque, es que a un esquema vacío le faltan
datos.

## Pasos

1. Copia `application-local.properties` a
   `src/main/resources/application-local.properties` de tu proyecto
   `apibackendventas`.
2. Ajusta la URL/usuario/password de MySQL en ese archivo a tu base de
   datos local (o a la de `docker-compose.yml` de este repo, servicio `mysql`,
   base `biochem_local`, usuario `cobranza_ia_app` / password `localdev123`).
3. Compila y arranca:
   ```bash
   mvn -q -DskipTests package
   java -jar target/apibackendventas-0.0.1-SNAPSHOT.war --spring.profiles.active=local
   ```
   (el puerto por default es 8090 según `application.properties`; ajusta con
   `--server.port=` si lo necesitas distinto).

## El "truco" que no es obvio

Poner `spring.datasource.jndi-name=` (vacío) en el perfil local **no
alcanza** - Spring Boot considera la propiedad "presente" aunque esté
vacía y de todos modos intenta la búsqueda JNDI. Hay que excluir esa
autoconfiguración explícitamente:

```properties
spring.autoconfigure.exclude=org.springframework.boot.autoconfigure.jdbc.JndiDataSourceAutoConfiguration
```

Ya está incluido en el `application-local.properties` de esta carpeta.

## Limitación importante (no es un bug, es honestidad sobre el alcance)

Este repo **no incluye la DDL real completa** de `apibackendventas`
(cientos de tablas: `vnt_cargo_encabezado`, `vnt_abono_encabezado`,
`vnt_documentos_asociaciones`, etc. - no confirmadas columna por
columna en esta sesión). Por eso, aunque el WAR arranca perfecto, la
mayoría de sus endpoints van a responder error 500 contra una base de
datos vacía o con solo las tablas mínimas de `db/local-dev/`.

Para tener `apibackendventas` 100% funcional en local, lo que hace
falta (y que solo tú puedes proveer, por seguridad, no se debe pedir
ni compartir aquí) es un **dump de una base de datos de desarrollo o
staging** (nunca de producción) restaurado en el MySQL local.

## ⚠️ Seguridad: credenciales expuestas en el repo

`apibackendventas/src/main/resources/application.properties` tiene
credenciales reales de Twilio, AWS S3 y OpenPay en texto plano,
commiteadas al repositorio. No las repetimos aquí. Recomendación:
rotarlas cuanto antes y mover las nuevas a variables de entorno (fuera
del control de versiones), igual que se hizo en `backend-cobranza-ia`.
