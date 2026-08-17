<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        [$lowerId, $higherId] = $userA->id < $userB->id
            ? [$userA->id, $userB->id]
            : [$userB->id, $userA->id];

        return [
            'user_one_id' => $lowerId,
            'user_two_id' => $higherId,
            'last_message_at' => null,
        ];
    }

    /**
     * Attaches $count messages, alternating a random sender between the two
     * participants, so tests can seed a populated conversation in one call.
     */
    public function withMessages(int $count = 3): static
    {
        return $this->afterCreating(function (Conversation $conversation) use ($count) {
            Message::factory()
                ->count($count)
                ->for($conversation)
                ->sequence(fn () => [
                    'sender_id' => fake()->randomElement([
                        $conversation->user_one_id,
                        $conversation->user_two_id,
                    ]),
                ])
                ->create();
        });
    }
}
