<x-slot:title>{{ $titulo }} — {{ config('app.name', 'Cerámica') }}</x-slot:title>

@php
    $specsSeleccionadas = collect(request()->query('specs', []))->filter(fn ($v) => $v !== '' && $v !== null);
    $filtrosActivos = request()->filled('marca') || request()->boolean('oferta') || request()->filled('q') || $specsSeleccionadas->isNotEmpty();
    $rutaBase = $categoria !== null
        ? route('catalogo.categoria', $categoria)
        : ($soloOfertas ? route('catalogo.ofertas') : route('catalogo.index'));
@endphp

<x-layouts.site :categorias="$categorias">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-stone-900">{{ $titulo }}</h1>
                <p class="mt-1 text-sm text-stone-500">
                    {{ $productos->total() }} producto{{ $productos->total() === 1 ? '' : 's' }}
                </p>
            </div>

            @if ($filtrosActivos)
                <a href="{{ $rutaBase }}" class="text-sm font-medium text-orange-700 hover:text-orange-800">Quitar filtros</a>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[240px_1fr]">
            <aside class="space-y-6">
                <form action="{{ $rutaBase }}" method="get" class="space-y-6 rounded-lg border border-stone-200 bg-white p-4 shadow-sm">
                    @if ($categoria !== null && $filtrosSpecs !== [])
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Atributos</h2>
                            <div class="mt-3 space-y-4">
                                @foreach ($filtrosSpecs as $clave => $valores)
                                    <div>
                                        <label for="specs-{{ $clave }}" class="text-sm font-medium text-stone-700">{{ $clave }}</label>
                                        <select
                                            id="specs-{{ $clave }}"
                                            name="specs[{{ $clave }}]"
                                            class="mt-1 block w-full rounded-md border-stone-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                        >
                                            <option value="">Todas las {{ $clave }}</option>
                                            @foreach ($valores as $valor)
                                                <option value="{{ $valor }}" @selected((string) request()->query("specs.{$clave}") === (string) $valor)>
                                                    {{ $valor }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($marcas->isNotEmpty())
                        <div>
                            <label for="filtro-marca" class="text-sm font-medium text-stone-700">Marca</label>
                            <select
                                id="filtro-marca"
                                name="marca"
                                class="mt-1 block w-full rounded-md border-stone-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                            >
                                <option value="">Todas las marcas</option>
                                @foreach ($marcas as $marca)
                                    <option value="{{ $marca }}" @selected(request('marca') === $marca)>{{ $marca }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <label class="flex items-center gap-2 text-sm font-medium text-stone-700">
                        <input
                            type="checkbox"
                            name="oferta"
                            value="1"
                            class="rounded border-stone-300 text-orange-600 shadow-sm focus:ring-orange-500"
                            @checked(request()->boolean('oferta') || $soloOfertas)
                            @disabled($soloOfertas)
                        >
                        Solo ofertas
                    </label>

                    <button type="submit" class="w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">
                        Aplicar filtros
                    </button>
                </form>
            </aside>

            <section>
                @if ($productos->isEmpty())
                    <div class="rounded-lg border border-dashed border-stone-300 bg-white p-12 text-center">
                        <p class="text-stone-500">No se encontraron productos.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($productos as $producto)
                            <x-product-card :producto="$producto" />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $productos->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.site>
