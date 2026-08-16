<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the two accounts published in the README.
 *
 * Both are ordinary users. The ADMIN label exists only so a reviewer can hold
 * two sessions side by side and verify that favorites, profile, and messages
 * are scoped to their owner — it grants no extra screens or permissions.
 */
class UserSeeder extends Seeder
{
    /**
     * The accounts documented in the README.
     *
     * The password is assigned in plaintext and hashed by the model's `hashed`
     * cast, so no plaintext value ever reaches the database.
     *
     * @var list<array{name: string, email: string, password: string}>
     */
    protected array $accounts = [
        [
            'name' => 'Admin',
            'email' => 'admin@pokelink.test',
            'password' => 'password',
        ],
        [
            'name' => 'Usuário',
            'email' => 'user@pokelink.test',
            'password' => 'password',
        ],
    ];

    public function run(): void
    {
        foreach ($this->accounts as $account) {
            // Keyed on e-mail so re-running the seeder updates the existing row
            // instead of colliding with the unique index on users.email.
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
