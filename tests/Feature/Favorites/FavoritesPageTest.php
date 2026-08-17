<?php

/*
|--------------------------------------------------------------------------
| F10 — Favorites page
|--------------------------------------------------------------------------
|
| Covers /favoritos: listing, ordering, the name filter, the sort control,
| pagination, and both empty states. Reuses the exact card component F08's
| own grid renders.
|
*/

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\User;
use Livewire\Volt\Volt;

test('a pagina de favoritos exige autenticacao', function () {
    $this->get('/favoritos')->assertRedirect(route('login'));
});

// beforeEach applies file-wide regardless of declaration order in this Pest
// setup, so the actingAs() below is scoped to a describe() block instead —
// the guest-redirect test above needs to run unauthenticated.
describe('usuário autenticado', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    test('a pagina lista apenas os favoritos do usuario autenticado', function () {
        $other = User::factory()->create();

        $mine = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        $theirs = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $mine->number]);
        Favorite::factory()->for($other)->create(['pokemon_number' => $theirs->number]);

        $response = $this->get('/favoritos');

        $response->assertOk()->assertSee('bulbasaur')->assertDontSee('charmander');
    });

    test('os favoritos aparecem em ordem do mais recente para o mais antigo por padrao', function () {
        $first = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        $second = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
        $third = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $first->number, 'created_at' => now()->subMinutes(3)]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $second->number, 'created_at' => now()->subMinutes(2)]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $third->number, 'created_at' => now()->subMinute()]);

        $html = Volt::test('pages.pokemon.favorites')->html();

        $positions = [
            'squirtle' => mb_strpos($html, 'squirtle'),
            'charmander' => mb_strpos($html, 'charmander'),
            'bulbasaur' => mb_strpos($html, 'bulbasaur'),
        ];

        expect($positions['squirtle'])->toBeLessThan($positions['charmander'])
            ->and($positions['charmander'])->toBeLessThan($positions['bulbasaur']);
    });

    test('o filtro de texto busca apenas dentro da colecao do usuario', function () {
        $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
        $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charizard->number]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $squirtle->number]);

        Volt::test('pages.pokemon.favorites')
            ->set('search', 'char')
            ->assertSee('charizard')
            ->assertDontSee('squirtle');
    });

    test('o controle de ordenacao por nome reordena a lista alfabeticamente', function () {
        $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charizard->number, 'created_at' => now()->subMinute()]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number, 'created_at' => now()]);

        $html = Volt::test('pages.pokemon.favorites')->set('sort', 'name')->html();

        expect(mb_strpos($html, 'bulbasaur'))->toBeLessThan(mb_strpos($html, 'charizard'));
    });

    test('o controle de ordenacao por numero usa o numero nacional', function () {
        $mewtwo = Pokemon::factory()->create(['number' => 150, 'name' => 'mewtwo', 'slug' => 'mewtwo']);
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $mewtwo->number, 'created_at' => now()->subMinute()]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number, 'created_at' => now()]);

        $html = Volt::test('pages.pokemon.favorites')->set('sort', 'number')->html();

        expect(mb_strpos($html, 'bulbasaur'))->toBeLessThan(mb_strpos($html, 'mewtwo'));
    });

    test('a colecao vazia mostra a mensagem dedicada com o link para buscar', function () {
        $response = $this->get('/favoritos');

        $response->assertOk()
            ->assertSee('Você ainda não favoritou nenhum Pokémon.')
            ->assertSee(route('dashboard'), false);
    });

    test('um filtro sem correspondencia mostra o estado de nenhum resultado sem esvaziar a colecao', function () {
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number]);

        Volt::test('pages.pokemon.favorites')
            ->set('search', 'xyz')
            ->assertSee("Nenhum Pokémon encontrado para 'xyz'.")
            ->assertDontSee('Você ainda não favoritou nenhum Pokémon.');
    });

    test('a pagina de favoritos reutiliza o mesmo componente de card dos resultados de busca', function () {
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number]);

        $response = $this->get('/favoritos');

        $response->assertOk()
            ->assertSee('#0001')
            ->assertSee('bulbasaur')
            ->assertSee('wire:key="favorite-pokemon-1"', false);
    });

    test('a pagina pagina em 20 itens', function () {
        Pokemon::factory()
            ->count(45)
            ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
            ->create()
            ->each(fn (Pokemon $pokemon) => Favorite::factory()->for($this->user)->create(['pokemon_number' => $pokemon->number]));

        $response = $this->get('/favoritos');

        $response->assertOk()->assertSee('Exibindo');
        expect(substr_count($response->getContent(), 'wire:key="favorite-pokemon-'))->toBe(20);
    });
});
