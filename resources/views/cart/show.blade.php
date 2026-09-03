<x-slot:title>Carrito — {{ config('app.name', 'Cerámica') }}</x-slot:title>

<x-layouts.site :categorias="$categorias">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="text-2xl font-bold text-stone-900">Carrito</h1>

        @if (session('status'))
            <div class="mt-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($isEmpty)
            <div class="mt-8 rounded-lg border border-stone-200 bg-white p-8 text-center">
                <p class="text-stone-600">Tu carrito está vacío.</p>
                <a href="{{ route('catalogo.index') }}" class="mt-4 inline-block rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Ver catálogo</a>
            </div>
        @else
            @if ($hasUnpurchasable)
                <div class="mt-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    Algunos productos ya no están disponibles o no tienen stock suficiente. Actualiza o elimina esas líneas para continuar.
                </div>
            @endif

            <div class="mt-6 space-y-4">
                @foreach ($lines as $line)
                    <x-cart-line :line="$line" />
                @endforeach
            </div>

            <div class="mt-8 flex justify-end border-t border-stone-200 pt-6">
                <div class="text-right">
                    <p class="text-sm text-stone-500">Subtotal</p>
                    <p class="text-2xl font-bold text-stone-900">${{ number_format($subtotal / 100, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <form action="{{ route('carrito.clear') }}" method="post">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-stone-500 hover:text-red-600">Vaciar carrito</button>
                </form>
            </div>
        @endif

        <div class="mt-8 rounded-lg border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-stone-700">Calcular envío</h2>
            <form method="get" action="{{ route('carrito.show') }}" class="mt-3 flex gap-2">
                <input type="text" name="cp" value="{{ $cp ?? '' }}" maxlength="4" pattern="[0-9]{4}" placeholder="CP (4 dígitos)" class="w-32 rounded-md border-stone-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500" />
                <button type="submit" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Calcular</button>
            </form>
            @isset($shippingError)
                <p class="mt-2 text-sm text-red-600">{{ $shippingError }}</p>
            @endisset
            @isset($shippingQuote)
                @if ($shippingQuote->disponible)
                    <p class="mt-2 text-sm text-stone-700">Envío: ${{ number_format($shippingQuote->costoCents / 100, 2, ',', '.') }}</p>
                    @isset($total)
                        <p class="mt-1 text-lg font-bold text-stone-900">Total: ${{ number_format($total / 100, 2, ',', '.') }}</p>
                    @endisset
                @else
                    <p class="mt-2 text-sm text-amber-700">Envío no disponible para este CP</p>
                @endif
            @endisset
        </div>
    </div>
</x-layouts.site>
