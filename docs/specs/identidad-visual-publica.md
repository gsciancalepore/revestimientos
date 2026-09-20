# Spec — Identidad visual de la tienda pública

- **Estado**: **aprobada (2026-09-20)** por el dueño, tras revisión de
  `revisor-spec` (aprobable con correcciones menores, ya aplicadas: contrato
  de `cart-line` en la regla 5, criterio de aceptación de la regla 11, caso
  borde de accesibilidad para componentes interactivos). Pendiente de
  implementación por fases (Fase A primero).
- **Fuentes**: decisión del dueño (2026-09-20) de encarar el rediseño visual
  "público primero" y "mismo stack, solo mejorar diseño"; `docs/roadmap.md`
  §Cómo se escribe una spec nueva; `.ai/rules/views.md` (patrón
  `<x-layouts.site>`); inventario del código real hecho para este borrador
  (§Contexto); `docs/specs/calidad-onboarding.md` y
  `docs/specs/calidad-analisis-estatico.md` como modelo de formato.

## Por qué esta spec no usa la numeración global de reglas

La numeración global (1–166 al 2026-09-16, ver `docs/roadmap.md`) está
reservada a reglas de **negocio**: lo que un actor puede hacer, qué pasa con
el dinero, el stock, los pedidos. Esta spec no agrega ni cambia ninguna: no
toca rutas, controladores, Form Requests, Actions ni el dominio. Es
presentación pura — Blade, Tailwind y, si hace falta, Alpine.js — sobre
pantallas que ya existen y ya funcionan. Sigue el precedente de
`calidad-onboarding.md`/`calidad-analisis-estatico.md`: mismo esqueleto de
spec, pero con una sección de reglas **numerada aparte** que no compite con
la numeración de dominio ni con el prefijo `HIG-xx` (reservado a higiene de
specs cerradas).

## Objetivo

Dar a la tienda pública una identidad visual propia y coherente —hoy es el
scaffold por defecto de Laravel Breeze, sin paleta de marca, con el logo
genérico de Laravel y tipografía por defecto— sin modificar dominio, rutas,
controladores ni el contrato de datos que las vistas ya reciben. El resultado
debe leerse como un sistema (paleta, tipografía, componentes) y no como
retoques sueltos por página.

## Alcance

**Cubre**: el layout público compartido (`layouts/site.blade.php`) y las seis
vistas que lo usan — `public/home.blade.php`, `public/catalogo.blade.php`
(también sirve `/ofertas` y `/categorias/{categoria}`),
`public/producto.blade.php`, `cart/show.blade.php`, `checkout/show.blade.php`,
`checkout/success.blade.php` — y los componentes que esas vistas ya
instancian: `components/product-card.blade.php`,
`components/cart-line.blade.php`.

**No cubre, deliberadamente** (YAGNI — `PROJECT_PRINCIPLES.md` regla 5): el
panel de administración (`layouts/app.blade.php`,
`layouts/navigation.blade.php`, todo `resources/views/admin/**`), la vista de
despacho, ni las pantallas de autenticación heredadas de Breeze
(`resources/views/auth/**`, `layouts/guest.blade.php`). Tampoco introduce un
framework de JS, una librería de componentes, ni dependencias nuevas
(`AGENTS.md`: "no introducir dependencias sin respaldo de spec") — el stack
sigue siendo Blade + Tailwind v4 + Alpine.js. Un plugin adicional de Tailwind
(en la línea de `@tailwindcss/forms`, ya instalado) queda autorizado si una
fase concreta lo justifica; un framework de JS, no.

## Contexto — qué hay hoy exactamente

Relevado sobre el código real (2026-09-20):

- **Sin identidad de marca**: `resources/css/app.css` solo define
  `--font-sans: 'Instrument Sans'` en `@theme`; no hay tokens de color propios,
  todo usa la paleta default de Tailwind. `components/application-logo.blade.php`
  es el diamante de Laravel — no se usa en `layouts/site.blade.php` (ahí el
  "logo" es el texto `{{ config('app.name') }}`).
- **Layout público único y header/footer inline**: las seis vistas públicas
  entran por `<x-layouts.site :categorias="...">` (requiere la clase
  `App\View\Components\Layouts\Site`, no alcanza con el archivo Blade —
  `.ai/rules/views.md`). Header y footer viven inline dentro de
  `layouts/site.blade.php`; no existen componentes `<x-header>`/`<x-footer>`
  separados.
- **Inconsistencia existente**: `public/producto.blade.php` es la única vista
  pública que no pasa `:categorias` al layout, así que la ficha de producto
  nunca muestra la barra de categorías del header que sí ven las demás
  páginas. Nadie lo pidió así — es una omisión, no una decisión.
- **Los componentes de Breeze no se usan en público**: `primary-button`,
  `secondary-button`, `danger-button`, `nav-link` y `application-logo` sirven
  al panel admin y a auth; ninguna vista pública los instancia — todo botón
  público es `<button>`/`<a>` con clases Tailwind inline. No forman parte del
  sistema visual público actual.
- **Alpine.js disponible pero sin uso**: cero `x-data` en las vistas públicas
  relevadas. No hay compromiso previo de usarlo; queda disponible si una regla
  concreta lo justifica (p. ej. un menú mobile).
- **Sin dark mode** en ningún punto del proyecto.
- **Patrones ya estandarizados, punto de partida para no reinventar**:
  contenedor `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` repetido en las seis
  vistas; breakpoints usados: solo `sm:`/`lg:`/`xl:` (nunca `md:`/`2xl:`);
  paleta actual sin tokens propios pero con un uso consistente:
  `stone` (neutro), `orange` (acento/CTA), `emerald` (éxito/oferta), `amber`
  (advertencia no bloqueante), `red` (error).
- **Contrato de datos que las vistas ya reciben** (esta spec no lo cambia):
  `product-card` usa `unidad_venta`, `route('catalogo.producto', $producto)`,
  `imagenes[0]`, `name`, `marca` (nullable), `tieneOfertaActiva()`,
  `precio_oferta_cents`, `precio_cents`, `stock`; `cart-line` recibe un array
  `line` con `product`, `comprable`, `precioUnitario`, `cantidad`, `subtotal`.
- **Gran cantidad de textos exactos verificados por tests** en estas vistas:
  badges (`"Sin stock"`, `"{n} % OFF"`, `"{n} cajas"`, `"{n} unidades"`),
  estados vacíos (`"Tu carrito está vacío"`, `"No se encontraron
  productos."`), mensajes de línea no comprable (`"Producto no disponible"`,
  `"Stock insuficiente"`), y formato de moneda literal (`"$X,XX"` con coma
  decimal; `"Envío: $X,XX"` y `"Total: $X,XX"` con el label pegado al monto).

## Reglas de diseño (no son reglas de negocio; la numeración de reglas de
negocio continúa en las specs de dominio)

1. **Paleta de marca como tokens Tailwind**: se definen colores propios en
   `@theme` de `resources/css/app.css` (no se sigue usando la paleta default
   de Tailwind sin nombrar). La paleta reemplaza el uso actual de
   `stone`/`orange`/`emerald`/`amber`/`red` por tokens con nombre semántico
   propio del proyecto (p. ej. `--color-brand-*`), conservando qué rol cumple
   cada uno (neutro, acento/CTA, éxito, advertencia, error) para no romper la
   lectura de las pantallas.
2. **Tipografía**: se define una tipografía de marca (o se ratifica
   `Instrument Sans` con una decisión explícita, no por omisión) más una
   escala tipográfica consistente (tamaños de H1/H2/H3/body) aplicada por
   igual en las seis vistas.
3. **Un layout público con header y footer con identidad**:
   `layouts/site.blade.php` se rediseña conservando el patrón
   `<x-layouts.site :categorias="...">` (`.ai/rules/views.md`) y el contrato
   de que solo pasa `categorias` como dato. Se decide en la fase de
   implementación si el header/footer se extraen a componentes propios o
   siguen inline; cualquiera de las dos opciones es válida siempre que no
   dupliquen markup entre vistas.
4. **`public/producto.blade.php` pasa `:categorias` al layout**: se corrige
   la inconsistencia relevada en el Contexto — la ficha de producto muestra
   la misma barra de categorías que el resto de las páginas públicas.
5. **`product-card` y `cart-line` conservan su contrato de datos**: `home.blade.php`
   y `catalogo.blade.php` siguen usando exclusivamente
   `components/product-card.blade.php` (no se crean tarjetas alternativas);
   `cart/show.blade.php` sigue usando exclusivamente
   `components/cart-line.blade.php`. El rediseño puede cambiar la maquetación
   de ambos componentes pero no las propiedades que reciben (§Contexto:
   `product-card` — `unidad_venta`, `route('catalogo.producto', $producto)`,
   `imagenes[0]`, `name`, `marca`, `tieneOfertaActiva()`,
   `precio_oferta_cents`, `precio_cents`, `stock`; `cart-line` — `product`,
   `comprable`, `precioUnitario`, `cantidad`, `subtotal`).
6. **Botones y CTA públicos consistentes**: se define un tratamiento visual
   único para los llamados a la acción de las vistas públicas (p. ej.
   "Agregar al carrito", "Finalizar compra", "Ver catálogo"), aplicado por
   igual en las seis vistas. No se reutilizan ni se modifican
   `primary-button`/`secondary-button`/`danger-button` (son del panel admin,
   fuera de alcance — regla siguiente).
7. **El panel admin y auth quedan intactos**: esta spec no modifica
   `layouts/app.blade.php`, `layouts/navigation.blade.php`,
   `layouts/guest.blade.php`, ningún archivo bajo `resources/views/admin/**`
   ni `resources/views/auth/**`, ni los componentes
   `primary-button`/`secondary-button`/`danger-button`/`nav-link`. Si el
   rediseño de un componente compartido pudiera afectarlos, se resuelve
   duplicando el componente para uso público en vez de modificar el
   existente.
8. **Responsive con los breakpoints ya en uso**: `sm:`/`lg:`/`xl:` (se puede
   sumar `md:` si una pantalla concreta lo necesita, pero no como regla
   general nueva). Toda vista pública se verifica en al menos dos anchos:
   mobile (~375px) y desktop (~1280px).
9. **Accesibilidad mínima**: toda imagen de producto lleva `alt` con el
   nombre del producto; todo campo de formulario público (buscador,
   calculadora m², checkout) tiene su `<label>` asociado; los estados de foco
   (`:focus-visible`) son visibles sobre la nueva paleta. Si alguna fase
   introduce un componente interactivo nuevo (p. ej. un menú mobile con
   Alpine.js — ver §Contexto), ese componente debe ser operable por teclado
   (sin trampas de foco) y llevar los atributos ARIA que correspondan a su
   rol (p. ej. `aria-expanded` en un botón que abre/cierra un menú).
10. **El copy exacto que los tests afirman no cambia sin decisión
    consciente**: la maquetación (clases, estructura, layout) es libre; los
    textos literales listados en el Contexto (badges, estados vacíos,
    mensajes de línea no comprable, formato de moneda) se preservan tal
    cual. Si una fase de implementación decide cambiar alguno, actualiza el
    test correspondiente en la misma rama — nunca lo deja romperse en
    silencio ni lo saltea con un test menos estricto.
11. **Sin dark mode, sin framework de JS nuevo, sin librería de componentes**:
    YAGNI explícito — nada de esto se pidió y ninguno tiene un caso de uso
    concreto todavía.

## Casos borde

- Catálogo o resultado de búsqueda vacío: se conserva el mensaje `"No se
  encontraron productos."` (regla 10), con un tratamiento visual acorde al
  resto del sistema (no una pantalla en blanco).
- Producto sin imagen (`imagenes` vacío): `product-card` y la ficha muestran
  un placeholder consistente con la paleta nueva, no un ícono roto ni un
  hueco vacío.
- Carrito vacío: se conserva `"Tu carrito está vacío"` + el link `"Ver
  catálogo"` (regla 10).
- Nombre de producto o dirección de envío muy largos: no rompen el grid ni
  desbordan la tarjeta/columna (truncamiento o wrap, a definir en
  implementación, pero sin overflow visual).
- Producto con oferta activa y producto con stock negativo (regla 146 de la
  Spec 08): el rediseño no cambia la regla de negocio de que el stock
  negativo nunca se muestra al público como número — solo cambia cómo se ve
  el badge de "Sin stock" que ya corresponde.
- Componente interactivo nuevo (regla 9): si una fase introduce un menú
  mobile u otro widget con Alpine.js, debe poder operarse sin mouse
  (`Tab`/`Enter`/`Esc`) y exponer su estado por ARIA. Si ninguna fase termina
  necesitando un componente así, esta regla no aplica y no hace falta
  construir nada por adelantado (YAGNI).

## Criterios de aceptación

- [ ] Paleta de marca definida como tokens en `@theme` de
      `resources/css/app.css`, usada en las seis vistas públicas en lugar de
      la paleta default de Tailwind sin nombrar (regla 1).
- [ ] Tipografía y escala tipográfica documentadas y aplicadas de forma
      consistente en las seis vistas (regla 2).
- [ ] `layouts/site.blade.php` rediseñado conservando el contrato
      `<x-layouts.site :categorias="...">` (regla 3).
- [ ] `public/producto.blade.php` pasa `:categorias` y muestra la barra de
      categorías del header (regla 4).
- [ ] `product-card` y `cart-line` mantienen su contrato de datos exacto
      (§Contexto) tras el rediseño (regla 5).
- [ ] Tratamiento único de botones/CTA en las seis vistas, sin tocar
      `primary-button`/`secondary-button`/`danger-button` (reglas 6 y 7).
- [ ] `layouts/app.blade.php`, `layouts/navigation.blade.php`,
      `layouts/guest.blade.php`, `resources/views/admin/**` y
      `resources/views/auth/**` sin cambios (diff verificable, regla 7).
- [ ] Las seis vistas verificadas en mobile (~375px) y desktop (~1280px), sin
      overflow ni contenido cortado (regla 8).
- [ ] `alt` en imágenes de producto, `<label>` en campos de formulario
      público, foco visible sobre la paleta nueva (regla 9).
- [ ] Suite completa en verde tras cada fase: ningún `assertSee`/
      `assertSeeText` existente roto, o el test actualizado en la misma rama
      con la razón anotada en el mensaje de commit (regla 10). Pint, PHPStan
      nivel 8, Pest verde — mismo gate que toda spec del repo.
- [ ] `package.json` y `composer.json` sin dependencias nuevas de framework
      JS o librería de componentes al cerrar la spec (diff verificable,
      regla 11).
- [ ] Recorrido manual del dueño en desktop y mobile de las seis vistas,
      documentado como sincronía en esta spec al cerrar cada fase (no
      automatizable — mismo criterio que el repo ya aplicó a la verificación
      del webhook: "verde en la suite no es lo mismo que verificado").

## Decisiones de diseño (a completar en la implementación, con su porqué)

- Paleta concreta (valores hex/oklch) y tipografía concreta: quedan abiertas
  a esta spec porque son una decisión de gusto del dueño más que de negocio;
  se proponen en la rama de la Fase A y se ratifican ahí, anotadas como
  sincronía.
- Extracción o no de `<x-header>`/`<x-footer>` como componentes propios:
  igual que el punto anterior, se decide en la Fase A según cuánto markup
  termine compartiéndose.

## Tareas técnicas — fases de entrega

Se entrega por fases, como las Specs 07/08/Higiene 03: un PR por fase, cada
una deja la suite en verde y dejar algo verificable por sí solo.

- [ ] **Fase A — Sistema base**: paleta y tipografía (reglas 1, 2),
      `layouts/site.blade.php` rediseñado (regla 3), corrección de
      `producto.blade.php` (regla 4), tratamiento de botones/CTA (regla 6).
      Rama `feat/identidad-visual-publica-a`.
- [ ] **Fase B — Home y catálogo**: `public/home.blade.php`,
      `public/catalogo.blade.php`, `components/product-card.blade.php`.
      Rama `feat/identidad-visual-publica-b`.
- [ ] **Fase C — Ficha, carrito y checkout**: `public/producto.blade.php`,
      `cart/show.blade.php`, `components/cart-line.blade.php`,
      `checkout/show.blade.php`, `checkout/success.blade.php`. Rama
      `feat/identidad-visual-publica-c`.
- [ ] Actualizar `docs/roadmap.md` (nueva fila o nota de la spec) al cerrar
      cada fase, según el DoD del repo.

## Nota de handoff

Esta spec es puramente de presentación: no autoriza tocar `app/Actions`,
`app/Http/Controllers`, `app/Http/Requests`, rutas, ni el dominio. Si al
implementar aparece la necesidad de cambiar un dato que una vista recibe
(agregar un campo nuevo al controller, por ejemplo), eso es una ampliación de
alcance que requiere volver a este documento y no se asume en silencio
(`PROJECT_PRINCIPLES.md` regla 2: nunca asumir reglas de negocio). El
contrato de datos de `product-card` y `cart-line` documentado en el Contexto
es el límite: si no alcanza, se pregunta antes de programar.
