<x-slot:title>{{ $producto->name }} — {{ config('app.name', 'Cerámica') }}</x-slot:title>

@php
    $esPorM2 = $producto->unidad_venta === App\Enums\ProductSaleUnit::M2;
    $descuento = $producto->descuentoPorcentaje();
@endphp

<x-layouts.site>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <nav class="text-sm text-stone-500">
            <a href="{{ route('catalogo.home') }}" class="hover:text-orange-700">Inicio</a>
            <span class="mx-1">/</span>
            <a href="{{ route('catalogo.categoria', $producto->category) }}" class="hover:text-orange-700">{{ $producto->category->name }}</a>
            <span class="mx-1">/</span>
            <span class="text-stone-800">{{ $producto->name }}</span>
        </nav>

        <div class="mt-6 grid grid-cols-1 gap-10 lg:grid-cols-2">
            <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-stone-200 bg-stone-100">
                @if (! empty($producto->imagenes))
                    <img src="{{ $producto->imagenes[0] }}" alt="{{ $producto->name }}" class="h-full w-full object-cover">
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-20 w-20 text-stone-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z" />
                    </svg>
                @endif
            </div>

            <div>
                <h1 class="text-3xl font-bold text-stone-900">{{ $producto->name }}</h1>

                @if ($producto->marca)
                    <p class="mt-1 text-sm uppercase tracking-wide text-stone-500">{{ $producto->marca }}</p>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($producto->tieneOfertaActiva())
                        <span class="text-3xl font-bold text-orange-700">${{ number_format($producto->precio_oferta_cents / 100, 2, ',', '.') }}</span>
                        <span class="text-lg text-stone-400 line-through">${{ number_format($producto->precio_cents / 100, 2, ',', '.') }}</span>
                        <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">{{ $descuento }} % OFF</span>
                    @else
                        <span class="text-3xl font-bold text-stone-900">${{ number_format($producto->precio_cents / 100, 2, ',', '.') }}</span>
                    @endif
                </div>

                <p class="mt-1 text-sm text-stone-500">por {{ $esPorM2 ? 'm²' : 'unidad' }}</p>

                <div class="mt-4">
                    @if ($producto->stock > 0)
                        <span class="font-medium text-emerald-700">
                            {{ $esPorM2 ? 'Quedan '.$producto->stock.' cajas' : 'Quedan '.$producto->stock.' unidades' }}
                        </span>
                    @else
                        <span class="inline-block rounded bg-stone-200 px-2 py-1 text-sm font-semibold text-stone-600">Sin stock</span>
                    @endif
                </div>

                @if ($producto->descripcion)
                    <p class="mt-4 leading-relaxed text-stone-700">{{ $producto->descripcion }}</p>
                @endif

                @if (! empty($producto->specs))
                    <div class="mt-6 rounded-lg border border-stone-200 bg-white p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Especificaciones</h2>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            @foreach ($producto->specs as $clave => $valor)
                                <div>
                                    <dt class="text-stone-500">{{ $clave }}</dt>
                                    <dd class="font-medium text-stone-800">{{ $valor }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-3">
                    <form action="{{ route('carrito.add') }}" method="post" class="rounded-lg border border-stone-200 bg-white p-4">
                        @csrf
                        <input type="hidden" name="producto" value="{{ $producto->slug }}">
                        @if ($esPorM2)
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Agregar al carrito</h2>
                            <div class="mt-3">
                                <label for="add-superficie" class="text-sm font-medium text-stone-700">Superficie (m²)</label>
                                <input id="add-superficie" type="number" name="superficie" step="0.01" min="0.01" placeholder="Ej. 12.5" class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            </div>
                            <label class="mt-3 flex items-center gap-2 text-sm font-medium text-stone-700">
                                <input type="checkbox" name="desperdicio" value="1" class="rounded border-stone-300 text-orange-600 shadow-sm focus:ring-orange-500">
                                Incluir 10 % de desperdicio
                            </label>
                            <p class="mt-2 text-xs text-stone-400">Se calcularán las cajas necesarias ({{ $producto->m2_por_caja }} m² por caja).</p>
                        @else
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Agregar al carrito</h2>
                            <div class="mt-3">
                                <label for="add-cantidad" class="text-sm font-medium text-stone-700">Cantidad (unidades)</label>
                                <input id="add-cantidad" type="number" name="cantidad" min="1" value="1" class="mt-1 block w-24 rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            </div>
                        @endif
                        <button type="submit" class="mt-4 w-full rounded-md bg-orange-700 px-4 py-2 text-sm font-medium text-white hover:bg-orange-800">Agregar al carrito</button>
                    </form>

                @if ($esPorM2)
                    <div
                        class="rounded-lg border border-stone-200 bg-white p-4"
                        x-data="calculadoraM2({{ $producto->m2_por_caja ?? 1 }})"
                    >
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Calculadora m² → cajas</h2>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="calc-largo" class="text-sm font-medium text-stone-700">Largo (cm)</label>
                                <input
                                    id="calc-largo"
                                    type="number"
                                    min="0"
                                    x-model.number="largo"
                                    class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                >
                            </div>
                            <div>
                                <label for="calc-ancho" class="text-sm font-medium text-stone-700">Ancho (cm)</label>
                                <input
                                    id="calc-ancho"
                                    type="number"
                                    min="0"
                                    x-model.number="ancho"
                                    class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                >
                            </div>
                        </div>

                        <p class="mt-3 text-center text-sm text-stone-400">— o —</p>

                        <div class="mt-3">
                            <label for="calc-superficie" class="text-sm font-medium text-stone-700">Superficie (m²)</label>
                            <input
                                id="calc-superficie"
                                type="number"
                                min="0"
                                step="0.01"
                                x-model.number="superficie"
                                class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                            >
                        </div>

                        <label class="mt-4 flex items-center gap-2 text-sm font-medium text-stone-700">
                            <input
                                type="checkbox"
                                x-model="desperdicio"
                                class="rounded border-stone-300 text-orange-600 shadow-sm focus:ring-orange-500"
                            >
                            Incluir 10 % de desperdicio
                        </label>

                        <div class="mt-5 rounded-md bg-stone-50 p-4 text-center" x-show="m2Efectivo > 0">
                            <p class="text-sm text-stone-500">
                                Superficie a cubrir:
                                <span class="font-semibold text-stone-900" x-text="m2Mostrar.toFixed(2)"></span> m²
                            </p>
                            <p class="mt-1 text-2xl font-bold text-stone-900">
                                <span x-text="cajas"></span> caja<span x-text="cajas === 1 ? '' : 's'"></span>
                            </p>
                        </div>

                        <p class="mt-3 text-xs text-stone-400">La calculadora solo estima las cajas; el pedido se confirma con un asesor.</p>
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('calculadoraM2', (m2PorCaja) => ({
                m2PorCaja: parseFloat(m2PorCaja),
                largo: null,
                ancho: null,
                superficie: null,
                desperdicio: false,

                get m2Dimensiones() {
                    if (this.largo > 0 && this.ancho > 0) {
                        return (this.largo * this.ancho) / 10000;
                    }

                    return 0;
                },

                get m2Efectivo() {
                    return Math.max(this.superficie > 0 ? this.superficie : this.m2Dimensiones, 0);
                },

                get m2Mostrar() {
                    return this.desperdicio ? this.m2Efectivo * 1.1 : this.m2Efectivo;
                },

                get cajas() {
                    if (this.m2Efectivo <= 0) {
                        return 0;
                    }

                    return Math.ceil(this.m2Mostrar / this.m2PorCaja);
                },
            }));
        });
    </script>
</x-layouts.site>
