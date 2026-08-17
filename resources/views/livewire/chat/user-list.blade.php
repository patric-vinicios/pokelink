<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $selectedUserId = null;

    public string $search = '';

    public int $authUserId;

    public function mount(): void
    {
        $this->authUserId = auth()->id();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
            ->when($this->search !== '', fn (Builder $query) => $query->where('users.name', 'like', '%'.$this->search.'%'))
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

<div class="flex flex-col h-full">
    <div class="p-3 border-b border-gray-200">
        <x-text-input
            wire:model.live.debounce.300ms="search"
            type="text"
            class="w-full text-sm"
            placeholder="Filtrar por nome..."
        />
    </div>

    <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
        @forelse ($users as $user)
            <button
                type="button"
                wire:click="$dispatch('conversation-selected', { userId: {{ $user->id }} })"
                wire:key="chat-user-{{ $user->id }}"
                class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-gray-50 {{ $selectedUserId === $user->id ? 'bg-indigo-50' : '' }}"
            >
                <span class="relative shrink-0">
                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-xs font-medium">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <span
                        x-data
                        :class="$store.presence?.onlineIds?.has({{ $user->id }}) ? 'bg-green-500' : 'bg-gray-300'"
                        class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-white"
                    ></span>
                </span>

                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium text-gray-900 truncate">{{ $user->name }}</span>
                </span>

                @if ($user->unread_count > 0)
                    <x-badge color="indigo">
                        {{ $user->unread_count > config('chat.unread_badge_cap') ? config('chat.unread_badge_cap').'+' : $user->unread_count }}
                    </x-badge>
                @endif
            </button>
        @empty
            <p class="p-4 text-sm text-gray-500">Nenhum usuário encontrado.</p>
        @endforelse
    </div>

    @if ($users->hasPages())
        <div class="p-2 border-t border-gray-200">
            {{ $users->links() }}
        </div>
    @endif
</div>
