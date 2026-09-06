<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Importar tarifas de envío') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600">El archivo representa el snapshot completo de tarifas vigentes: cabecera exacta <code>codigo_postal,precio_envio</code>, CP de 4 dígitos y precio en pesos como entero (0 permitido).</p>

                    <form method="post" action="{{ route('tarifas-envio.import.preview') }}" enctype="multipart/form-data" class="mt-6 space-y-6 max-w-xl">
                        @csrf

                        <div>
                            <x-input-label for="csv" :value="__('Archivo CSV')" />
                            <input id="csv" name="csv" type="file" accept=".csv,.txt" required autofocus class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" />
                            <x-input-error class="mt-2" :messages="$errors->get('csv')" />
                            <x-input-error class="mt-2" :messages="$errors->get('token')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Validar y previsualizar') }}</x-primary-button>

                            <a href="{{ route('tarifas-envio.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
