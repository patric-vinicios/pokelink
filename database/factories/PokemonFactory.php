<?php

namespace Database\Factories;

use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pokemon>
 */
class PokemonFactory extends Factory
{
    protected $model = Pokemon::class;

    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 100000);
        $slug = fake()->unique()->slug(2);

        return [
            'number' => $number,
            'name' => $slug,
            'slug' => $slug,
            'sprite_url' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/{$number}.png",
        ];
    }
}
