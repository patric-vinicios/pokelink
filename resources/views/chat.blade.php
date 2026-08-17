<x-app-layout>
    <x-pokedex.hero
        class="chat-page"
        eyebrow="Conexões em tempo real"
        :inline-title="true"
    >
        <x-slot name="headline">
            Converse com seus<br>
            Pokémon <span>favoritos</span>
        </x-slot>

        <x-slot name="copy">
            Acompanhe mensagens, equipes e interações em tempo real.
        </x-slot>

        <div class="chat-page-content">
            <div
                x-data
                x-cloak
                x-show="! $store.realtime.connected"
                class="chat-connection-alert"
                role="status"
            >
                <span class="chat-connection-alert-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.8 2.4 17.5A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.5L13.7 3.8a2 2 0 0 0-3.4 0Z" />
                    </svg>
                </span>
                <div>
                    <strong>Conexão em tempo real perdida.</strong>
                    <span>Suas mensagens continuam salvas. Reconectando...</span>
                </div>
            </div>

            <section class="trainer-panel trainer-chat-panel" aria-label="Conversas">
                <livewire:chat.index />
            </section>
        </div>
    </x-pokedex.hero>
</x-app-layout>
