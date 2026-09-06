<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Confirmar importación de tarifas') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div class="p-4 bg-gray-50 rounded-md">
                            <dt class="text-xs font-medium text-gray-500 uppercase">Total filas</dt>
                            <dd class="mt-1 text-2xl font-semibold">{{ $total }}</dd>
                        </div>
                        <div class="p-4 bg-green-50 rounded-md">
                            <dt class="text-xs font-medium text-green-700 uppercase">Nuevas</dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-800">{{ $nuevas }}</dd>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-md">
                            <dt class="text-xs font-medium text-blue-700 uppercase">A actualizar</dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-800">{{ $aActualizar }}</dd>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-md">
                            <dt class="text-xs font-medium text-gray-500 uppercase">Sin cambios</dt>
                            <dd class="mt-1 text-2xl font-semibold">{{ $sinCambios }}</dd>
                        </div>
                        <div class="p-4 bg-yellow-50 rounded-md">
                            <dt class="text-xs font-medium text-yellow-700 uppercase">A desactivar</dt>
                            <dd class="mt-1 text-2xl font-semibold text-yellow-800">{{ $aDesactivar }}</dd>
                        </div>
                    </dl>

                    <h3 class="mt-8 font-semibold">Primeras filas</h3>

                    <table class="mt-2 min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CP</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio (pesos)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($muestra as $fila)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $fila['cp'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$ {{ number_format($fila['precio'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-6 flex items-center gap-4">
                        <form method="post" action="{{ route('tarifas-envio.import.confirm') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}" />

                            <x-primary-button>{{ __('Confirmar importación') }}</x-primary-button>
                        </form>

                        <form method="post" action="{{ route('tarifas-envio.import.cancel') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}" />

                            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancelar') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
