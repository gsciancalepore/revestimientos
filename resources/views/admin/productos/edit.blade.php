<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar producto') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="post" action="{{ route('productos.update', $producto) }}" class="space-y-6 max-w-xl">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label for="codigo" :value="__('Código (SKU)')" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo', $producto->codigo)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('codigo')" />
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Nombre')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $producto->name)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="category_id" :value="__('Categoría')" />
                            <select id="category_id" name="category_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}" @selected(old('category_id', $producto->category_id) == $categoria->id)>{{ $categoria->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="marca" :value="__('Marca')" />
                                <x-text-input id="marca" name="marca" type="text" class="mt-1 block w-full" :value="old('marca', $producto->marca)" />
                                <x-input-error class="mt-2" :messages="$errors->get('marca')" />
                            </div>

                            <div>
                                <x-input-label for="unidad_venta" :value="__('Modo de venta')" />
                                <select id="unidad_venta" name="unidad_venta" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    @foreach ($unidadesVenta as $unidad)
                                        <option value="{{ $unidad->value }}" @selected(old('unidad_venta', $producto->unidad_venta->value) == $unidad->value)>{{ $unidad->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('unidad_venta')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="precio_cents" :value="__('Precio (en centavos)')" />
                            <x-text-input id="precio_cents" name="precio_cents" type="number" min="0" class="mt-1 block w-full" :value="old('precio_cents', $producto->precio_cents)" required />
                            <p class="mt-1 text-sm text-gray-500">Por m² o por bolsa/pieza según el modo de venta.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('precio_cents')" />
                        </div>

                        <div>
                            <x-input-label for="m2_por_caja" :value="__('m² por caja (solo modo por m²)')" />
                            <x-text-input id="m2_por_caja" name="m2_por_caja" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('m2_por_caja', $producto->m2_por_caja)" />
                            <x-input-error class="mt-2" :messages="$errors->get('m2_por_caja')" />
                        </div>

                        <div>
                            <x-input-label for="stock" :value="__('Stock (cajas o unidades)')" />
                            <x-text-input id="stock" name="stock" type="number" min="0" class="mt-1 block w-full" :value="old('stock', $producto->stock)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('stock')" />
                        </div>

                        <div>
                            <x-input-label for="descripcion" :value="__('Descripción')" />
                            <textarea id="descripcion" name="descripcion" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('descripcion', $producto->descripcion) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('descripcion')" />
                        </div>

                        @if ($clavesSpecs !== [])
                            <div>
                                <x-input-label :value="__('Atributos de la familia')" />
                                <p class="mt-1 text-sm text-gray-500">Claves permitidas para la categoría: {{ implode(', ', $clavesSpecs) }}.</p>
                                <div class="mt-3 space-y-3">
                                    @foreach ($clavesSpecs as $clave)
                                        <div class="flex items-center gap-3">
                                            <span class="w-40 text-sm text-gray-700">{{ $clave }}</span>
                                            <x-text-input name="specs[{{ $clave }}]" type="text" class="flex-1" :value="old('specs.'.$clave, $producto->specs[$clave] ?? null)" />
                                        </div>
                                    @endforeach
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('specs')" />
                            </div>
                        @endif

                        <div class="flex items-center gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('activo', $producto->activo))>
                                <span class="ms-2 text-sm text-gray-700">{{ __('Producto activo') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Guardar') }}</x-primary-button>

                            <a href="{{ route('productos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
