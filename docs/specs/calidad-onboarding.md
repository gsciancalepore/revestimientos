# Spec — Calidad de onboarding y runbook de agentes

- **Estado**: aprobada (2026-08-05); implementada (2026-08-05)
- **Fuentes**: análisis de preparación del repo para agentes nuevos sin contexto
  (2026-08-05), convención de documentación en español, DoD del roadmap

## Objetivo

Garantizar que un agente nuevo, **sin contexto previo**, pueda levantar el
entorno y continuar el proyecto leyendo únicamente la documentación del repo:
README, Makefile, specs, ADRs, `.ai/rules` y el estado del roadmap. El arranque
no debe requerir conocimientos no documentados ni pasos a prueba y error.

## Contexto

- El proyecto exige que toda decisión se pueda reconstruir leyendo la
  documentación (principio 10) y que el equipo lo conformen agentes sin memoria
  entre sesiones: el único canal de continuidad es el repo.
- El análisis de preparación detectó estos huecos (2026-08-05):
  1. `make setup` no deja el panel operable: no migra ni siembra, y usa
     `composer update` (drift de versiones contra `composer.lock`).
  2. El README indica `php artisan db:seed` sin el patrón `docker compose exec`
     (falla en el host: no hay PHP instalado) y no menciona el script
     `composer run setup` del skeleton.
  3. No existen comandos de operación diaria (`migrate`, `seed`) en el Makefile.
  4. El árbol de `arquitectura.md` lista carpetas que todavía no existen
     (`app/DTOs`, `Events`, `Listeners`, `Jobs`), sin distinguirlas de las reales.
  5. No hay guía explícita de "cuál es el próximo paso" ni del proceso para
     escribir una spec nueva.
  6. `ADR-002` está en inglés (el resto de la documentación en español).
  7. Los traps operativos (Vite hot file, manifest en tests, cache de Spatie)
     están en `.ai/rules` pero no tienen referencia desde el README.

## Reglas de documentación (no son reglas de negocio; la numeración de reglas
de negocio continúa en las specs de dominio)

1. El **README es el punto de entrada**: un lector nuevo debe poder llegar a un
   panel operativo (login + datos sembrados) siguiendo solo el runbook.
2. **`make setup` es determinista e idempotente**: misma configuración en cada
   clon nuevo; instala desde `composer.lock` (`composer install`, no `update`),
   migra y siembra (los seeders son idempotentes: `findOrCreate`/`updateOrCreate`).
3. **Todo comando PHP/Node se ejecuta dentro de contenedores** (`docker compose
   exec app ...`, `docker compose exec assets ...`); el host no tiene PHP ni Node.
4. **Toda la documentación está en español** (incluidos los ADRs).
5. El árbol de `arquitectura.md` **distingue carpetas existentes de previstas**:
   las previstas (se crean con su spec) están marcadas como tales.
6. **Cada trap operativo tiene su regla en `.ai/rules`** y una referencia breve
   en el README (sección "Problemas comunes"), para que el agente llegue a la
   regla completa desde el punto de entrada.

## Criterios de aceptación

- [ ] Un clon nuevo + `make setup` deja: contenedores arriba, base migrada,
      roles y admin inicial sembrados (credenciales de entorno), y el panel
      accesible en `/admin/login` sin pasos adicionales.
- [ ] El runbook del README lista los pasos en orden y las URLs (web, mailpit,
      vite) y las credenciales del admin de desarrollo.
- [ ] La tabla de comandos del README incluye `migrate`, `seed` y `artisan`, y
      aclara el patrón `docker compose exec`.
- [ ] `make setup` no modifica versiones más allá del `composer.lock`
      (`composer install`) y no regenera archivos commiteados
      (no `pest --init`).
- [ ] `arquitectura.md` marca `app/DTOs`, `Events`, `Listeners`, `Jobs` como
      carpetas previstas.
- [ ] `ADR-002` está en español.
- [ ] El roadmap indica el próximo paso (Spec 02 — Panel + Categorías) y el
      formato para escribir specs nuevas.
- [ ] Pint, PHPStan (nivel 8) y Pest quedan en verde tras los cambios.
- [ ] README: sección "Problemas comunes" con los 3 traps conocidos y su regla
      en `.ai/rules`.

## Tareas técnicas

- [x] Spec de calidad de onboarding (este documento).
- [x] Makefile: `setup` con `composer install` + `migrate --force` +
      `db:seed --force`, sin `pest --init`; targets `migrate`, `seed` y
      `artisan`.
- [x] README: runbook de primer arranque, tabla de comandos ampliada, sección
      "Problemas comunes".
- [x] `arquitectura.md`: nota de carpetas previstas en el árbol.
- [x] `ADR-002`: traducción a español.
- [x] `roadmap.md`: fila de calidad de onboarding + sección "Cómo continuar".
- [x] Verificación de calidad: pint, PHPStan, Pest y drill de arranque.
