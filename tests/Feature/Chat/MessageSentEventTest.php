<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;

test('the event is broadcast on the conversation\'s and the recipient\'s private channels', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);
    $message = Message::factory()->for($conversation)->create(['sender_id' => $a->id]);

    $channels = (new MessageSent($message))->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-conversation.'.$conversation->id);
    expect($channels[1]->name)->toBe('private-App.Models.User.'.$b->id);
});

test('the event is named message.sent', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);
    $message = Message::factory()->for($conversation)->create(['sender_id' => $a->id]);

    expect((new MessageSent($message))->broadcastAs())->toBe('message.sent');
});

test('the broadcast payload contains the expected fields', function () {
    $a = User::factory()->create(['name' => 'Ash']);
    $b = User::factory()->create();
    $conversation = Conversation::betweenUsers($a, $b);
    $message = Message::factory()->for($conversation)->create(['sender_id' => $a->id, 'body' => 'Pikachu!']);

    $payload = (new MessageSent($message))->broadcastWith();

    expect($payload)->toMatchArray([
        'id' => $message->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $a->id,
        'sender_name' => 'Ash',
        'body' => 'Pikachu!',
    ]);
    expect($payload)->toHaveKey('created_at');
});

test('sending a message dispatches the MessageSent event', function () {
    Event::fake([MessageSent::class]);

    $me = User::factory()->create();
    $other = User::factory()->create();

    Volt::actingAs($me)->test('chat.conversation', ['otherUserId' => $other->id])
        ->set('body', 'oi')
        ->call('send');

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->body === 'oi');
});
