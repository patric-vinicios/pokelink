<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Livewire\Volt\Volt;

test('the conversation list excludes the current user', function () {
    $me = User::factory()->create(['name' => 'Ana']);
    User::factory()->create(['name' => 'Bob']);

    Volt::actingAs($me)->test('chat.user-list')
        ->assertDontSee('Ana')
        ->assertSee('Bob');
});

test('users with the most recent message appear first, the rest in alphabetical order', function () {
    $me = User::factory()->create();
    $noConversationZ = User::factory()->create(['name' => 'Zelda']);
    $noConversationA = User::factory()->create(['name' => 'Alan']);
    $recent = User::factory()->create(['name' => 'Recent Contact']);
    $older = User::factory()->create(['name' => 'Older Contact']);

    Conversation::betweenUsers($me, $older)->update(['last_message_at' => now()->subDay()]);
    Conversation::betweenUsers($me, $recent)->update(['last_message_at' => now()]);

    Volt::actingAs($me)->test('chat.user-list')
        ->assertSeeInOrder(['Recent Contact', 'Older Contact', 'Alan', 'Zelda']);
});

test('the name filter restricts the list', function () {
    $me = User::factory()->create();
    User::factory()->create(['name' => 'Bulbasaur Fan']);
    User::factory()->create(['name' => 'Charmander Fan']);

    Volt::actingAs($me)->test('chat.user-list')
        ->set('search', 'bulba')
        ->assertSee('Bulbasaur Fan')
        ->assertDontSee('Charmander Fan');
});

test('the list paginates every 30 users', function () {
    $me = User::factory()->create();
    User::factory()->count(35)->create();

    $component = Volt::actingAs($me)->test('chat.user-list');

    expect($component->viewData('users'))->toHaveCount(30);

    $component->call('nextPage');

    expect($component->viewData('users'))->toHaveCount(5);
});

test('the unread counter appears per conversation and caps at 99+', function () {
    $me = User::factory()->create();
    $chatty = User::factory()->create(['name' => 'Chatty']);
    $quiet = User::factory()->create(['name' => 'Quiet']);

    $withLots = Conversation::betweenUsers($me, $chatty);
    Message::factory()->count(150)->for($withLots)->create(['sender_id' => $chatty->id, 'read_at' => null]);

    $withFew = Conversation::betweenUsers($me, $quiet);
    Message::factory()->count(3)->for($withFew)->create(['sender_id' => $quiet->id, 'read_at' => null]);

    Volt::actingAs($me)->test('chat.user-list')
        ->assertSee('99+')
        ->assertSee('3');
});
