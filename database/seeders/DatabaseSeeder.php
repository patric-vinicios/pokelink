<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Seeding must never block on network I/O — the container entrypoint runs
     * this before the application starts serving. Anything that talks to
     * PokeAPI belongs on the queue, not here.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);

        // F06 extension point: dispatch the Pokémon catalog sync job onto the
        // `default` queue here. The Horizon worker picks it up while the
        // application is already answering on http://localhost:8000.
    }
}
