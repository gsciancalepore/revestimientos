---
paths:
  - 'database/seeders/**'
---

# Seeders

## Resetear el cache de permisos de Spatie tras tocar seeders de roles
Spatie cachea permisos y roles; tras modificar RolesSeeder/AdminSeeder (o crear permisos en código) hay que correr `php artisan permission:cache-reset` dentro del contenedor, si no el panel sigue viendo los roles viejos sin error visible. También tras cambios en app/Enums/UserRole.php.
