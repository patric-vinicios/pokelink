<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Livewire\Volt\Volt;

test('opening a nonexistent conversation creates no row', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->assertSee('Nenhuma mensagem ainda');

    expect(Conversation::count())->toBe(0);
});

test('sending the first message creates the conversation and the message', function () {
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

test('two messages between the same users, in either order, produce a single conversation', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Volt::actingAs($a)->test('chat.conversation', ['otherUserId' => $b->id])
        ->set('body', 'oi')->call('send');

    Volt::actingAs($b)->test('chat.conversation', ['otherUserId' => $a->id])
        ->set('body', 'e ai')->call('send');

    expect(Conversation::count())->toBe(1);
    expect(Message::count())->toBe(2);
});

test('the history loads the 30 most recent messages in chronological order', function () {
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

test('scrolling to the top loads the previous 30 messages', function () {
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

test('opening the conversation marks the other participant\'s messages as read', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);
    Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'read_at' => null]);
    Message::factory()->for($conversation)->create(['sender_id' => $me->id, 'read_at' => null]);

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id]);

    expect($conversation->messages()->where('sender_id', $other->id)->whereNull('read_at')->count())->toBe(0);
    expect($conversation->messages()->where('sender_id', $me->id)->whereNull('read_at')->count())->toBe(1);
});

test('a message over 2000 characters is rejected even when bypassing the client-side control', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->set('body', str_repeat('a', 2001))
        ->call('send')
        ->assertHasErrors('body');

    expect(Message::count())->toBe(0);
});

test('a message body containing html is rendered as literal text', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);
    Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'body' => '<script>alert(1)</script>']);

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('<script>alert(1)</script>');
});

test('a first message to a brand-new conversation still reaches the recipient\'s already-open thread', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    // $me opens the thread before anyone has ever messaged — mounts with
    // conversationId = 0, the "no conversation yet" sentinel, and so
    // subscribes (client-side) to conversation.0, a channel nothing
    // publishes to. Nothing re-renders $me's component on its own, so
    // without the personal-channel fallback this binding would never
    // update once $other's message below creates the real conversation.
    $component = Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id]);
    expect($component->get('conversationId'))->toBe(0);

    Volt::actingAs($other)->test('chat.conversation', ['otherUserId' => $me->id])
        ->set('body', 'oi, tudo bem?')
        ->call('send');

    $conversation = Conversation::first();
    $message = Message::first();

    // Simulates the App.Models.User.{me->id} event Echo delivers to $me's
    // browser — the channel MessageSent::broadcastOn() always reaches
    // regardless of what conversation.{conversationId} is bound to.
    $component->call('firstMessageReceived', [
        'id' => $message->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $other->id,
        'sender_name' => $other->name,
        'body' => $message->body,
        'created_at' => $message->created_at->toIso8601String(),
    ]);

    expect($component->get('conversationId'))->toBe($conversation->id);
    $component->assertSee('oi, tudo bem?');
});

test('the personal-channel fallback ignores a message from someone other than the open thread\'s participant', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $stranger = User::factory()->create();

    $component = Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id]);

    $component->call('firstMessageReceived', [
        'id' => 999,
        'conversation_id' => 999,
        'sender_id' => $stranger->id,
        'sender_name' => $stranger->name,
        'body' => 'not for this thread',
        'created_at' => now()->toIso8601String(),
    ]);

    expect($component->get('conversationId'))->toBe(0);
    $component->assertDontSee('not for this thread');
});

test('the personal-channel fallback does not double-append once the conversation id is already known', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::betweenUsers($me, $other);

    $component = Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id]);
    expect($component->get('conversationId'))->toBe($conversation->id);

    $message = Message::factory()->for($conversation)->create(['sender_id' => $other->id, 'body' => 'ja tinha conversa']);

    // The real conversation.{conversationId} listener (messageReceived)
    // already handles this message; the fallback must no-op instead of
    // appending it a second time.
    $component->call('firstMessageReceived', [
        'id' => $message->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $other->id,
        'sender_name' => $other->name,
        'body' => $message->body,
        'created_at' => $message->created_at->toIso8601String(),
    ]);

    expect($component->get('messages'))->toBeEmpty();
});

test('a user cannot load the history of a conversation they don\'t participate in', function () {
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
