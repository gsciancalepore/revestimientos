---
paths:
  - 'tests/Feature/Usuarios/**'
---

# Usuarios

## Seed de roles en beforeEach a nivel de archivo, no en Pest.php de subdirectorio
El beforeEach definido en tests/Feature/Usuarios/Pest.php no aplicaba el seed de RolesSeeder en los tests de ese subdirectorio (fallos RoleDoesNotExist). Declarar beforeEach(fn () => $this->seed(RolesSeeder::class)) en cada archivo de test (UserManagementTest, UserAuditTest). Ese Pest.php de subdirectorio se eliminó.
