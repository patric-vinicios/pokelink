<?php

use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $selectedUserId = null;

    #[On('conversation-selected')]
    public function selectConversation(int $userId): void
    {
        $this->selectedUserId = $userId;
    }
}; ?>

<div class="chat-layout">
    <aside class="chat-directory">
        <livewire:chat.user-list :selected-user-id="$selectedUserId" />
    </aside>

    <section class="chat-thread-panel">
        @if ($selectedUserId)
            <livewire:chat.conversation :other-user-id="$selectedUserId" :key="$selectedUserId" />
        @else
            <div class="chat-welcome-state">
                <div class="chat-welcome-pokeball" aria-hidden="true"><span></span></div>
                <span class="trainer-page-eyebrow">Nova conversa</span>
                <h2>Selecione um treinador</h2>
                <p>Escolha alguém na lista para começar uma conversa.</p>
                <span class="chat-welcome-tip">As mensagens aparecem aqui em tempo real.</span>
            </div>
        @endif
    </section>
</div>
