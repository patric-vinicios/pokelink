<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Whether $user is one of the conversation's two participants. Used both
     * by routes/channels.php's authorization callback and as a defense-in-
     * depth guard inside the chat Livewire components.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->user_one_id || $user->id === $conversation->user_two_id;
    }
}
