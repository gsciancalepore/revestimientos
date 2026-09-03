@props(['categorias' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-stone-50 text-stone-900">
        <header class="bg-white border-b border-stone-200 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('catalogo.home') }}" class="text-2xl font-bold tracking-tight text-stone-900">
                        {{ config('app.name', 'Cerámica') }}
                    </a>

                    <nav class="flex items-center gap-6 text-sm font-medium text-stone-600">
                        <a href="{{ route('catalogo.index') }}" class="hover:text-orange-700">Catálogo</a>
                        <a href="{{ route('catalogo.ofertas') }}" class="hover:text-orange-700">Ofertas</a>
                        <a href="{{ route('carrito.show') }}" class="hover:text-orange-700">Carrito</a>
                    </nav>
                </div>

                <form action="{{ route('catalogo.index') }}" method="get" class="flex gap-2">
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Buscar por nombre, código o marca…"
                        class="flex-1 rounded-md border-stone-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                    >
                    <button type="submit" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">
                        Buscar
                    </button>
                </form>

                @isset($categorias)
                    <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-stone-600">
                        @foreach ($categorias as $categoria)
                            <a href="{{ route('catalogo.categoria', $categoria) }}" class="hover:text-orange-700">{{ $categoria->name }}</a>
                        @endforeach
                    </nav>
                @endisset
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="bg-white border-t border-stone-200 mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-sm text-stone-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Cerámica') }} — Ventas por mayor y menor.
            </div>
        </footer>
    </body>
</html>
