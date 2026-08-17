<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// F12: a conversation's two participants only — implicit route-model
// binding resolves {conversation}, ConversationPolicy::view does the check.
Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation) {
    return $user->can('view', $conversation);
});

// F12: any authenticated user may join the presence roster. Echo prepends
// "presence-" to this wire name automatically on the client.
Broadcast::channel('online', function (User $user) {
    return ['id' => $user->id, 'name' => $user->name];
});
