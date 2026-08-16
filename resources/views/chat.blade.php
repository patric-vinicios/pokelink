<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Chat
        </h2>
    </x-slot>

    <x-card>
        <x-empty-state message="Em construção.">
            <x-slot name="action">
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Voltar ao início
                </a>
            </x-slot>
        </x-empty-state>
    </x-card>
</x-app-layout>
