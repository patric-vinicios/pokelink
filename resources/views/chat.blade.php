<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Chat
        </h2>
    </x-slot>

    <div
        x-data
        x-show="! $store.realtime.connected"
        class="mb-4 rounded-md bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800"
    >
        Conexão em tempo real perdida. Reconectando...
    </div>

    <x-card padding="p-0">
        <livewire:chat.index />
    </x-card>
</x-app-layout>
