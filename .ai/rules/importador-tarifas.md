---
paths:
  - 'app/Http/Controllers/ShippingRateImportController.php'
  - 'app/Http/Requests/ShippingRates/**'
  - 'app/Services/ShippingRatesCsvParser.php'
  - 'app/Actions/ImportShippingRatesAction.php'
---

# Importador de tarifas (Spec 06 fase 2, reglas 129–142)

## El importador responde 422, NO 302 — es deliberado, no lo "corrijas"
La Spec 06 fase 2 exige `422` ante cualquier error de validación del importador: tanto los de contenido del CSV (parser) como los del archivo (`ImportShippingRatesRequest::failedValidation`, que renderiza `admin.tarifas-envio.import` con `$errors` en vez de redirigir). Es una **excepción deliberada y acotada** a la convención del resto del panel (redirect 302 + `withErrors`), ratificada por el dueño. NO lo unifiques con el patrón 302 por parecer inconsistente, y NO cambies el comportamiento de otros endpoints para "emparejarlos": el resto de la app sigue con 302.

## Flujo PRG: el POST no renderiza el preview
`POST tarifas-envio.import.upload` valida todo y **redirige** a `GET tarifas-envio.import.preview?token=`; el POST nunca devuelve la vista del preview. El manifiesto vive en caché (`user_id`, `path`, `hash`, conteos, `expires_at`, TTL 30 min) y el CSV crudo en `storage/app/private/tmp` — nunca en sesión (regla 140). El `GET` valida token propio + hash antes de mostrar nada; `confirm` re-parsea y revalida desde el temporal antes de aplicar. Si rompés el PRG, refrescar el preview vuelve a subir el archivo y deja temporales duplicados.

## Limpieza del temporal sin scheduler (ADR-011 punto 5)
Ningún entorno del proyecto ejecuta el scheduler de Laravel, así que un comando agendado sería código muerto. La limpieza se dispara: al confirmar, ante hash que no coincide, en `POST tarifas-envio.import.cancel`, y por barrido oportunista de vencidos al entrar a `GET tarifas-envio.import`. No agregues un scheduled command.

## str_getcsv necesita el $escape explícito
PHP 8.4 deprecó omitirlo. Usar siempre `str_getcsv($line, ',', '"', '')` — el `''` desactiva el escapado propietario y es el comportamiento futuro. Sin esto la suite emite deprecaciones y se rompe el "sin warnings" del DoD.

## Semántica de la aplicación del snapshot
Fila-por-fila en una única `DB::transaction()`, nunca `upsert()` (ADR-011). Sin tarifa activa → `create`; distinto costo → `update` de la misma fila; igual costo → no-op **sin tocar `updated_at`**; activa ausente del snapshot → `activo = false`. Cero `delete` físicos, y una histórica inactiva jamás se reactiva: se crea una fila nueva.
