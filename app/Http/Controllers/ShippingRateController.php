<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShippingRates\StoreShippingRateRequest;
use App\Http\Requests\ShippingRates\UpdateShippingRateRequest;
use App\Models\ShippingRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShippingRateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ShippingRate::class);

        return view('admin.tarifas-envio.index', [
            'tarifas' => ShippingRate::query()->orderBy('cp')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ShippingRate::class);

        return view('admin.tarifas-envio.create');
    }

    public function store(StoreShippingRateRequest $request): RedirectResponse
    {
        Gate::authorize('create', ShippingRate::class);

        ShippingRate::query()->create([
            'cp' => $request->validated('cp'),
            'costo_cents' => $request->validated('costo_cents'),
            'activo' => $request->boolean('activo', true),
        ]);

        return redirect()->route('tarifas-envio.index')->with('status', 'Tarifa creada.');
    }

    public function edit(ShippingRate $tarifa_envio): View
    {
        Gate::authorize('update', $tarifa_envio);

        return view('admin.tarifas-envio.edit', [
            'tarifa' => $tarifa_envio,
        ]);
    }

    public function update(UpdateShippingRateRequest $request, ShippingRate $tarifa_envio): RedirectResponse
    {
        Gate::authorize('update', $tarifa_envio);

        $tarifa_envio->update([
            'cp' => $request->validated('cp'),
            'costo_cents' => $request->validated('costo_cents'),
            'activo' => $request->boolean('activo', true),
        ]);

        return redirect()->route('tarifas-envio.index')->with('status', 'Tarifa actualizada.');
    }

    public function destroy(ShippingRate $tarifa_envio): RedirectResponse
    {
        Gate::authorize('delete', $tarifa_envio);

        $tarifa_envio->delete();

        return redirect()->route('tarifas-envio.index')->with('status', 'Tarifa eliminada.');
    }
}
