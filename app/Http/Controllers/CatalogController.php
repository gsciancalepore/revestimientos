<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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

    public function producto(Product $producto): View
    {
        abort_unless($producto->activo, 404);

        return view('public.producto', [
            'producto' => $producto->load('category'),
        ]);
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
