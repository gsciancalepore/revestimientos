# ADR-013 — Capa de observabilidad y contrato con un investigador de incidentes externo

- **Estado**: **propuesta (2026-09-24)**, pendiente de aprobación del dueño.
- **Enmienda** a [ADR-004](ADR-004-observabilidad-estructura-reservada.md), sin reemplazarla. ADR-004
  sigue vigente en lo que decidió: la auditoría en `audit_logs` y no implementar métricas ni
  dashboards en el MVP. Esta ADR cambia estos puntos:
  - **Punto 1 (logs)**: pasan a ser un contrato JSON versionado.
  - **Punto 2 (eventos de dominio como base del trazado)**: se descarta. La consecuencia de ADR-004
    "las Actions disparan eventos de dominio desde el día uno" **no se cumplió**: `app/Events/` no
    existe.
  - **Punto 4 (métricas)**: se concreta.
  - **Nuevo**: un consumidor externo que ADR-004 no preveía.
- **Origen**: decisiones del dueño del 2026-09-24:
  - Construir un investigador de incidentes con IA como **proyecto independiente**
    (`incident-investigator`), parte de su portfolio.
  - Revestimientos es el **sistema observado** y expone su observabilidad por interfaces bien
    definidas.
  - Se empieza solo con logs.
- **Etapa 1**: la especifica [`observabilidad-01-contrato-logs.md`](../specs/observabilidad-01-contrato-logs.md).

## Contexto

ADR-004 reservó el diseño de la observabilidad sin implementarlo, y el código se quedó más corto que
lo que prometía. Al 2026-09-24 hay esto:

- **Logs**: texto plano por defecto de Laravel. Hay tres `Log::error`, no existe un identificador
  de request y los tests escriben en el mismo archivo que el uso real (525 de 4141 líneas). Hay
  **datos personales filtrados** por dos vías: mensajes de `QueryException` con el SQL y sus
  valores, y trazas con los argumentos de las llamadas.
- **Eventos de dominio**: no existen. La auditoría se resolvió con llamadas explícitas a
  `AuditRecorder`.
- **Auditoría**: `audit_logs` funciona y cubre los hechos de negocio críticos.
- **Métricas**: solo `/up`.

El dueño quiere un agente de IA que investigue incidentes. Decidió que ese agente **no viva en este
repositorio** por tres razones:

- el investigador es un sistema con su propio ciclo de vida, lenguaje y dependencias;
- tiene que poder observar a Revestimientos sin conocer su código;
- es una pieza de portfolio que se tiene que poder mostrar por sí sola.

### Restricciones del dueño (2026-09-24)

- Solo planes gratuitos. Local primero.
- Primera versión simple: **solo logs**, sin MCP, Agent SDK ni infraestructura adicional.
- **Sin datos personales en los logs**, código postal incluido.
- Un evento entra al contrato solo si tapa un agujero que hoy impide investigar un incidente.

## Decisión

### 1. Revestimientos publica contratos, no integra al agente

La frontera entre los dos sistemas es un **contrato de observabilidad versionado**. En la v1 es solo
de logs:

- **Forma**: un JSON Schema (`docs/observabilidad/log-schema.v1.json`).
- **Significado**: un catálogo de eventos.
- **Entrega**: archivos `.jsonl` en un directorio.

Revestimientos lo garantiza con tests que validan contra el schema, y un cambio incompatible exige
una versión nueva. El investigador depende del contrato, nunca del código de Revestimientos. Es el
mismo criterio de puerto y adaptador de ADR-006, llevado al borde entre dos sistemas.

**La frontera de datos también la fija el contrato**: los datos personales se eliminan antes de
escribir la línea. Lo que recibe el investigador, y por lo tanto el modelo de lenguaje que use, ya
viene limpio. No depende de cómo se porte el agente.

### 2. La capa ideal (destino, no compromiso de fecha)

| Señal | Qué responde | Contrato hacia afuera |
|---|---|---|
| **Logs estructurados** | ¿Qué pasó, en qué orden, en qué request? | Schema de logs v1 (etapa 1) |
| **Auditoría** | ¿Quién cambió qué dato de negocio? | Se espeja en los logs (etapa 1). Acceso directo a `audit_logs` solo si hace falta y sin `ip_address` ni `user_agent` (ver abajo) |
| **Errores** | ¿Qué excepción, cuántas veces, desde cuándo? | Hoy, el evento `app.exception` del schema. Mañana, un servicio que agrupe errores |
| **Métricas** | ¿Cuánto, qué tan rápido, qué tan seguido falla? | Por definir (etapa 4) |
| **Trazas** | ¿Dónde se fue el tiempo dentro de un request? | Por definir (etapa 4) |
| **Disponibilidad** | ¿Está arriba? | `/up` |

Principios:

1. **Un identificador de correlación atraviesa todo**: `request_id` en cada línea y en la cabecera
   `X-Request-Id`, y además los identificadores de negocio (`order_id`, `payment_id`).
2. **El contrato es propio y versionado. OpenTelemetry es el rumbo, no la promesa de la v1.** El
   schema v1 usa nombres propios. Su forma (`event`, `attributes`, `level`, `timestamp`) se puede
   traducir de manera directa al modelo de logs de OpenTelemetry. Si en la etapa 4 se adopta OTel,
   el camino es una versión nueva del contrato o un exportador que traduzca, no un cambio
   silencioso de la v1.
3. **Los consumidores leen, no escriben.** Ningún consumidor externo modifica a Revestimientos ni a
   sus datos.

### 3. Etapas del lado de Revestimientos

Cada etapa es una spec propia, que pasa por `revisor-spec` y la aprobación del dueño. Solo la etapa
1 está comprometida.

1. **Contrato de logs v1, en local**: `observabilidad-01-contrato-logs.md`.
2. **Staging**: el mismo contrato por `stderr` en Render, y cómo lo obtiene un consumidor (API de
   Render o reenvío a un backend gratuito, según lo que permita el plan en ese momento).
   Verificación del `request_id` bajo Octane.
3. **Errores y disponibilidad**: agrupación de errores y ping externo, con planes gratuitos.
4. **Métricas y trazas**, evaluando OpenTelemetry.
5. **Señales para disparo automático**: qué alertas expone Revestimientos para que un consumidor
   reaccione.

**Nota sobre `audit_logs`**: la tabla guarda `ip_address` y `user_agent` de clientes anónimos. Si
una etapa futura expone la auditoría directamente (y no solo su espejo en los logs), esas columnas
quedan fuera de lo que se expone.

### 4. El investigador, del otro lado del contrato

El investigador vive en su propio repositorio (`~/incident-investigator`) y sus decisiones de
arquitectura se documentan allá. Para esta ADR solo importa lo que consume:

- El directorio de logs y la versión del schema.
- Nada de base de datos.
- Nada de red hacia Revestimientos.

### Candidatos de proveedor (se deciden en su etapa, no ahora)

Los límites de los planes gratuitos cambian seguido, así que **se verifican el día que se elige**:

- **Grafana Cloud**: logs, métricas y trazas, con recepción nativa de OpenTelemetry y API de
  consulta.
- **Better Stack**: logs y uptime.
- **Sentry**: agrupación de errores.
- **Laravel Nightwatch**: poca configuración, pero ata al proveedor y no habla OpenTelemetry.

## Alternativas descartadas

- **Agente de Claude Code dentro de este repositorio** (borrador v1 de esta ADR, 2026-09-24):
  descartada por el dueño. Además, `revisor-spec` encontró que con `Bash` el agente podía leer la
  base con `psql`, y que la frontera de datos dependía solo del prompt (hallazgo B2). Un sistema
  externo que solo recibe un directorio de logs no tiene ese camino.
- **Adoptar ya un proveedor SaaS completo**: descartada para la etapa 1. Mandar a la nube logs sin
  estructura, sin correlación y con datos personales no los vuelve investigables.
- **Laravel Pulse o Telescope como fuente**: descartada. Guardan en la base de la app en formatos
  pensados para verse en su pantalla, no para un consumidor externo, y Telescope registra los
  payloads de los requests.
- **Crear eventos de dominio solo para loguear**: descartada. Sería infraestructura sin otro
  consumidor (principio 5). El espejo de la auditoría cubre lo mismo con un único cambio.

## Consecuencias

- **Positivas**:
  - Los logs sirven a cualquiera que investigue, con o sin agente.
  - El investigador se puede reescribir, reemplazar o multiplicar sin tocar Revestimientos.
  - La filtración de datos personales que hoy existe se corta en el origen.
- **Negativas**:
  - Todo flujo nuevo que mueva dinero o stock tiene que pasar por el criterio del catálogo y
    declararse en el schema. Es disciplina en cada spec.
  - Mientras no llegue la etapa 2, solo se investigan incidentes reproducidos en local.
- **Riesgo**: texto libre con datos personales dentro del mensaje de una excepción de una librería.
  Está acotado por OBS-07 y declarado como riesgo residual en la spec.

## Condición de revisión

Se revisa al terminar cada etapa, si cambia el hosting de producción (hoy sin definir) o si
desaparece un plan gratuito del que dependa una etapa, como pasó con Koyeb (ADR-008).
