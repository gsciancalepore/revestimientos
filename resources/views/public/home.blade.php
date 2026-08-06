<x-slot:title>{{ config('app.name', 'Cerámica') }}</x-slot:title>

<x-layouts.site :categorias="$categorias">
    <section class="bg-stone-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-4xl font-bold tracking-tight">Revestimientos y pastinas al por mayor</h1>
            <p class="mt-3 max-w-2xl text-stone-300">
                Cerámicas, porcelanatos, pastinas y adhesivos para obra y revestimiento.
                Consultá el stock real y calculá cuántas cajas necesitás para tu superficie.
            </p>
            <a href="{{ route('catalogo.index') }}" class="mt-6 inline-block rounded-md bg-orange-600 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-500">
                Ver catálogo
            </a>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($categorias as $categoria)
                <a href="{{ route('catalogo.categoria', $categoria) }}" class="rounded-lg border border-stone-200 bg-white p-4 text-center shadow-sm hover:border-orange-500">
                    <span class="font-medium text-stone-800">{{ $categoria->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($destacados->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-stone-900">Destacados con oferta</h2>
                <a href="{{ route('catalogo.ofertas') }}" class="text-sm font-medium text-orange-700 hover:text-orange-800">Ver todas las ofertas</a>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($destacados as $producto)
                    <x-product-card :producto="$producto" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.site>
