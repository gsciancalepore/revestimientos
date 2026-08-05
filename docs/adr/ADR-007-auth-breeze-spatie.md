# ADR-007 — Autenticación: Breeze (Blade) + Spatie Permission

- **Estado**: aceptado (2026-08-05)
- **Contexto**: para la Spec 01 (autenticación y roles) se evaluó cómo
  implementar el login del panel y la autorización por roles. La evaluación
  recomendaba roles nativos (enum + Policies) por simplicidad (principios 5 y 8);
  el dueño decidió instalar **spatie/laravel-permission** (flexibilidad futura)
  y **Laravel Breeze** para el flujo de autenticación.

## Decisión

1. **Laravel Breeze (stack Blade)** para el flujo de auth: login, logout,
   "olvidé mi contraseña"/reset, confirmación de contraseña y perfil propio.
   Adaptado al panel interno: rutas bajo `/admin`, **sin registro público**, sin
   verificación de email y sin borrado de cuenta.
2. **spatie/laravel-permission** para roles y permisos. Roles sembrados:
   `admin`, `vendedor`, `deposito`; un rol por usuario.
3. **Desactivación en vez de borrado**: columna `users.is_active`; el login se
   deniega a usuarios inactivos.
4. La autorización por recurso sigue viviendo en **Policies** (UserPolicy);
   Spatie aporta los checks de rol (`role:admin` en rutas, `hasRole` en
   Policies). Los permisos por acción se crean solo cuando el dominio los pida
   (YAGNI).
5. Auditoría de usuarios/roles: tabla `audit_logs` + servicio `AuditRecorder`
   (ver ADR-004).

## Implementación (2026-08-05)

- **Breeze pineado a `2.4.2` exacto** (sin caret): los stubs instalados se
  versionan y no deben cambiar con futuras versiones de Breeze.
- **Tailwind 4 se conserva**: `breeze:install` baja Tailwind a `^3.1.0`,
  copia `tailwind.config.js`/`postcss.config.js` y pisa `vite.config.js`,
  `app.css` y `package.json`. Se restauró la configuración v4 (plugin
  `@tailwindcss/vite`) y se registró `@tailwindcss/forms` al estilo v4:
  `@plugin "@tailwindcss/forms";` en `resources/css/app.css`. Se eliminaron
  `tailwind.config.js` y `postcss.config.js`.
- **Alpine**: el skeleton no traía Alpine; se conserva el `app.js` de Breeze
  (única inicialización `Alpine.start()`). El dropdown del navigation no se
  duplica.
- **Poda del flujo Breeze**: sin `RegisteredUserController`, sin verificación
  de email, sin `ProfileController::destroy` (ni su partial); welcome sin links
  a registro; tests de registro/verificación/borrado eliminados.
- El throttle de login (5/min por `email|ip`) ya viene en el `LoginRequest` de
  Breeze; solo se agregó el chequeo de `users.is_active` (mismo error genérico).

## Consecuencias

- Costo operativo de Spatie: 4 tablas (roles, permissions, pivotes), config y
  cache de permisos; debe publicarse y mantener el seeder de roles.
- Flexibilidad futura: permisos granulares por acción sin migración de datos si
  el negocio lo pide.
- **Ruta de salida**: si Spatie deja de justificarse, los checks de rol se
  reemplazan por enum + Policies sin tocar vistas ni controladores (la
  autorización ya pasa por Policies).
- El cliente web sigue siendo anónimo (Spec 00, regla 27): la futura compra web
  no usa estas cuentas.

## Alternativas

- **Roles nativos (enum + Policies)**: recomendada por la asesoría técnica
  (menor complejidad); descartada por decisión del dueño (flexibilidad futura
  para permisos dinámicos).
- **Fortify**: cubre auth headless con APIs; descartado — el panel usa Blade y
  Breeze entrega el flujo completo con código first-party.
- **Login custom (~20 líneas)**: viable, pero sin los flujos de reseteo/perfil ya
  resueltos por Breeze.
