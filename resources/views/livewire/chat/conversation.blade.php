<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pokemon;
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

    public ?string $otherUserAvatarUrl = null;

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
        $this->otherUserAvatarUrl = $this->otherUser->favorites()
            ->orderByDesc('favorites.created_at')
            ->value('pokemon.sprite_url')
            ?? Pokemon::query()->whereKey($otherUserId)->value('sprite_url');

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
    class="chat-conversation"
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
    <header class="chat-thread-header">
        <span class="chat-thread-avatar" aria-hidden="true">
            @if ($otherUserAvatarUrl)
                <img src="{{ $otherUserAvatarUrl }}" alt="" />
            @else
                {{ mb_strtoupper(mb_substr($otherUser->name, 0, 1)) }}
            @endif
        </span>
        <div class="chat-thread-person">
            <h2>{{ $otherUser->name }}</h2>
            <small
                x-data
                :class="$store.presence?.onlineIds?.has({{ $otherUser->id }}) ? 'is-online' : ''"
            >
                <i aria-hidden="true"></i>
                <span x-text="$store.presence?.onlineIds?.has({{ $otherUser->id }}) ? 'Online agora' : 'Offline'"></span>
            </small>
        </div>
        <div class="chat-thread-actions" aria-label="Ações da conversa">
            <button type="button" disabled aria-label="Chamada de voz indisponível" title="Chamada de voz em breve">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 16.9v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.2 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.68 2.8a2 2 0 0 1-.45 2.11L8.07 9.9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.32 1.84.55 2.8.68A2 2 0 0 1 22 16.9Z" />
                </svg>
            </button>
            <button type="button" disabled aria-label="Chamada de vídeo indisponível" title="Chamada de vídeo em breve">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="3" y="5" width="13" height="14" rx="3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16 10 5-3v10l-5-3" />
                </svg>
            </button>
            <button type="button" disabled aria-label="Mais opções indisponíveis" title="Mais opções em breve">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="12" cy="5" r="1.8" />
                    <circle cx="12" cy="12" r="1.8" />
                    <circle cx="12" cy="19" r="1.8" />
                </svg>
            </button>
        </div>
    </header>

    <div x-ref="thread" x-on:scroll="onScroll" class="chat-messages">
        <div class="chat-messages-day" aria-hidden="true"><span>Hoje</span></div>

        @if ($hasMoreHistory)
            <div class="chat-load-older">
                <button type="button" x-on:click="loadOlder()" x-bind:disabled="loadingOlder">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5-5 5 5" />
                    </svg>
                    <span x-text="loadingOlder ? 'Carregando...' : 'Carregar mensagens anteriores'"></span>
                </button>
            </div>
        @endif

        @forelse ($messages as $message)
            <div wire:key="chat-message-{{ $message['id'] }}" class="chat-message-row {{ $message['mine'] ? 'is-mine' : 'is-theirs' }}">
                @if (! $message['mine'])
                    <span class="chat-message-avatar" aria-hidden="true">
                        @if ($otherUserAvatarUrl)
                            <img src="{{ $otherUserAvatarUrl }}" alt="" />
                        @else
                            {{ mb_strtoupper(mb_substr($otherUser->name, 0, 1)) }}
                        @endif
                    </span>
                @endif
                <div class="chat-message-bubble">
                    @if (! $message['mine'])
                        <strong class="chat-message-author">{{ $message['sender_name'] }}</strong>
                    @endif
                    <p>{{ $message['body'] }}</p>
                    <time datetime="{{ $message['created_at'] }}">
                        {{ \Illuminate\Support\Carbon::parse($message['created_at'])->format('H:i') }}
                        @if ($message['mine'])
                            <span aria-label="Mensagem enviada">✓✓</span>
                        @endif
                    </time>
                </div>
            </div>
        @empty
            <div class="chat-empty-thread">
                <span class="mini-pokeball" aria-hidden="true"></span>
                <h3>Nenhuma mensagem ainda. Diga olá!</h3>
                <p>Envie uma mensagem para começar essa conversa.</p>
            </div>
        @endforelse
    </div>

    <form
        wire:submit.prevent="send"
        x-data="{ liveBody: @entangle('body') }"
        class="chat-composer"
    >
        <div class="chat-composer-box">
            <button type="button" disabled class="chat-composer-action" aria-label="Anexos indisponíveis" title="Anexos em breve">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.6-9.6a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 1 1-2.8-2.8l8.9-8.9" />
                </svg>
            </button>

            <textarea
                wire:model="body"
                wire:keydown.enter.prevent="send"
                maxlength="{{ config('chat.max_message_length') }}"
                rows="1"
                placeholder="Digite sua mensagem..."
                aria-label="Mensagem"
            ></textarea>

            <p
                class="chat-character-count"
                x-show="liveBody.length >= 1800"
            >
                <span x-text="liveBody.length"></span>/{{ config('chat.max_message_length') }} caracteres.
                <span x-show="liveBody.length > {{ config('chat.max_message_length') }}" class="chat-character-error">
                    Máximo de {{ config('chat.max_message_length') }} caracteres.
                </span>
            </p>

            <button type="button" disabled class="chat-composer-action" aria-label="Emojis indisponíveis" title="Emojis em breve">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" d="M8.5 14.5a5 5 0 0 0 7 0M9 9h.01M15 9h.01" />
                </svg>
            </button>

            <button
                type="submit"
                aria-label="Enviar mensagem"
                x-bind:disabled="liveBody.length === 0 || liveBody.length > {{ config('chat.max_message_length') }}"
                wire:loading.attr="disabled"
                wire:target="send"
                class="chat-send-button"
            >
                <svg wire:loading.remove wire:target="send" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z" />
                    <path stroke-linecap="round" d="M22 2 11 13" />
                </svg>
                <span class="sr-only" wire:loading.remove wire:target="send">Enviar</span>
                <span class="sr-only" wire:loading wire:target="send">Enviando...</span>
            </button>
        </div>

        <x-input-error :messages="$errors->get('body')" class="chat-composer-error" />
    </form>
</div>
