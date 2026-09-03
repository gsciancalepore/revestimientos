<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nueva tarifa de envío') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="post" action="{{ route('tarifas-envio.store') }}" class="space-y-6 max-w-xl">
                        @csrf

                        <div>
                            <x-input-label for="cp" :value="__('Código postal (4 dígitos)')" />
                            <x-text-input id="cp" name="cp" type="text" maxlength="4" pattern="[0-9]{4}" class="mt-1 block w-full" :value="old('cp')" required autofocus placeholder="ej: 1407" />
                            <x-input-error class="mt-2" :messages="$errors->get('cp')" />
                        </div>

                        <div>
                            <x-input-label for="costo_cents" :value="__('Costo (centavos)')" />
                            <x-text-input id="costo_cents" name="costo_cents" type="number" min="0" step="1" class="mt-1 block w-full" :value="old('costo_cents')" required placeholder="ej: 150000 para $1500,00" />
                            <p class="mt-1 text-sm text-gray-500">En centavos. 0 = envío gratis.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('costo_cents')" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <label for="activo" class="text-sm text-gray-700">Activa</label>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('activo')" />

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Crear tarifa') }}</x-primary-button>

                            <a href="{{ route('tarifas-envio.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
