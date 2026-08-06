@props(['producto'])

@php
    $esPorM2 = $producto->unidad_venta === App\Enums\ProductSaleUnit::M2;
@endphp

<div class="group flex flex-col overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm transition hover:shadow-md">
    <a href="{{ route('catalogo.producto', $producto) }}" class="flex aspect-square items-center justify-center bg-stone-100 p-4">
        @if (! empty($producto->imagenes))
            <img src="{{ $producto->imagenes[0] }}" alt="{{ $producto->name }}" class="h-full w-full object-cover">
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-16 w-16 text-stone-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z" />
            </svg>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-1 p-4">
        <a href="{{ route('catalogo.producto', $producto) }}" class="font-semibold leading-snug text-stone-900 hover:text-orange-700">
            {{ $producto->name }}
        </a>

        @if ($producto->marca)
            <p class="text-xs uppercase tracking-wide text-stone-500">{{ $producto->marca }}</p>
        @endif

        <div class="mt-2">
            @if ($producto->tieneOfertaActiva())
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="text-lg font-bold text-orange-700">${{ number_format($producto->precio_oferta_cents / 100, 2, ',', '.') }}</span>
                    <span class="text-sm text-stone-400 line-through">${{ number_format($producto->precio_cents / 100, 2, ',', '.') }}</span>
                </div>
            @else
                <span class="text-lg font-bold text-stone-900">${{ number_format($producto->precio_cents / 100, 2, ',', '.') }}</span>
            @endif

            <p class="text-xs text-stone-500">
                por {{ $esPorM2 ? 'm²' : 'unidad' }}
            </p>
        </div>

        <div class="mt-auto pt-3">
            @if ($producto->stock > 0)
                <span class="text-xs font-medium text-emerald-700">
                    {{ $esPorM2 ? 'Quedan '.$producto->stock.' cajas' : 'Quedan '.$producto->stock.' unidades' }}
                </span>
            @else
                <span class="inline-block rounded bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Sin stock</span>
            @endif
        </div>
    </div>
</div>
