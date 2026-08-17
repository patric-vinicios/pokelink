<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Livewire\Volt\Volt;

test('abrir uma conversa inexistente não cria nenhuma linha', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->assertSee('Nenhuma mensagem ainda');

    expect(Conversation::count())->toBe(0);
});

test('enviar a primeira mensagem cria a conversa e a mensagem', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->set('body', 'Oi, tudo bem?')
        ->call('send')
        ->assertHasNoErrors();

    expect(Conversation::count())->toBe(1);
    expect(Message::count())->toBe(1);

    $conversation = Conversation::first();
    expect($conversation->last_message_at)->not->toBeNull();
    expect(Message::first())
        ->body->toBe('Oi, tudo bem?')
        ->sender_id->toBe($me->id);
});

test('duas mensagens entre os mesmos usuários, em qualquer ordem, produzem uma única conversa', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Volt::actingAs($a)->test('chat.conversation', ['otherUserId' => $b->id])
        ->set('body', 'oi')->call('send');

    Volt::actingAs($b)->test('chat.conversation', ['otherUserId' => $a->id])
        ->set('body', 'e ai')->call('send');

    expect(Conversation::count())->toBe(1);
    expect(Message::count())->toBe(2);
});

test('o histórico carrega as 30 mensagens mais recentes em ordem cronológica', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);

    foreach (range(1, 45) as $i) {
        Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'body' => "msg-{$i}"]);
    }

    $messages = Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->get('messages');

    expect($messages)->toHaveCount(30);
    expect($messages[0]['body'])->toBe('msg-16');
    expect($messages[29]['body'])->toBe('msg-45');
});

test('rolar até o topo carrega as 30 mensagens anteriores', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);

    foreach (range(1, 45) as $i) {
        Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'body' => "msg-{$i}"]);
    }

    $component = Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id]);

    $component->call('loadOlder');

    $messages = $component->get('messages');
    expect($messages)->toHaveCount(45);
    expect($messages[0]['body'])->toBe('msg-1');
    expect($messages[44]['body'])->toBe('msg-45');
});

test('abrir a conversa marca como lidas as mensagens do outro participante', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);
    Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'read_at' => null]);
    Message::factory()->for($conversation)->create(['sender_id' => $me->id, 'read_at' => null]);

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id]);

    expect($conversation->messages()->where('sender_id', $other->id)->whereNull('read_at')->count())->toBe(0);
    expect($conversation->messages()->where('sender_id', $me->id)->whereNull('read_at')->count())->toBe(1);
});

test('uma mensagem com mais de 2000 caracteres é rejeitada mesmo contornando o controle do cliente', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->set('body', str_repeat('a', 2001))
        ->call('send')
        ->assertHasErrors('body');

    expect(Message::count())->toBe(0);
});

test('o corpo da mensagem com html é renderizado como texto literal', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);
    Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'body' => '<script>alert(1)</script>']);

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('<script>alert(1)</script>');
});

test('um usuário não pode carregar o histórico de uma conversa da qual não participa', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $stranger = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);
    Message::factory()->count(35)->for($conversation)->create(['sender_id' => $a->id]);

    // The stranger's own component never resolves this conversation via
    // mount() — this simulates a crafted request that tampers with the
    // Livewire snapshot's conversationId, which is exactly what the
    // ConversationPolicy defense-in-depth check inside loadOlder() exists to
    // catch (see spec's "Conversation identity" technical decision).
    Volt::actingAs($stranger)->test('chat.conversation', ['otherUserId' => $a->id])
        ->set('conversationId', $conversation->id)
        ->set('hasMoreHistory', true)
        ->call('loadOlder')
        ->assertForbidden();
});
