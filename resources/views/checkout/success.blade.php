<x-slot:title>Pedido confirmado — {{ config('app.name', 'Cerámica') }}</x-slot:title>

<x-layouts.site :categorias="$categorias">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-6 text-center">
            <h1 class="text-2xl font-bold text-emerald-900">¡Pedido confirmado!</h1>
            <p class="mt-2 text-sm text-emerald-800">Tu pedido #{{ $order->id }} está pendiente de pago.</p>
        </div>

        <div class="mt-8 rounded-lg border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-stone-900">Detalle del pedido</h2>
            <div class="mt-4 space-y-2 text-sm">
                <p><span class="font-medium">Cliente:</span> {{ $order->customer_name }} — {{ $order->customer_email }} — {{ $order->customer_phone }}</p>
                <p><span class="font-medium">Envío:</span> CP {{ $order->shipping_cp }} @if($order->shipping_address) — {{ $order->shipping_address }} @endif</p>
                <p><span class="font-medium">Estado:</span> {{ $order->status->label() }}</p>
                <p><span class="font-medium">Pago:</span> {{ $order->payment_method }}</p>
            </div>

            <div class="mt-6 space-y-3">
                @foreach ($lines as $line)
                    <div class="flex justify-between text-sm">
                        <span class="text-stone-600">{{ $line->product_name }} × {{ $line->cantidad }}</span>
                        <span class="font-medium">${{ number_format($line->subtotal_cents / 100, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 border-t border-stone-200 pt-4 space-y-1 text-right">
                <p class="text-sm text-stone-500">Subtotal: ${{ number_format($order->subtotal_cents / 100, 2, ',', '.') }}</p>
                <p class="text-sm text-stone-500">Envío: ${{ number_format($order->shipping_cost_cents / 100, 2, ',', '.') }}</p>
                <p class="text-xl font-bold text-stone-900">Total: ${{ number_format($order->total_cents / 100, 2, ',', '.') }}</p>
            </div>

            @if($order->payment_method === 'transferencia')
                <p class="mt-4 text-sm text-stone-600">Te enviaremos por email las instrucciones para realizar la transferencia.</p>
            @else
                <p class="mt-4 text-sm text-stone-600">Serás redirigido a MercadoPago para completar el pago.</p>
            @endif

            @if($order->payment_method === 'mercadopago' && $order->mp_init_point)
                <a href="{{ $order->mp_init_point }}" class="mt-4 inline-block rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-600">Continuar al pago en MercadoPago</a>
            @endif

            @if(session('payment_error'))
                <p class="mt-4 text-sm font-medium text-red-700">{{ session('payment_error') }}</p>
                @if($order->payment_method === 'mercadopago')
                    <form method="POST" action="{{ route('checkout.mercadopago.retry') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Reintentar pago</button>
                    </form>
                @endif
            @endif
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('catalogo.index') }}" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Seguir comprando</a>
        </div>
    </div>
</x-layouts.site>
