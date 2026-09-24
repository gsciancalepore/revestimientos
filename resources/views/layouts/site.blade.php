@props(['categorias' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|fraunces:500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-neutral-50 text-neutral-900">
        <header class="bg-neutral-50/95 backdrop-blur border-b border-neutral-200 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('catalogo.home') }}" class="font-display text-2xl font-semibold tracking-tight text-brand-800">
                        {{ config('app.name', 'Cerámica') }}
                    </a>

                    <nav class="flex items-center gap-6 text-sm font-medium text-neutral-600">
                        <a href="{{ route('catalogo.index') }}" class="hover:text-brand-700">Catálogo</a>
                        <a href="{{ route('catalogo.ofertas') }}" class="hover:text-brand-700">Ofertas</a>
                        <a href="{{ route('carrito.show') }}" class="hover:text-brand-700">Carrito</a>
                    </nav>
                </div>

                <form action="{{ route('catalogo.index') }}" method="get" class="flex gap-2">
                    <label for="buscador-catalogo" class="sr-only">Buscar productos</label>
                    <input
                        id="buscador-catalogo"
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Buscar por nombre, código o marca…"
                        class="flex-1 rounded-md border-neutral-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                    <button type="submit" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
                        Buscar
                    </button>
                </form>

                @isset($categorias)
                    <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-neutral-600">
                        @foreach ($categorias as $categoria)
                            <a href="{{ route('catalogo.categoria', $categoria) }}" class="hover:text-brand-700">{{ $categoria->name }}</a>
                        @endforeach
                    </nav>
                @endisset
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="bg-white border-t border-neutral-200 mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-sm text-neutral-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Cerámica') }} — Ventas por mayor y menor.
            </div>
        </footer>
    </body>
</html>
