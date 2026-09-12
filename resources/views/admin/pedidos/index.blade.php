<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pedidos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="GET" action="{{ route('pedidos.index') }}" class="mb-6 flex flex-wrap gap-3 items-end">
                        <div>
                            <label for="estado" class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</label>
                            <select name="estado" id="estado" class="mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Todos</option>
                                @foreach ($estados as $estado)
                                    <option value="{{ $estado->value }}" @selected($estadoActual === $estado)>{{ $estado->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="q" class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Email o N.º</label>
                            <input type="text" name="q" id="q" value="{{ $busqueda }}" class="mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                        </div>

                        <button type="submit" class="px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Filtrar</button>
                    </form>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N.º</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio de pago</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($pedidos as $pedido)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $pedido->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $pedido->customer_name }}
                                        <span class="block text-xs text-gray-500">{{ $pedido->customer_email }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$ {{ number_format($pedido->total_cents / 100, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pedido->payment_method }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="text-gray-900">{{ $pedido->status->label() }}</span>

                                        {{-- Regla 161: destacados derivados del estado, sin columna nueva. --}}
                                        @if ($pedido->necesitaReposicion())
                                            <span class="block mt-1 px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800">Reposición pendiente</span>
                                        @endif

                                        @if ($pedido->tieneIncidenteDePago())
                                            <span class="block mt-1 px-2 py-0.5 text-xs font-semibold rounded bg-red-100 text-red-800">Incidente de pago</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('pedidos.show', $pedido) }}" class="text-indigo-600 hover:text-indigo-900">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-6 text-center text-sm text-gray-500">No hay pedidos que coincidan.</td>
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
