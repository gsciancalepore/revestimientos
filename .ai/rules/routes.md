---
paths:
  - routes/web.php
---

# Routes

## Route-model binding debe matchear el parámetro singular de la ruta
Route::resource('usuarios', ...) auto-singulariza el parámetro a {usuario}. Si el controller declara User $user, el binding implícito no matchea: se inyecta un User vacío (exists=false) y save() hace INSERT con password null → errores 500/validación en cascada. Fijar siempre ->parameters(['usuarios' => 'user']) y que UpdateUserRequest use ignore($this->route('user')).
