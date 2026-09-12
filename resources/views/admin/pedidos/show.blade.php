<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pedido #{{ $pedido->id }}</h2>
            <a href="{{ route('pedidos.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Volver al listado</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            @error('pedido')
                <div class="p-4 bg-red-50 text-red-800 rounded-md">{{ $message }}</div>
            @enderror

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-gray-500">Estado:</span>
                    <span class="font-semibold text-gray-900">{{ $pedido->status->label() }}</span>

                    @if ($pedido->necesitaReposicion())
                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800">Reposición pendiente</span>
                    @endif

                    @if ($pedido->tieneIncidenteDePago())
                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-red-100 text-red-800">Incidente de pago</span>
                    @endif
                </div>

                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Cliente</h3>
                        <p class="mt-2 text-sm text-gray-900">{{ $pedido->customer_name }}</p>
                        <p class="text-sm text-gray-500">{{ $pedido->customer_email }}</p>
                        <p class="text-sm text-gray-500">{{ $pedido->customer_phone }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Envío</h3>
                        <p class="mt-2 text-sm text-gray-900">{{ $pedido->shipping_address }}</p>
                        <p class="text-sm text-gray-500">CP {{ $pedido->shipping_cp }}</p>
                        <p class="text-sm text-gray-500">Medio de pago: {{ $pedido->payment_method }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Líneas</h3>

                <table class="mt-4 min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unitario</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($pedido->lines as $linea)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    {{ $linea->product_name }}
                                    <span class="block text-xs text-gray-500">{{ $linea->product_codigo }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $linea->cantidad }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">$ {{ number_format($linea->precio_unitario_cents / 100, 2, ',', '.') }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">$ {{ number_format($linea->subtotal_cents / 100, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <dl class="mt-4 text-sm text-gray-700 space-y-1">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>$ {{ number_format($pedido->subtotal_cents / 100, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt>Envío</dt><dd>$ {{ number_format($pedido->shipping_cost_cents / 100, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between font-semibold text-gray-900"><dt>Total</dt><dd>$ {{ number_format($pedido->total_cents / 100, 2, ',', '.') }}</dd></div>
                </dl>
            </div>

            @canany(['confirmPayment', 'cancel'], $pedido)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap gap-3">
                    {{-- Regla 159: confirmar a mano solo tiene sentido sobre una transferencia impaga. --}}
                    @can('confirmPayment', $pedido)
                        @if ($pedido->payment_method === 'transferencia' && $pedido->status === \App\Enums\OrderStatus::PendingPayment)
                            <form method="POST" action="{{ route('pedidos.confirmar-pago', $pedido) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">
                                    Confirmar pago por transferencia
                                </button>
                            </form>
                        @endif
                    @endcan

                    @can('cancel', $pedido)
                        @if ($pedido->status->puedeTransicionarA(\App\Enums\OrderStatus::Cancelled))
                            <form method="POST" action="{{ route('pedidos.cancelar', $pedido) }}"
                                  onsubmit="return confirm('¿Cancelar el pedido? Si estaba pagado, el stock se restituye.');">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-red-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-600">
                                    Cancelar pedido
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            @endcanany

            {{-- Regla 162: la traza de auditoría es solo para admin. --}}
            @if ($auditoria !== null)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Auditoría</h3>

                    <table class="mt-4 min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuándo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Detalle</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($auditoria as $registro)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $registro->action }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ json_encode($registro->payload, JSON_UNESCAPED_UNICODE) }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $registro->ip_address }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
