<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nuevo producto') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="post" action="{{ route('productos.store') }}" class="space-y-6 max-w-xl">
                        @csrf

                        <div>
                            <x-input-label for="codigo" :value="__('Código (SKU)')" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo')" placeholder="ILV-12345" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('codigo')" />
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Nombre')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="slug" :value="__('Slug (opcional)')" />
                            <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug')" placeholder="ceramico-60x60-beige" />
                            <p class="mt-1 text-sm text-gray-500">Si se deja vacío se genera automáticamente desde el nombre. Debe ser único en todo el catálogo.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('slug')" />
                        </div>

                        <div>
                            <x-input-label for="category_id" :value="__('Categoría')" />
                            <select id="category_id" name="category_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="">— Seleccionar —</option>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}" @selected(old('category_id') == $categoria->id)>{{ $categoria->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="marca" :value="__('Marca')" />
                                <x-text-input id="marca" name="marca" type="text" class="mt-1 block w-full" :value="old('marca')" />
                                <x-input-error class="mt-2" :messages="$errors->get('marca')" />
                            </div>

                            <div>
                                <x-input-label for="unidad_venta" :value="__('Modo de venta')" />
                                <select id="unidad_venta" name="unidad_venta" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    @foreach ($unidadesVenta as $unidad)
                                        <option value="{{ $unidad->value }}" @selected(old('unidad_venta', 'm2') == $unidad->value)>{{ $unidad->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('unidad_venta')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="precio_cents" :value="__('Precio (en centavos)')" />
                            <x-text-input id="precio_cents" name="precio_cents" type="number" min="0" class="mt-1 block w-full" :value="old('precio_cents')" required />
                            <p class="mt-1 text-sm text-gray-500">Por m² o por bolsa/pieza según el modo de venta.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('precio_cents')" />
                        </div>

                        <div>
                            <x-input-label for="m2_por_caja" :value="__('m² por caja (solo modo por m²)')" />
                            <x-text-input id="m2_por_caja" name="m2_por_caja" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('m2_por_caja')" />
                            <x-input-error class="mt-2" :messages="$errors->get('m2_por_caja')" />
                        </div>

                        <div>
                            <x-input-label for="stock" :value="__('Stock (cajas o unidades)')" />
                            <x-text-input id="stock" name="stock" type="number" min="0" class="mt-1 block w-full" :value="old('stock', 0)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('stock')" />
                        </div>

                        <div>
                            <x-input-label for="descripcion" :value="__('Descripción')" />
                            <textarea id="descripcion" name="descripcion" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('descripcion') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('descripcion')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('activo', true))>
                                <span class="ms-2 text-sm text-gray-700">{{ __('Producto activo') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Crear producto') }}</x-primary-button>

                            <a href="{{ route('productos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
