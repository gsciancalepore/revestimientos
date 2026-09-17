@props(['line'])

@php
    $product = $line['product'];
    $esPorM2 = $product->unidad_venta === App\Enums\ProductSaleUnit::M2;
    // HIG-12: un M2 sin `m2_por_caja` no tiene precio calculable; la línea
    // informa sin exhibir precio ni subtotal (coherente con HIG-17).
    $sinPrecio = $esPorM2 && $product->m2_por_caja === null;
@endphp

<div class="flex gap-4 rounded-lg border bg-white p-4 {{ $line['comprable'] ? 'border-stone-200' : 'border-amber-300 bg-amber-50' }}">
    <a href="{{ route('catalogo.producto', $product) }}" class="h-20 w-20 flex-shrink-0 overflow-hidden rounded bg-stone-100 flex items-center justify-center">
        @if (! empty($product->imagenes))
            <img src="{{ $product->imagenes[0] }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
        @else
            <span class="text-xs text-stone-400">Sin imagen</span>
        @endif
    </a>

    <div class="flex-1 min-w-0">
        <a href="{{ route('catalogo.producto', $product) }}" class="font-semibold text-stone-900 hover:text-orange-700">{{ $product->name }}</a>
        <p class="text-xs uppercase tracking-wide text-stone-500">{{ $product->marca ?? '' }} · {{ $product->category->name ?? '' }}</p>

        @unless ($sinPrecio)
            <p class="mt-1 text-sm text-stone-600">
                {{ $esPorM2 ? $product->m2_por_caja.' m² por caja' : 'Por unidad' }} ·
                ${{ number_format($line['precioUnitario'] / 100, 2, ',', '.') }} {{ $esPorM2 ? 'por caja' : 'por unidad' }}
            </p>
        @endunless

        @unless ($line['comprable'])
            <p class="mt-2 text-sm font-medium text-amber-700">
                @if (! $product->activo)
                    Producto no disponible
                @elseif ($product->stock > 0 && $line['cantidad'] > $product->stock)
                    Stock insuficiente — quedan {{ $product->stock }} {{ $esPorM2 ? 'cajas' : 'unidades' }}
                @elseif ($line['cantidad'] > $product->stock)
                    Producto no disponible
                @else
                    No comprable
                @endif
            </p>
        @endunless

        <div class="mt-3 flex items-center gap-3">
            <form action="{{ route('carrito.update', $product) }}" method="post" class="flex items-center gap-2">
                @csrf
                @method('PATCH')
                <label class="text-sm text-stone-600">Cantidad</label>
                <input type="number" name="cantidad" value="{{ $line['cantidad'] }}" min="0" class="w-20 rounded-md border-stone-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-stone-700">Actualizar</button>
            </form>

            <form action="{{ route('carrito.remove', $product) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-stone-500 hover:text-red-600">Eliminar</button>
            </form>
        </div>
    </div>

    <div class="text-right">
        <p class="text-sm text-stone-500">{{ $line['cantidad'] }} {{ $esPorM2 ? 'caja'.($line['cantidad']===1?'':'s') : 'unidad'.($line['cantidad']===1?'':'es') }}</p>
        @unless ($sinPrecio)
            <p class="font-bold text-stone-900">${{ number_format($line['subtotal'] / 100, 2, ',', '.') }}</p>
        @endunless
    </div>
</div>
