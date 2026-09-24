---
paths:
  - 'app/Logging/**'
  - 'app/Http/Middleware/AssignRequestId.php'
  - 'app/Services/AuditRecorder.php'
  - 'config/logging.php'
  - 'docs/observabilidad/**'
---

# Observabilidad — contrato de logs v1 (spec observabilidad-01, ADR-013)

## Lo que se escribe en el log es un contrato público
Un sistema externo (`incident-investigator`) lee `storage/logs/app-*.jsonl` sin conocer este código. La forma de cada línea y el catálogo de eventos viven en `docs/observabilidad/log-schema.v1.json`, que es la fuente de verdad; los tests validan cada línea contra él (`lineasDelContrato()` en `tests/Pest.php`). Agregar un evento o un atributo opcional es compatible; quitar, renombrar o cambiar un tipo o un significado exige `schema_version: "2"` y aviso al consumidor.

## Todo `Log::` nuevo en `app/` es un evento del catálogo
Se emite con `EventLog::record('evento', [...])`, nunca con `Log::info('texto')`. Un evento entra al catálogo **solo si tapa un agujero que hoy impide investigar un incidente concreto** (criterio del dueño): nada de eventos "por si acaso". Antes de emitirlo, declararlo en el schema con su descripción —incluido cuándo es comportamiento previsto y no una falla— y cubrirlo con un test que valide contra el schema. Lo que no pasa por `EventLog` termina como `app.log`, que es riesgo residual.

## Solo identificadores, nunca datos personales
En `attributes` van ids, montos en centavos, estados, códigos, nombres de campos y textos **fijos** de la app. Nunca nombre, email, teléfono, dirección, CP, IP ni user agent de un cliente. `RedactPersonalData` es la red de seguridad (clave exacta, sin mayúsculas, en todos los canales vía `tap`), no el mecanismo principal: no protege texto libre. Un mensaje de excepción propio (`App\` o `DomainException` de `app/`) sí llega al log, así que tampoco puede interpolar datos del cliente.

## Si el pedido o el pago se conocen, van con ese nombre
`order_id` (entero) y `payment_id` (string) en todo evento que se refiere a un pedido o a un pago. El consumidor filtra por esas dos claves; un `subject_id` o un `pedido_id` no los encuentra.

## Los valores que vienen de afuera se truncan en `EventLog`
`EventLog::CLAVES_EXTERNAS` (hoy `payment_id`, `tipo`, `mp_request_id`, `external_reference`, `status`) se corta a 64 caracteres: terminan en el prompt de un modelo de lenguaje del lado consumidor. Un valor nuevo que venga del request o de MercadoPago se agrega a esa lista.

## El espejo de la auditoría se escribe después del commit
`AuditRecorder` emite el evento con `DB::afterCommit`: si la transacción se revierte, no hay fila de auditoría y el log no puede contar algo que no pasó. La fila de auditoría conserva sus claves; los renombres (el `request_id` de MercadoPago → `mp_request_id`) son solo del log.

## `app.exception` reemplaza al reporte por defecto de Laravel
`bootstrap/app.php` registra el callback con `->stop()`. Sin el `stop()`, Laravel vuelve a escribir `getMessage()` crudo —el SQL con los valores del cliente en una `QueryException`— en el canal por defecto. `zend.exception_ignore_args = On` (php.ini y `ini-values` de CI) es defensa en profundidad para las trazas.

## Un log que no se puede escribir nunca rompe un request
El canal `app` es un `stack` con `ignore_exceptions: true` sobre `app_file`: Laravel solo lee esa opción en el driver `stack`. Consecuencia aceptada: se pueden perder líneas, y la ausencia de un evento no prueba nada.

## Los tests no escriben en `storage/logs/`
`phpunit.xml` fija `LOG_CHANNEL=null`; los tests del contrato usan `canalDeContrato()`, que construye el canal real con la ruta en un directorio temporal. CI compara los hashes de `storage/logs/` antes y después de la suite. `storage/logs/browser.log` lo escribe Boost desde el navegador del desarrollador (solo local, `require-dev`): no es de la suite ni del contrato.
