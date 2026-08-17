<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=nunito:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <!-- Global loading indicator: any Livewire round-trip past 200ms (F04) -->
        <div wire:loading.delay class="pokelink-loading-bar"></div>

        <div class="pokelink-app-shell">
            <livewire:layout.navigation />

            <div class="pokelink-stage">
                @if (isset($header))
                    <header class="pokelink-page-header">
                        <div class="mx-auto w-full max-w-7xl px-5 py-5 sm:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main class="pokelink-main {{ request()->routeIs('dashboard', 'favoritos', 'chat', 'perfil') ? 'pokelink-main--catalog' : 'pokelink-main--default' }}">
                    {{ $slot }}
                </main>

                <footer class="pokelink-footer">
                    PokéLink v{{ config('app.version') }}
                </footer>
            </div>
        </div>

        <x-toast />
    </body>
</html>
