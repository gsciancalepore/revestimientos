<?php

namespace App\Http\Controllers;

use App\Actions\CreateShippingRateAction;
use App\Actions\DeleteShippingRateAction;
use App\Actions\UpdateShippingRateAction;
use App\Http\Requests\ShippingRates\StoreShippingRateRequest;
use App\Http\Requests\ShippingRates\UpdateShippingRateRequest;
use App\Models\ShippingRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShippingRateController extends Controller
{
    public function __construct(
        private CreateShippingRateAction $createShippingRate,
        private UpdateShippingRateAction $updateShippingRate,
        private DeleteShippingRateAction $deleteShippingRate,
    ) {}

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

        $this->createShippingRate->execute(
            $request->validated('cp'),
            $request->validated('costo_cents'),
            $request->boolean('activo', true),
        );

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

        $this->updateShippingRate->execute(
            $tarifa_envio,
            $request->validated('cp'),
            $request->validated('costo_cents'),
            $request->boolean('activo', true),
        );

        return redirect()->route('tarifas-envio.index')->with('status', 'Tarifa actualizada.');
    }

    public function destroy(ShippingRate $tarifa_envio): RedirectResponse
    {
        Gate::authorize('delete', $tarifa_envio);

        $this->deleteShippingRate->execute($tarifa_envio);

        return redirect()->route('tarifas-envio.index')->with('status', 'Tarifa eliminada.');
    }
}
