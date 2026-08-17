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
use App\Models\Type;
use App\Models\User;
use Livewire\Volt\Volt;

test('the favorites page requires authentication', function () {
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

    test('the page lists only the authenticated user\'s favorites', function () {
        $other = User::factory()->create();

        $mine = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        $theirs = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $mine->number]);
        Favorite::factory()->for($other)->create(['pokemon_number' => $theirs->number]);

        $response = $this->get('/favoritos');

        $response->assertOk()->assertSee('bulbasaur')->assertDontSee('charmander');
    });

    test('favorites appear from most to least recent by default', function () {
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

    test('the text filter searches only within the user\'s own collection', function () {
        $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
        $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charizard->number]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $squirtle->number]);

        Volt::test('pages.pokemon.favorites')
            ->set('search', 'char')
            ->assertSee('charizard')
            ->assertDontSee('squirtle');
    });

    test('the type filter restricts the collection and renders the shared official icons', function () {
        $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
        $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

        $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
        $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
        $charizard->types()->attach($fire);
        $squirtle->types()->attach($water);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charizard->number]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $squirtle->number]);

        Volt::test('pages.pokemon.favorites')
            ->assertSee('images/icons/types/glyphs/fire.svg', false)
            ->assertSee('images/icons/types/glyphs/water.svg', false)
            ->set('type', 'fogo')
            ->assertSee('charizard')
            ->assertDontSee('squirtle');
    });

    test('clearing after several filter changes restores the complete collection in one action', function () {
        $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
        $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

        $charmander = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
        $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
        $charmander->types()->attach($fire);
        $squirtle->types()->attach($water);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charmander->number]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $squirtle->number]);

        Volt::test('pages.pokemon.favorites')
            ->set('type', 'fogo')
            ->set('type', 'água')
            ->set('search', 'squirtle')
            ->set('sort', 'name')
            ->call('clearFilters')
            ->assertSet('type', '')
            ->assertSet('search', '')
            ->assertSet('sort', 'recent')
            ->assertSee('charmander')
            ->assertSee('squirtle');
    });

    test('the visible clear-filter controls navigate to the unfiltered canonical page', function () {
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number]);

        Volt::test('pages.pokemon.favorites')
            ->set('search', 'xyz')
            ->assertSeeHtml('href="'.route('favoritos').'"')
            ->assertSeeHtml('wire:navigate');
    });

    test('the type filter offers only types present in the authenticated user favorites', function () {
        $other = User::factory()->create();
        $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
        $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

        $charmander = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
        $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
        $charmander->types()->attach($fire);
        $squirtle->types()->attach($water);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charmander->number]);
        Favorite::factory()->for($other)->create(['pokemon_number' => $squirtle->number]);

        Volt::test('pages.pokemon.favorites')
            ->assertSee('images/icons/types/glyphs/fire.svg', false)
            ->assertDontSee('images/icons/types/glyphs/water.svg', false);
    });

    test('removing the last favorite of the selected type returns to the available collection', function () {
        $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
        $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

        $charmander = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
        $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
        $charmander->types()->attach($fire);
        $squirtle->types()->attach($water);

        $fireFavorite = Favorite::factory()->for($this->user)->create(['pokemon_number' => $charmander->number]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $squirtle->number]);

        $component = Volt::test('pages.pokemon.favorites')
            ->set('type', 'fogo')
            ->assertSee('charmander')
            ->assertDontSee('squirtle');

        $fireFavorite->delete();

        $component
            ->dispatch('favorite-removed')
            ->assertSet('type', '')
            ->assertSee('squirtle')
            ->assertDontSee('Nenhum Pokémon favorito encontrado para o tipo selecionado.');
    });

    test('the favorites hero shows the collection summary and latest national number', function () {
        $older = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        $latest = Pokemon::factory()->create(['number' => 25, 'name' => 'pikachu', 'slug' => 'pikachu']);

        Favorite::factory()->for($this->user)->create([
            'pokemon_number' => $older->number,
            'created_at' => now()->subDay(),
        ]);
        Favorite::factory()->for($this->user)->create([
            'pokemon_number' => $latest->number,
            'created_at' => now(),
        ]);

        $this->get('/favoritos')
            ->assertOk()
            ->assertSee('Seus Pokémon')
            ->assertSee('favoritos')
            ->assertSee('Monte sua seleção preferida e acesse rapidamente')
            ->assertSee('Tipos diferentes')
            ->assertSee('#0025')
            ->assertSee('Último adicionado');
    });

    test('the name sort control reorders the list alphabetically', function () {
        $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $charizard->number, 'created_at' => now()->subMinute()]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number, 'created_at' => now()]);

        $html = Volt::test('pages.pokemon.favorites')->set('sort', 'name')->html();

        expect(mb_strpos($html, 'bulbasaur'))->toBeLessThan(mb_strpos($html, 'charizard'));
    });

    test('the number sort control uses the national number', function () {
        $mewtwo = Pokemon::factory()->create(['number' => 150, 'name' => 'mewtwo', 'slug' => 'mewtwo']);
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

        Favorite::factory()->for($this->user)->create(['pokemon_number' => $mewtwo->number, 'created_at' => now()->subMinute()]);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number, 'created_at' => now()]);

        $html = Volt::test('pages.pokemon.favorites')->set('sort', 'number')->html();

        expect(mb_strpos($html, 'bulbasaur'))->toBeLessThan(mb_strpos($html, 'mewtwo'));
    });

    test('an empty collection shows the dedicated message with the link to search', function () {
        $response = $this->get('/favoritos');

        $response->assertOk()
            ->assertSee('Você ainda não favoritou nenhum Pokémon.')
            ->assertSee(route('dashboard'), false);
    });

    test('a filter with no match shows the no-results state without emptying the collection', function () {
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number]);

        Volt::test('pages.pokemon.favorites')
            ->set('search', 'xyz')
            ->assertSee("Nenhum Pokémon encontrado para 'xyz'.")
            ->assertDontSee('Você ainda não favoritou nenhum Pokémon.');
    });

    test('the favorites page reuses the same card component as the search results', function () {
        $bulbasaur = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
        Favorite::factory()->for($this->user)->create(['pokemon_number' => $bulbasaur->number]);

        $response = $this->get('/favoritos');

        $response->assertOk()
            ->assertSee('#0001')
            ->assertSee('bulbasaur')
            ->assertSee('wire:key="favorite-pokemon-1"', false);
    });

    test('the page paginates in batches of 20', function () {
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
