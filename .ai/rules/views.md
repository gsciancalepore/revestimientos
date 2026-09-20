---
paths:
  - 'resources/views/**'
---

# Views

## Layout público: clase componente + paso de datos
`<x-layouts.site>` (layout público) requiere la clase `App\View\Components\Layouts\Site` cuyo `render()` devuelve `view('layouts/site')` (patrón Breeze); no basta con tener el archivo en `layouts/site.blade.php`. Además, las variables del hijo NO se propagan al componente layout: hay que pasarlas como atributos (`<x-layouts.site :categorias="$categorias">`).

## Fuente cargada vs. fuente declarada en `@theme`
El `<link>` de Bunny Fonts en el `<head>` (`family=nombre-fuente:pesos`) y el nombre citado en `--font-sans`/`--font-display` de `resources/css/app.css` tienen que coincidir exactamente. Si no coinciden, el navegador no encuentra la fuente por nombre y cae en el siguiente fallback de la lista **sin ningún error visible** — así estuvo el proyecto sirviendo `Figtree` desde el `<link>` mientras `@theme` pedía `'Instrument Sans'` (detectado en `identidad-visual-publica.md` §Sincronía 2026-09-20, corregido en `layouts/site.blade.php`). Al agregar o cambiar una tipografía, verificar los dos lugares en el mismo cambio.
