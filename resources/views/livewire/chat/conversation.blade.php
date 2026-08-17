<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public int $otherUserId;

    public int $authUserId;

    public User $otherUser;

    // 0, not null: Livewire's dynamic #[On('echo-private:conversation.{conversationId},...')]
    // placeholder resolver throws when the referenced property is null, so a
    // real (nonzero) auto-increment id is the only safe "no conversation
    // yet" sentinel — Echo simply subscribes to a channel nothing publishes to.
    public int $conversationId = 0;

    /** @var array<int, array{id: int, sender_id: int, sender_name: string, body: string, created_at: string, mine: bool}> */
    public array $messages = [];

    public string $body = '';

    public bool $hasMoreHistory = false;

    public function mount(int $otherUserId): void
    {
        $this->otherUserId = $otherUserId;
        $this->authUserId = auth()->id();
        $this->otherUser = User::findOrFail($otherUserId);

        $conversation = Conversation::findBetween(auth()->id(), $otherUserId);

        if (! $conversation) {
            return;
        }

        $this->conversationId = $conversation->id;

        $this->markVisibleMessagesRead($conversation);

        $page = $conversation->messages()
            ->latest('id')
            ->limit(config('chat.history_page_size'))
            ->get()
            ->reverse()
            ->values();

        $this->hasMoreHistory = $page->isNotEmpty()
            && $conversation->messages()->where('id', '<', $page->first()->id)->exists();

        $this->messages = $page->map($this->presentMessage(...))->all();
    }

    public function send(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:'.config('chat.max_message_length')],
        ]);

        $message = DB::transaction(function () {
            $conversation = Conversation::betweenUsers(auth()->user(), $this->otherUser);

            Gate::authorize('view', $conversation);

            $message = $conversation->messages()->create([
                'sender_id' => auth()->id(),
                'body' => $this->body,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            $this->conversationId = $conversation->id;

            return $message;
        });

        $this->messages[] = $this->presentMessage($message);
        $this->body = '';
        $this->dispatch('message-appended');

        broadcast(new MessageSent($message))->toOthers();
    }

    public function loadOlder(): void
    {
        if (! $this->conversationId || ! $this->hasMoreHistory) {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        Gate::authorize('view', $conversation);

        $oldestLoadedId = $this->messages[0]['id'];

        $page = $conversation->messages()
            ->where('id', '<', $oldestLoadedId)
            ->latest('id')
            ->limit(config('chat.history_page_size'))
            ->get()
            ->reverse()
            ->values();

        $this->hasMoreHistory = $page->isNotEmpty()
            && $conversation->messages()->where('id', '<', $page->first()->id)->exists();

        $this->messages = $page->map($this->presentMessage(...))->concat($this->messages)->all();
    }

    /**
     * Livewire recomputes this dynamic channel name on every render, so once
     * send() sets $conversationId for a brand-new conversation the client
     * re-subscribes to the real channel without a manual page reload.
     *
     * @param  array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string, created_at: string}  $event
     */
    #[On('echo-private:conversation.{conversationId},.message.sent')]
    public function messageReceived(array $event): void
    {
        $this->messages[] = [
            'id' => $event['id'],
            'sender_id' => $event['sender_id'],
            'sender_name' => $event['sender_name'],
            'body' => $event['body'],
            'created_at' => $event['created_at'],
            'mine' => false,
        ];

        $conversation = Conversation::find($this->conversationId);

        if ($conversation) {
            $this->markVisibleMessagesRead($conversation);
        }

        $this->dispatch('message-appended');
    }

    /**
     * Fallback for the conversation-doesn't-exist-yet case: with
     * $conversationId still 0 when this component mounted, the listener
     * above subscribed to conversation.0 — a channel nothing publishes to
     * — and nothing ever re-renders this component (and so recomputes
     * that binding) for a recipient who never calls send() themselves.
     * The personal channel always exists and always delivers, so it
     * catches this one first message and adopts the real conversation id;
     * every message after this arrives through the now-correctly-bound
     * conversation.{conversationId} listener instead, and the guard below
     * stops this method from double-handling those.
     *
     * @param  array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string, created_at: string}  $event
     */
    #[On('echo-private:App.Models.User.{authUserId},.message.sent')]
    public function firstMessageReceived(array $event): void
    {
        if ($this->conversationId !== 0 || $event['sender_id'] !== $this->otherUserId) {
            return;
        }

        $this->conversationId = $event['conversation_id'];

        $this->messageReceived($event);
    }

    private function markVisibleMessagesRead(Conversation $conversation): void
    {
        $conversation->messages()->unreadFor(auth()->id())->update(['read_at' => now()]);
    }

    /**
     * @return array{id: int, sender_id: int, sender_name: string, body: string, created_at: string, mine: bool}
     */
    private function presentMessage(Message $message): array
    {
        $mine = $message->sender_id === auth()->id();

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $mine ? auth()->user()->name : $this->otherUser->name,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'mine' => $mine,
        ];
    }
}; ?>

<div
    class="flex flex-col h-full"
    x-data="{
        loadingOlder: false,
        scrollToBottom() {
            $refs.thread.scrollTop = $refs.thread.scrollHeight;
        },
        loadOlder() {
            this.loadingOlder = true;
            const before = $refs.thread.scrollHeight;
            $wire.loadOlder().then(() => {
                $nextTick(() => {
                    $refs.thread.scrollTop = $refs.thread.scrollHeight - before;
                    this.loadingOlder = false;
                });
            });
        },
        onScroll() {
            if (! this.loadingOlder && $refs.thread.scrollTop < 80) {
                this.loadOlder();
            }
        },
    }"
    x-init="scrollToBottom()"
    x-on:message-appended.window="$nextTick(() => scrollToBottom())"
>
    <div class="px-4 py-3 border-b border-gray-200">
        <p class="font-medium text-gray-900">{{ $otherUser->name }}</p>
    </div>

    <div x-ref="thread" x-on:scroll="onScroll" class="flex-1 overflow-y-auto px-4 py-3 space-y-2">
        @if ($hasMoreHistory)
            <div class="text-center">
                <button type="button" x-on:click="loadOlder()" x-bind:disabled="loadingOlder" class="text-xs text-indigo-600 hover:text-indigo-500">
                    Carregar mensagens anteriores
                </button>
            </div>
        @endif

        @forelse ($messages as $message)
            <div wire:key="chat-message-{{ $message['id'] }}" class="flex {{ $message['mine'] ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%] rounded-lg px-3 py-2 {{ $message['mine'] ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                    <p class="text-sm whitespace-pre-wrap break-words">{{ $message['body'] }}</p>
                    <p class="mt-1 text-[10px] {{ $message['mine'] ? 'text-indigo-100' : 'text-gray-400' }}">
                        {{ \Illuminate\Support\Carbon::parse($message['created_at'])->format('H:i') }}
                    </p>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 text-center py-6">Nenhuma mensagem ainda. Diga olá!</p>
        @endforelse
    </div>

    <form
        wire:submit.prevent="send"
        x-data="{ liveBody: @entangle('body') }"
        class="border-t border-gray-200 p-3"
    >
        <textarea
            wire:model="body"
            wire:keydown.enter.prevent="send"
            maxlength="{{ config('chat.max_message_length') }}"
            rows="2"
            placeholder="Escreva uma mensagem..."
            class="w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none"
        ></textarea>

        <div class="flex items-center justify-between mt-2">
            <p
                class="text-xs text-gray-400"
                x-show="liveBody.length >= 1800"
            >
                <span x-text="liveBody.length"></span>/{{ config('chat.max_message_length') }} caracteres.
                <span x-show="liveBody.length > {{ config('chat.max_message_length') }}" class="text-red-600">
                    Máximo de {{ config('chat.max_message_length') }} caracteres.
                </span>
            </p>

            <x-primary-button
                type="submit"
                x-bind:disabled="liveBody.length === 0 || liveBody.length > {{ config('chat.max_message_length') }}"
                wire:loading.attr="disabled"
                wire:target="send"
            >
                <span wire:loading.remove wire:target="send">Enviar</span>
                <span wire:loading wire:target="send">Enviando...</span>
            </x-primary-button>
        </div>

        <x-input-error :messages="$errors->get('body')" class="mt-2" />
    </form>
</div>
