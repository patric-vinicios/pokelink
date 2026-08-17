<?php

use App\Models\Conversation;
use App\Models\User;

test('um participante é autorizado no canal da própria conversa', function () {
    useReverbBroadcaster();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);

    $this->actingAs($a)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-conversation.'.$conversation->id,
            'socket_id' => '1234.5678',
        ])->assertOk();
});

test('um usuário que não participa é negado no canal da conversa', function () {
    useReverbBroadcaster();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $stranger = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);

    $this->actingAs($stranger)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-conversation.'.$conversation->id,
            'socket_id' => '1234.5678',
        ])->assertForbidden();
});

test('qualquer usuário autenticado é autorizado no canal de presença', function () {
    useReverbBroadcaster();

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-online',
            'socket_id' => '1234.5678',
        ])
        ->assertOk();

    // channel_data is a JSON-encoded string per the Pusher protocol, not a
    // nested object, so it must be decoded before asserting on its shape.
    $channelData = json_decode($response->json('channel_data'), true);
    expect($channelData['user_info']['id'])->toBe($user->id);
});

test('um convidado não é autorizado em nenhum dos dois canais', function () {
    useReverbBroadcaster();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);

    // Pusher's guarded-channel check (private-/presence- prefixes) rejects
    // an unauthenticated request with 403 before any channel callback runs,
    // not 401 — there is no redirect-to-login concept in this JSON exchange.
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-conversation.'.$conversation->id,
        'socket_id' => '1234.5678',
    ])->assertForbidden();

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'presence-online',
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
