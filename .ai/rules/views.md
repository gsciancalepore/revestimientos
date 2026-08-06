---
paths:
  - 'resources/views/**'
---

# Views

## Layout público: clase componente + paso de datos
`<x-layouts.site>` (layout público) requiere la clase `App\View\Components\Layouts\Site` cuyo `render()` devuelve `view('layouts/site')` (patrón Breeze); no basta con tener el archivo en `layouts/site.blade.php`. Además, las variables del hijo NO se propagan al componente layout: hay que pasarlas como atributos (`<x-layouts.site :categorias="$categorias">`).
