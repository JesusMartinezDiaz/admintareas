# Build context = raíz del repo (ver docker-compose.yml), porque Flyway
# necesita ../db/migrations relativo al working dir de la app dentro del
# contenedor y así lo empaquetamos junto con el jar.
FROM maven:3.9-eclipse-temurin-17 AS build
WORKDIR /build
COPY backend-cobranza-ia/pom.xml .
RUN mvn -q -B dependency:go-offline
COPY backend-cobranza-ia/src ./src
RUN mvn -q -B -DskipTests package

FROM eclipse-temurin:17-jre
WORKDIR /app
COPY --from=build /build/target/*.jar app.jar
COPY db/migrations /db/migrations
EXPOSE 8081
ENTRYPOINT ["java", "-jar", "app.jar"]
