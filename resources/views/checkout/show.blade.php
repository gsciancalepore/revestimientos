<x-slot:title>Checkout — {{ config('app.name', 'Cerámica') }}</x-slot:title>

<x-layouts.site :categorias="$categorias">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="text-2xl font-bold text-stone-900">Checkout</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="rounded-lg border border-stone-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-stone-900">Resumen</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($lines as $line)
                            <div class="flex justify-between text-sm">
                                <span class="text-stone-600">{{ $line['product']->name }} × {{ $line['cantidad'] }}</span>
                                <span class="font-medium">${{ number_format($line['subtotal'] / 100, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 border-t border-stone-200 pt-4 text-right">
                        <p class="text-sm text-stone-500">Subtotal</p>
                        <p class="text-xl font-bold text-stone-900">${{ number_format($subtotal / 100, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <form method="POST" action="{{ route('checkout.store') }}" class="rounded-lg border border-stone-200 bg-white p-6 space-y-4">
                    @csrf

                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-stone-700">Nombre</label>
                        <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" required class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    </div>

                    <div>
                        <label for="customer_email" class="block text-sm font-medium text-stone-700">Email</label>
                        <input type="email" name="customer_email" id="customer_email" value="{{ old('customer_email') }}" required class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    </div>

                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-stone-700">Teléfono</label>
                        <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" required class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    </div>

                    <div>
                        <label for="shipping_cp" class="block text-sm font-medium text-stone-700">Código postal (4 dígitos)</label>
                        <input type="text" name="shipping_cp" id="shipping_cp" value="{{ old('shipping_cp') }}" required maxlength="4" pattern="[0-9]{4}" class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    </div>

                    <div>
                        <label for="shipping_address" class="block text-sm font-medium text-stone-700">Dirección (opcional)</label>
                        <input type="text" name="shipping_address" id="shipping_address" value="{{ old('shipping_address') }}" class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-stone-700">Medio de pago</span>
                        <div class="mt-2 space-y-2">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="payment_method" value="transferencia" {{ old('payment_method', 'transferencia') === 'transferencia' ? 'checked' : '' }} class="text-orange-600">
                                <span class="text-sm">Transferencia</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="payment_method" value="mercadopago" {{ old('payment_method') === 'mercadopago' ? 'checked' : '' }} class="text-orange-600">
                                <span class="text-sm">MercadoPago</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Confirmar pedido</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.site>
