# ADR-008 — Staging remoto con Koyeb + Neon

* **Estado:** aceptado para Staging
* **Fecha:** 2026-08-31
* **Decide:** reemplazar temporalmente el enfoque de VPS definido en ADR-002 para el entorno de Staging.
* **Alcance:** exclusivamente entorno Staging.

## Contexto

El proyecto necesita un entorno remoto accesible por Internet para que el socio pueda utilizar y validar el sistema desde su propia computadora mientras continúa el desarrollo.

El entorno debe:

* ser gratuito durante esta etapa;
* permanecer disponible aunque la PC del desarrollador esté apagada;
* ejecutar Laravel mediante Docker;
* utilizar PostgreSQL;
* integrarse con GitHub;
* permitir deployments repetibles;
* mantener los datos de desarrollo separados de Staging;
* permitir evolucionar posteriormente hacia Production.

El objetivo actual no justifica contratar ni administrar un VPS.

## Decisión

Se utilizará:

* **GitHub** como repositorio y fuente de verdad.
* **Koyeb Free Instance** como runtime de la aplicación Laravel.
* **Neon Free** como PostgreSQL de Staging.
* **Docker** como mecanismo de empaquetado de la aplicación.
* **GitHub Actions** como mecanismo de CI.
* **Koyeb Git deployment** como mecanismo inicial de CD.

## Arquitectura

```text
GitHub
   │
   ├── CI
   │
   └── Staging branch
          │
          ▼
       Koyeb
          │
          ▼
       Laravel
          │
          ▼
        Neon
      PostgreSQL
```

## Justificación

Koyeb ofrece actualmente un Free Instance de 512 MB RAM, 0,1 vCPU y 2 GB SSD, suficiente para disponer de un entorno de prueba inicial y sin costo. El servicio escala a cero después de una hora sin tráfico.

Koyeb soporta despliegue desde GitHub y Docker, permitiendo mantener el enfoque de containerización del proyecto.

Neon ofrece PostgreSQL Free con 0,5 GB de almacenamiento por proyecto y 50 CU-hours mensuales, con scale-to-zero. Esto permite separar la base de datos del runtime sin introducir inicialmente un servidor PostgreSQL propio.

## Consecuencias positivas

* El socio puede acceder aunque la PC del desarrollador esté apagada.
* No es necesario administrar un servidor.
* El entorno puede desplegarse automáticamente.
* La base de datos queda separada del runtime.
* Se mantiene Docker.
* El flujo se acerca a un proceso real de CI/CD.
* El costo inicial es cero.
* La arquitectura puede evolucionar posteriormente hacia Production.

## Consecuencias negativas

* El Free Instance de Koyeb tiene recursos muy limitados.
* El servicio puede entrar en scale-to-zero.
* El entorno no debe considerarse Production.
* Los límites gratuitos de Koyeb y Neon deben monitorearse.
* Los workers persistentes no forman parte de esta primera arquitectura de Free Instance.

## Relación con ADR-002

ADR-002 continúa siendo válido como decisión histórica para una futura estrategia de Production basada en VPS.

ADR-008 establece una decisión específica para **Staging**.

No se considera que una decisión invalide históricamente a la otra:

```text
ADR-002
   ↓
Production futura / VPS

ADR-008
   ↓
Staging actual / Koyeb + Neon
```

## Condición de revisión

Esta ADR deberá revisarse cuando:

* el sistema pase a producción;
* se requieran workers persistentes;
* el consumo supere los límites gratuitos;
* se necesiten backups de producción;
* aumente el tráfico;
* se requiera mayor disponibilidad;
* aparezcan requisitos de observabilidad o seguridad que excedan el entorno actual.
