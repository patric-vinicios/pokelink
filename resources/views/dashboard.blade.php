<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Início
        </h2>
    </x-slot>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <x-card>
        <p class="text-gray-900">
            A busca de Pokémon será exibida aqui em breve.
        </p>
    </x-card>
</x-app-layout>
