<?php

namespace App\Http\Controllers;

use App\Actions\CreateProductAction;
use App\Actions\DeleteProductAction;
use App\Actions\UpdateProductAction;
use App\Enums\ProductSaleUnit;
use App\Http\Requests\Productos\StoreProductRequest;
use App\Http\Requests\Productos\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductSpecs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('admin.productos.index', [
            'productos' => Product::query()
                ->with('category')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.productos.create', [
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'unidadesVenta' => ProductSaleUnit::cases(),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $action->execute(
            categoryId: $request->validated('category_id'),
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            codigo: $request->validated('codigo'),
            unidadVenta: ProductSaleUnit::from($request->validated('unidad_venta')),
            precioCents: $request->validated('precio_cents'),
            marca: $request->validated('marca'),
            descripcion: $request->validated('descripcion'),
            precioOfertaCents: $request->validated('precio_oferta_cents'),
            m2PorCaja: $request->validated('m2_por_caja'),
            stock: $request->validated('stock'),
            activo: $request->boolean('activo'),
            imagenes: $request->validated('imagenes'),
            specs: $request->validated('specs'),
        );

        return redirect()
            ->route('productos.index')
            ->with('status', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $categoria = Category::query()->findOrFail($product->category_id);

        return view('admin.productos.edit', [
            'producto' => $product,
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'unidadesVenta' => ProductSaleUnit::cases(),
            'clavesSpecs' => app(ProductSpecs::class)->allowedKeysFor($categoria),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProductAction $action): RedirectResponse
    {
        Gate::authorize('update', $product);

        $action->execute(
            product: $product,
            categoryId: $request->validated('category_id'),
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            codigo: $request->validated('codigo'),
            unidadVenta: ProductSaleUnit::from($request->validated('unidad_venta')),
            precioCents: $request->validated('precio_cents'),
            marca: $request->validated('marca'),
            descripcion: $request->validated('descripcion'),
            precioOfertaCents: $request->validated('precio_oferta_cents'),
            m2PorCaja: $request->validated('m2_por_caja'),
            stock: $request->validated('stock'),
            activo: $request->boolean('activo'),
            imagenes: $request->validated('imagenes'),
            specs: $request->validated('specs'),
        );

        return redirect()
            ->route('productos.index')
            ->with('status', 'Producto actualizado.');
    }

    public function destroy(Product $product, DeleteProductAction $action): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $action->execute($product);

        return redirect()
            ->route('productos.index')
            ->with('status', 'Producto eliminado.');
    }
}
