<?php

namespace App\Http\Controllers;

use App\Actions\CreateCategoryAction;
use App\Actions\DeleteCategoryAction;
use App\Actions\UpdateCategoryAction;
use App\Http\Requests\Categorias\StoreCategoryRequest;
use App\Http\Requests\Categorias\UpdateCategoryRequest;
use App\Models\Category;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('admin.categorias.index', [
            'categorias' => Category::query()
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('admin.categorias.create', [
            'siguienteOrden' => (int) Category::max('sort_order') + 1,
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $action->execute(
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            sortOrder: $request->validated('sort_order', 0),
        );

        return redirect()
            ->route('categorias.index')
            ->with('status', 'Categoría creada.');
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.categorias.edit', [
            'categoria' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategoryAction $action): RedirectResponse
    {
        Gate::authorize('update', $category);

        $action->execute(
            category: $category,
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            sortOrder: $request->validated('sort_order', $category->sort_order),
        );

        return redirect()
            ->route('categorias.index')
            ->with('status', 'Categoría actualizada.');
    }

    public function destroy(Category $category, DeleteCategoryAction $action): RedirectResponse
    {
        Gate::authorize('delete', $category);

        try {
            $action->execute($category);
        } catch (DomainException $e) {
            return redirect()
                ->route('categorias.index')
                ->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('categorias.index')
            ->with('status', 'Categoría eliminada.');
    }
}
