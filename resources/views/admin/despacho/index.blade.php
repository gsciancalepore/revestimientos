<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Despacho') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            @error('pedido')
                <div class="mb-4 p-4 bg-red-50 text-red-800 rounded-md">{{ $message }}</div>
            @enderror

            <div class="mb-4 flex gap-2">
                <a href="{{ route('despacho.index', ['solapa' => 'preparar']) }}"
                   class="px-4 py-2 rounded-md text-sm font-semibold {{ $solapa === 'preparar' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 border border-gray-300' }}">
                    Por preparar
                </a>
                <a href="{{ route('despacho.index', ['solapa' => 'despachados']) }}"
                   class="px-4 py-2 rounded-md text-sm font-semibold {{ $solapa === 'despachados' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 border border-gray-300' }}">
                    Despachados
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{-- Sin importes: el depósito arma envíos, no necesita ver plata (regla 163). --}}
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N.º</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destino</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Productos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($pedidos as $pedido)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $pedido->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $pedido->shipping_address }}
                                        <span class="block text-xs text-gray-500">CP {{ $pedido->shipping_cp }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <ul class="list-disc list-inside">
                                            @foreach ($pedido->lines as $linea)
                                                <li>{{ $linea->cantidad }} × {{ $linea->product_name }} <span class="text-xs text-gray-500">({{ $linea->product_codigo }})</span></li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <form method="POST" action="{{ $solapa === 'preparar' ? route('despacho.despachar', $pedido) : route('despacho.entregar', $pedido) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                                {{ $solapa === 'preparar' ? 'Marcar despachado' : 'Marcar entregado' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">
                                        {{ $solapa === 'preparar' ? 'No hay pedidos por preparar.' : 'No hay pedidos despachados.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-4">{{ $pedidos->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
