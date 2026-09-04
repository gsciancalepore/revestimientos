<aside class="w-64 shrink-0 bg-gray-800 text-gray-200 flex flex-col">
    <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-700">
        <div class="shrink-0 flex items-center">
            <a href="{{ route('dashboard') }}">
                <x-application-logo class="block h-9 w-auto fill-current text-white" />
            </a>
        </div>
    </div>

    <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ request()->routeIs('dashboard') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10" />
            </svg>
            {{ __('Dashboard') }}
        </a>

        @if (Auth::user()->hasRole('admin'))
            <a href="{{ route('usuarios.index') }}"
                class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ request()->routeIs('usuarios.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                {{ __('Usuarios') }}
            </a>

            <a href="{{ route('categorias.index') }}"
                class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ request()->routeIs('categorias.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                {{ __('Categorías') }}
            </a>

            <a href="{{ route('productos.index') }}"
                class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ request()->routeIs('productos.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M9 12h6" />
                </svg>
                {{ __('Productos') }}
            </a>

            <a href="{{ route('tarifas-envio.index') }}"
                class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ request()->routeIs('tarifas-envio.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM21 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                {{ __('Tarifas de envío') }}
            </a>
        @endif

        <div class="pt-4 mt-4 border-t border-gray-700">
            <span class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Próximamente') }}</span>
        </div>

        <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-500 rounded-md cursor-not-allowed" aria-disabled="true" tabindex="-1">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 4.6A1 1 0 005.7 19H19M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
            {{ __('Pedidos') }}
        </a>

        <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-500 rounded-md cursor-not-allowed" aria-disabled="true" tabindex="-1">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.4-4 8-9 8-1.2 0-2.3-.2-3.4-.6L3 21l1.6-3.6C3.6 15.9 3 14.1 3 12c0-4.4 4-8 9-8s9 3.6 9 8z" />
            </svg>
            {{ __('Ventas WhatsApp') }}
        </a>
    </nav>

    <div class="px-4 py-4 border-t border-gray-700">
        <div class="px-3">
            <div class="text-sm font-medium text-white">{{ Auth::user()->name }}</div>
            <div class="text-xs text-gray-400">{{ Auth::user()->email }}</div>
        </div>

        <div class="mt-3 space-y-1">
            <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition duration-150 ease-in-out">
                {{ __('Mi perfil') }}
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="w-full text-start px-3 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white transition duration-150 ease-in-out">
                    {{ __('Cerrar sesión') }}
                </button>
            </form>
        </div>
    </div>
</aside>
