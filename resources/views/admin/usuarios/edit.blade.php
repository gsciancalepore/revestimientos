<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar usuario') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between max-w-xl">
                        <span class="text-sm text-gray-600">
                            {{ $usuario->is_active ? 'Activo' : 'Desactivado' }}
                            ({{ $usuario->role()->value }})
                        </span>

                        @if (! $usuario->is(auth()->user()))
                            <form method="post" action="{{ route('usuarios.toggle-active', $usuario) }}">
                                @csrf
                                @method('patch')

                                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-red-500">
                                    {{ $usuario->is_active ? 'Desactivar' : 'Reactivar' }}
                                </button>
                            </form>
                        @endif
                    </div>

                    <form method="post" action="{{ route('usuarios.update', $usuario) }}" class="mt-6 space-y-6 max-w-xl">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label for="name" :value="__('Nombre')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $usuario->name)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $usuario->email)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <div>
                            <x-input-label for="role" :value="__('Rol')" />
                            <select id="role" name="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(old('role', $usuario->role()->value) === $role->value)>
                                        {{ $role->value }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('role')" />
                        </div>

                        <div>
                            <x-input-label for="password" :value="__('Resetear contraseña')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <p class="mt-1 text-sm text-gray-500">Dejar vacío para conservar la contraseña actual.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" :value="__('Confirmar contraseña nueva')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Guardar') }}</x-primary-button>

                            <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
