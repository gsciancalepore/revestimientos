# Spec 01 — Autenticación y roles

- **Estado**: aprobada (2026-08-05); implementada y **cerrada** (2026-08-05)
  con verificación de calidad completa: Pint, PHPStan nivel 8 y suite Pest de 52
  tests en verde, CI alineado
- **Fuentes**: Spec 00 (reglas 31-32), ADR-004 (auditoría), decisiones del dueño
  sobre stack de auth (ADR-007)

## Objetivo

Dar acceso al panel administrativo a los usuarios internos (admin, vendedor,
depósito), con recuperación de contraseña, perfil propio y gestión de usuarios
solo por el admin, auditando las acciones críticas sobre usuarios y roles.

## Contexto

- El panel es **interno**: no existe registro público ni verificación de email
  (el cliente web es anónimo — Spec 00, regla 27).
- El stack de auth es **Laravel Breeze (Blade) + spatie/laravel-permission**
  (ADR-007).
- Esta spec implementa la **auditoría** reservada en ADR-004 para la primera
  acción crítica del sistema: la gestión de usuarios y roles.

## Reglas de negocio (continúa la numeración de la Spec 00)

33. El panel se accede únicamente con **email + contraseña** de un usuario
    interno, desde `/admin`.
34. Los roles son: **admin** (dueño: todo), **vendedor** (clientes, pedidos,
    ventas WhatsApp, catálogo), **depósito** (despacho y entrega). Cada usuario
    tiene **exactamente un rol**.
35. No existe registro público ni verificación de email: las cuentas las crea un
    admin. La primera cuenta (el **admin inicial**) nace del seeder con
    credenciales de entorno.
36. Solo **admin** gestiona usuarios: crear, editar, desactivar, reactivar,
    asignar rol y resetear contraseña.
37. Un usuario **se desactiva, nunca se borra** (conserva historial de pedidos y
    ventas); un usuario desactivado **no puede iniciar sesión**.
38. El login tolera **máximo 5 intentos fallidos por minuto** (por email + IP);
    superado, se bloquea temporalmente.
39. La contraseña mínima es de **8 caracteres**.
40. Cualquier usuario cambia su propia contraseña desde **Mi perfil**; el admin
    puede resetear la contraseña de cualquier usuario.
41. Si un usuario olvida su contraseña, recibe por email un **link de reseteo de
    un solo uso** (flujo nativo de Laravel/Breeze). El reseteo no reactiva un
    usuario desactivado.
42. Son **acciones auditadas** (ADR-004): crear usuario, editar usuario, cambiar
    rol, desactivar y reactivar. Cada una queda en `audit_logs` con actor,
    acción, sujeto, datos del cambio, IP y fecha.

## Matriz de permisos

| Acción | admin | vendedor | depósito |
|---|---|---|---|
| Iniciar sesión | ✓ | ✓ | ✓ |
| Mi perfil (datos y contraseña propios) | ✓ | ✓ | ✓ |
| Recuperar contraseña | ✓ | ✓ | ✓ |
| Ver listado de usuarios | ✓ | — | — |
| Crear / editar usuarios | ✓ | — | — |
| Asignar rol | ✓ | — | — |
| Desactivar / reactivar | ✓ | — | — |
| Resetear contraseña ajena | ✓ | — | — |

El listado de auditoría **no se expone** en esta spec: los registros solo se
persisten (la UI de auditoría se define cuando exista la sección de
administración).

## Casos borde

- Login con usuario desactivado → **mismo error genérico** que credenciales
  inválidas (no revela el estado de la cuenta).
- Login con email inexistente vs contraseña inválida → mismo error.
- Cambio de rol → se audita con **rol anterior y rol nuevo**.
- Edición sin cambio de rol → solo se audita la edición.
- El admin **no puede desactivarse a sí mismo** (evita dejar el sistema sin
  administradores).
- Email duplicado al crear/editar usuario → validación `unique` con mensaje claro.
- Reset de contraseña de usuario desactivado → el link se envía si el email
  existe (sin revelar el estado), permite cambiar la contraseña, pero el usuario
  sigue desactivado y no puede entrar.
- Throttle: los 5 intentos se cuentan por `email|ip`; se limpian al loguear bien.

## Criterios de aceptación

- [ ] Un invitado que visita `/admin` es redirigido a `/admin/login`.
- [ ] Login con credenciales válidas entra al panel.
- [ ] Login con credenciales inválidas muestra error genérico.
- [ ] Un usuario desactivado no ingresa (mismo error genérico).
- [ ] 5 intentos fallidos en un minuto → bloqueo temporal (error).
- [ ] Logout → vuelve al login.
- [ ] "Olvidé mi contraseña": email con link, reset de un solo uso, nueva
      contraseña ≥ 8 caracteres.
- [ ] Mi perfil: cambiar nombre/email y contraseña propios.
- [ ] Solo admin accede a `/admin/usuarios` (vendedor/depósito → 403).
- [ ] Admin crea un usuario con rol; email duplicado se rechaza.
- [ ] Admin edita un usuario; el cambio de rol se audita (anterior → nuevo).
- [ ] Admin desactiva/reactiva; el desactivado no ingresa; ambas acciones se
      auditan.
- [ ] El admin no puede desactivarse a sí mismo.
- [ ] Cada acción crítica deja una fila en `audit_logs` con actor, acción,
      sujeto, payload, IP y fecha.
- [ ] `php artisan db:seed` crea los 3 roles y el admin inicial (credenciales de
      entorno).

## Decisiones arquitectónicas

- Stack de auth: **Breeze (Blade) + Spatie Permission** (ADR-007).
- Auditoría: tabla `audit_logs` + servicio `AuditRecorder` (ADR-004).
- Rutas del panel: `/admin/*`; auth con middleware `guest`/`auth`; gestión de
  usuarios con `auth` + `role:admin`; autorización por recurso en
  `app/Policies/UserPolicy.php`.
- Roles como enum `UserRole` para tipado (código) mapeado a los nombres de
  Spatie (persistencia).

## Tareas técnicas

- [x] Instalar `laravel/breeze` (blade) y `spatie/laravel-permission`; configurar
      aliases de middleware y redirects de auth en `bootstrap/app.php`
      (Breeze `2.4.2` pineado; Tailwind 4 restaurado — ADR-007).
- [x] Migraciones: `users.is_active` (default true), tablas de permisos,
      `audit_logs`.
- [x] Modelos: `User` (HasRoles), `AuditLog`; enum `UserRole`.
- [x] Seeders: `RolesSeeder` (3 roles) y `AdminSeeder` (admin inicial por
      entorno — `ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD` en `.env(.example/.ci)`,
      leídos vía `config/admin.php`).
- [x] Actions: `CreateUserAction`, `UpdateUserAction`, `SetUserActiveAction` +
      servicio `AuditRecorder`.
- [x] `UserController` + `UserPolicy` + rutas `/admin/usuarios` (middleware
      `role:admin` + `Gate::authorize` por método).
- [x] Vistas: login/forgot/reset/perfil (Breeze, sin registro ni borrado de
      cuenta), usuarios (index/create/edit en español), dashboard placeholder.
- [x] Tests Pest (TDD): auth completo, gestión de usuarios, auditoría.
      (Auth de Breeze cubierto por tests; gestión de usuarios y auditoría
      agregados en `tests/Feature/Usuarios/` — 22 tests de gestión + 8 de
      auditoría, 52 tests totales en verde. El seed de roles vive en `beforeEach`
      a nivel de archivo; el mismatch de route-model binding se documentó en la
      spec de calidad de análisis estático.)
- [x] Verificación de calidad: pint, PHPStan, CI, smoke en local.
