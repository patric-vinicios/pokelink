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

<div class="flex flex-col sm:flex-row gap-4 h-[36rem]">
    <div class="sm:w-80 shrink-0 border border-gray-200 rounded-lg overflow-hidden flex flex-col">
        <livewire:chat.user-list :selected-user-id="$selectedUserId" />
    </div>

    <div class="flex-1 border border-gray-200 rounded-lg overflow-hidden flex flex-col min-h-0">
        @if ($selectedUserId)
            <livewire:chat.conversation :other-user-id="$selectedUserId" :key="$selectedUserId" />
        @else
            <x-empty-state message="Selecione uma conversa para começar." class="h-full justify-center" />
        @endif
    </div>
</div>
