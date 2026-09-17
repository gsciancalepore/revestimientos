<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\M2Calculator;
use App\Services\ProductSpecs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private const int PER_PAGE = 12;

    public function home(): View
    {
        return view('public.home', [
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'destacados' => Product::query()
                ->with('category')
                ->activo()
                ->conOferta()
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function catalogo(Request $request): View
    {
        $categoria = $this->resolverCategoria($request);

        $query = Product::query()->with('category')->activo();
        $this->filtrar($query, $request, $categoria);

        return view('public.catalogo', [
            'titulo' => 'Catálogo',
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'productos' => $query->orderBy('name')->paginate(self::PER_PAGE)->withQueryString(),
            'marcas' => $this->marcasDisponibles($categoria),
            'filtrosSpecs' => $categoria !== null ? $this->filtrosSpecs($categoria) : [],
            'categoria' => $categoria,
            'soloOfertas' => false,
        ]);
    }

    public function categoria(Category $categoria, Request $request): View
    {
        $query = Product::query()->with('category')->activo()->deCategoria($categoria);
        $this->filtrar($query, $request, $categoria);

        return view('public.catalogo', [
            'titulo' => $categoria->name,
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'productos' => $query->orderBy('name')->paginate(self::PER_PAGE)->withQueryString(),
            'marcas' => $this->marcasDisponibles($categoria),
            'filtrosSpecs' => $this->filtrosSpecs($categoria),
            'categoria' => $categoria,
            'soloOfertas' => false,
        ]);
    }

    public function ofertas(Request $request): View
    {
        $query = Product::query()->with('category')->activo()->conOferta();

        if ($request->filled('marca')) {
            $query->porMarca((string) $request->query('marca'));
        }

        if ($request->filled('q')) {
            $query->buscar((string) $request->query('q'));
        }

        return view('public.catalogo', [
            'titulo' => 'Ofertas',
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'productos' => $query->orderBy('name')->paginate(self::PER_PAGE)->withQueryString(),
            'marcas' => $this->marcasDisponibles(),
            'filtrosSpecs' => [],
            'categoria' => null,
            'soloOfertas' => true,
        ]);
    }

    public function producto(Request $request, Product $producto, M2Calculator $calculator): View
    {
        abort_unless($producto->activo, 404);

        $producto->load('category');

        return view('public.producto', [
            'producto' => $producto,
            'estimacion' => $this->estimarCajas($request, $producto, $calculator),
        ]);
    }

    /**
     * Estimación m²→cajas calculada en el servidor con M2Calculator (HIG-13):
     * la ficha no duplica la lógica de redondeo. Sin parámetros no hay
     * estimación; es el mismo cálculo que arma el carrito.
     *
     * @return array{m2: string, cajas: int, desperdicio: bool}|null
     */
    private function estimarCajas(Request $request, Product $producto, M2Calculator $calculator): ?array
    {
        if (! $producto->isM2Mode() || $producto->m2_por_caja === null) {
            return null;
        }

        $m2 = $this->m2DesdeConsulta($request, $calculator);

        if ($m2 === null) {
            return null;
        }

        $conDesperdicio = $request->boolean('desperdicio');

        if ($conDesperdicio) {
            $m2 = $calculator->aplicarDesperdicio($m2);
        }

        /** @var numeric-string $m2PorCaja */
        $m2PorCaja = (string) $producto->m2_por_caja;

        return [
            'm2' => $m2,
            'cajas' => $calculator->cajasNecesarias($m2, $m2PorCaja),
            'desperdicio' => $conDesperdicio,
        ];
    }

    /**
     * La superficie directa manda; si no hay, largo × ancho en centímetros.
     * Lo no numérico o no positivo se ignora (no hay estimación).
     *
     * @return numeric-string|null
     */
    private function m2DesdeConsulta(Request $request, M2Calculator $calculator): ?string
    {
        $superficie = $this->positivo($request->query('superficie'));

        if ($superficie !== null) {
            return $superficie;
        }

        $largo = $this->positivo($request->query('largo'));
        $ancho = $this->positivo($request->query('ancho'));

        if ($largo === null || $ancho === null) {
            return null;
        }

        return $calculator->m2DesdeDimensiones($largo, $ancho);
    }

    /**
     * @return numeric-string|null
     */
    private function positivo(mixed $valor): ?string
    {
        if (! is_string($valor) || ! is_numeric($valor) || (float) $valor <= 0) {
            return null;
        }

        /** @var numeric-string $valor */
        return $valor;
    }

    private function resolverCategoria(Request $request): ?Category
    {
        if (! $request->filled('categoria')) {
            return null;
        }

        return Category::query()->where('slug', (string) $request->query('categoria'))->first();
    }

    /**
     * Aplica los filtros combinables del listado (regla 76).
     *
     * @param  Builder<Product>  $query
     */
    private function filtrar(Builder $query, Request $request, ?Category $categoria): void
    {
        if ($categoria !== null) {
            $query->deCategoria($categoria);
        }

        if ($request->boolean('oferta')) {
            $query->conOferta();
        }

        if ($request->filled('marca')) {
            $query->porMarca((string) $request->query('marca'));
        }

        if ($request->filled('q')) {
            $query->buscar((string) $request->query('q'));
        }

        foreach ((array) $request->query('specs', []) as $clave => $valor) {
            if (is_string($clave) && is_string($valor) && $valor !== '') {
                $query->specsValor($clave, $valor);
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function marcasDisponibles(?Category $categoria = null): Collection
    {
        $query = Product::query()->activo()->whereNotNull('marca')->where('marca', '!=', '');

        if ($categoria !== null) {
            $query->deCategoria($categoria);
        }

        return $query->distinct()->orderBy('marca')->pluck('marca');
    }

    /**
     * Filtros de specs solo para la familia de la categoría y solo con valores
     * presentes en los productos publicados (regla 77).
     *
     * @return array<string, Collection<int, non-empty-string>>
     */
    private function filtrosSpecs(Category $categoria): array
    {
        $claves = app(ProductSpecs::class)->allowedKeysFor($categoria);
        $filtros = [];

        foreach ($claves as $clave) {
            $valores = Product::query()
                ->activo()
                ->deCategoria($categoria)
                ->whereNotNull('specs->'.$clave)
                ->distinct()
                ->selectRaw('"specs"->>? as valor', [$clave])
                ->orderBy('valor')
                ->pluck('valor')
                ->filter(fn (mixed $valor): bool => is_string($valor) && $valor !== '')
                ->values();

            if ($valores->isNotEmpty()) {
                $filtros[$clave] = $valores;
            }
        }

        return $filtros;
    }
}
