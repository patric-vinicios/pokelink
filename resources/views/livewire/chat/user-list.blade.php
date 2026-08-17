<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Reactive]
    public ?int $selectedUserId = null;

    public int $authUserId;

    public function mount(): void
    {
        $this->authUserId = auth()->id();
    }

    /**
     * No body needed — this method exists solely to register the private
     * channel listener. A new message anywhere re-renders the list so
     * ordering and unread badges stay current even for a closed conversation.
     */
    #[On('echo-private:App.Models.User.{authUserId},.message.sent')]
    public function refreshList(): void
    {
        //
    }

    public function with(): array
    {
        $authUserId = $this->authUserId;

        $users = User::query()
            ->where('users.id', '!=', $authUserId)
            ->leftJoin('conversations', function ($join) use ($authUserId) {
                $join->where(function ($join) use ($authUserId) {
                    $join->where('conversations.user_one_id', $authUserId)
                        ->whereColumn('conversations.user_two_id', 'users.id');
                })->orWhere(function ($join) use ($authUserId) {
                    $join->where('conversations.user_two_id', $authUserId)
                        ->whereColumn('conversations.user_one_id', 'users.id');
                });
            })
            ->select('users.id', 'users.name')
            ->selectRaw('conversations.last_message_at as last_message_at')
            ->selectRaw('(select messages.body from messages where messages.conversation_id = conversations.id order by messages.id desc limit 1) as last_message_body')
            ->selectRaw('coalesce(
                (select pokemon.sprite_url from favorites inner join pokemon on pokemon.number = favorites.pokemon_number where favorites.user_id = users.id order by favorites.created_at desc limit 1),
                (select pokemon.sprite_url from pokemon where pokemon.number = users.id limit 1)
            ) as avatar_url')
            ->selectRaw(
                '(select count(*) from messages where messages.conversation_id = conversations.id and messages.sender_id != ? and messages.read_at is null) as unread_count',
                [$authUserId]
            )
            ->orderByRaw('(conversations.last_message_at is null) asc')
            ->orderByDesc('conversations.last_message_at')
            ->orderBy('users.name')
            ->paginate(config('chat.user_list_page_size'));

        return [
            'users' => $users,
        ];
    }
}; ?>

<div class="chat-directory-inner">
    <h2 class="sr-only">Conversas</h2>

    <div class="chat-user-list">
        @forelse ($users as $user)
            <button
                type="button"
                wire:click="$dispatch('conversation-selected', { userId: {{ $user->id }} })"
                wire:key="chat-user-{{ $user->id }}"
                class="chat-user-row {{ $selectedUserId === $user->id ? 'is-selected' : '' }}"
                @if ($selectedUserId === $user->id) aria-current="true" @endif
            >
                <span class="chat-user-avatar" aria-hidden="true">
                    @if ($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="" />
                    @else
                        <span>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    @endif
                    <i
                        x-data
                        :class="$store.presence?.onlineIds?.has({{ $user->id }}) ? 'is-online' : ''"
                        class="chat-presence-dot"
                    ></i>
                </span>

                <span class="chat-user-copy">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->last_message_body ?: 'Comece uma nova conversa' }}</small>
                </span>

                <span class="chat-user-meta">
                    @if ($user->last_message_at)
                        <time datetime="{{ $user->last_message_at }}">
                            {{ \Illuminate\Support\Carbon::parse($user->last_message_at)->isToday()
                                ? \Illuminate\Support\Carbon::parse($user->last_message_at)->format('H:i')
                                : \Illuminate\Support\Carbon::parse($user->last_message_at)->diffForHumans(short: true) }}
                        </time>
                    @endif

                    @if ($user->unread_count > 0)
                        <span class="chat-unread-badge" aria-label="{{ $user->unread_count }} mensagens não lidas">
                            {{ $user->unread_count > config('chat.unread_badge_cap') ? config('chat.unread_badge_cap').'+' : $user->unread_count }}
                        </span>
                    @else
                        <i
                            x-data
                            :class="$store.presence?.onlineIds?.has({{ $user->id }}) ? 'is-online' : ''"
                            class="chat-user-status"
                            aria-hidden="true"
                        ></i>
                    @endif
                </span>
            </button>
        @empty
            <div class="chat-user-empty">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="10.5" cy="10.5" r="6.5" />
                    <path stroke-linecap="round" d="m20 20-4.5-4.5" />
                </svg>
                <p>Nenhum treinador disponível.</p>
                <span>Novos treinadores aparecerão aqui.</span>
            </div>
        @endforelse
    </div>

    @if ($users->hasPages())
        <div class="chat-directory-pagination">
            {{ $users->links() }}
        </div>
    @endif
</div>
