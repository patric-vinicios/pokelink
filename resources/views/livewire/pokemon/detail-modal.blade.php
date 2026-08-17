<?php

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Services\PokeApi\PokeApiClient;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    private const STAT_LABELS = [
        'hp' => 'HP',
        'attack' => 'Ataque',
        'defense' => 'Defesa',
        'special_attack' => 'Ataque Especial',
        'special_defense' => 'Defesa Especial',
        'speed' => 'Velocidade',
    ];

    private const OVERVIEW_STATS = ['hp', 'attack', 'defense', 'speed'];

    public string $slug = '';

    public ?int $number = null;

    public string $name = '';

    public ?string $spriteUrl = null;

    public string $primaryType = 'normal';

    /** @var list<array{slug: string, label: string, color: string}> */
    public array $typeBadges = [];

    public bool $showInitially = false;

    public bool $foundLocally = false;

    public bool $notFound = false;

    public bool $headerUnavailable = false;

    public bool $detailLoaded = false;

    public bool $detailUnavailable = false;

    /** @var list<array{name: string, hidden: bool}> */
    public array $abilities = [];

    /** @var array<string, int> */
    public array $stats = [];

    /** @var list<array{slug: string, label: string, multiplier: float}> */
    public array $weaknesses = [];

    /** @var list<array{number: int|null, name: string, min_level: int|null, trigger: string|null, sprite_url: string|null}> */
    public array $evolutions = [];

    /** @var list<string> */
    public array $moves = [];

    public ?float $heightM = null;

    public ?float $weightKg = null;

    public ?int $baseExperience = null;

    public ?string $genus = null;

    public ?string $flavorText = null;

    public function mount(): void
    {
        $slug = request()->query('pokemon');

        if (is_string($slug) && $slug !== '') {
            $this->prepare($slug);
            $this->showInitially = true;
        }
    }

    #[On('open-pokemon')]
    public function openPokemon(string $slug): void
    {
        $this->prepare($slug);
        $this->dispatch('open-modal', 'pokemon-details');
    }

    public function loadDetail(): void
    {
        if ($this->slug === '' || $this->detailLoaded) {
            return;
        }

        $result = app(PokeApiClient::class)->pokemonDetail($this->number ?? $this->slug);

        if ($result->notFound()) {
            $this->notFound = true;
            $this->detailLoaded = true;

            return;
        }

        if (! $result->successful()) {
            $this->detailUnavailable = true;
            $this->headerUnavailable = ! $this->foundLocally;
            $this->detailLoaded = true;

            return;
        }

        $data = $result->data() ?? [];

        if (! $this->foundLocally) {
            $this->applyHeaderFromUpstream($data);
        }

        $this->applyDetailFromPayload($data);
        $this->detailLoaded = true;
    }

    public function retry(): void
    {
        $this->detailUnavailable = false;
        $this->headerUnavailable = false;
        $this->detailLoaded = false;

        $this->loadDetail();
    }

    #[Computed]
    public function favorited(): bool
    {
        return $this->number !== null && Favorite::query()
            ->where('user_id', auth()->id())
            ->where('pokemon_number', $this->number)
            ->exists();
    }

    #[Computed]
    public function statRows(): array
    {
        return collect(self::STAT_LABELS)
            ->map(function (string $label, string $key): array {
                $value = $this->stats[$key] ?? 0;

                return [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'percent' => (int) round(min(120, max(0, $value)) / 120 * 100),
                    'band' => match (true) {
                        $value < 60 => 'low',
                        $value < 100 => 'medium',
                        default => 'high',
                    },
                ];
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function overviewStatRows(): array
    {
        return collect($this->statRows)
            ->filter(fn (array $row): bool => in_array($row['key'], self::OVERVIEW_STATS, true))
            ->values()
            ->all();
    }

    private function prepare(string $slug): void
    {
        $this->reset([
            'number',
            'name',
            'spriteUrl',
            'primaryType',
            'typeBadges',
            'foundLocally',
            'notFound',
            'headerUnavailable',
            'detailLoaded',
            'detailUnavailable',
            'abilities',
            'stats',
            'weaknesses',
            'evolutions',
            'moves',
            'heightM',
            'weightKg',
            'baseExperience',
            'genus',
            'flavorText',
        ]);

        $this->slug = $slug;

        $pokemon = Pokemon::query()->where('slug', $slug)->with('types')->first();

        if ($pokemon !== null) {
            $this->foundLocally = true;
            $this->applyHeaderFromLocal($pokemon);
        }
    }

    private function applyHeaderFromLocal(Pokemon $pokemon): void
    {
        $this->number = $pokemon->number;
        $this->name = $pokemon->name;
        $this->spriteUrl = $pokemon->sprite_url;
        $this->typeBadges = $pokemon->types
            ->map(fn ($type) => $this->typeBadge($type->slug, $type->label_pt))
            ->values()
            ->all();
        $this->primaryType = $this->typeBadges[0]['slug'] ?? 'normal';
        $this->weaknesses = $this->calculateWeaknesses(array_column($this->typeBadges, 'slug'));
    }

    private function applyHeaderFromUpstream(array $data): void
    {
        $this->number = $data['number'] ?? null;
        $this->name = $data['name'] ?? $this->slug;
        $this->spriteUrl = $data['sprite_url'] ?? null;
        $this->typeBadges = collect($data['types'] ?? [])
            ->map(fn (string $typeSlug) => $this->typeBadge($typeSlug))
            ->values()
            ->all();
        $this->primaryType = $this->typeBadges[0]['slug'] ?? 'normal';
    }

    private function applyDetailFromPayload(array $data): void
    {
        $this->abilities = collect($data['abilities'] ?? [])
            ->map(fn (array $ability) => [
                'name' => ucfirst(str_replace('-', ' ', (string) ($ability['name'] ?? ''))),
                'hidden' => (bool) ($ability['hidden'] ?? false),
            ])
            ->all();

        $typeSlugs = collect($data['types'] ?? array_column($this->typeBadges, 'slug'))
            ->filter(fn ($type): bool => is_string($type))
            ->values()
            ->all();

        $this->stats = $data['stats'] ?? [];
        $this->heightM = $data['height_m'] ?? null;
        $this->weightKg = $data['weight_kg'] ?? null;
        $this->baseExperience = $data['base_experience'] ?? null;
        $this->genus = $data['genus'] ?? null;
        $this->flavorText = $data['flavor_text'] ?? null;
        $this->weaknesses = $this->calculateWeaknesses($typeSlugs);
        $this->evolutions = $data['evolutions'] ?? [];
        $this->moves = collect($data['moves'] ?? [])
            ->map(fn (string $move): string => ucfirst(str_replace('-', ' ', $move)))
            ->values()
            ->all();
    }

    private function typeBadge(string $slug, ?string $label = null): array
    {
        return [
            'slug' => $slug,
            'label' => Str::ucfirst($label ?? config("pokemon.type_labels.{$slug}", $slug)),
            'color' => config("pokemon.type_colors.{$slug}", 'gray'),
        ];
    }

    /**
     * @param  list<string>  $defendingTypes
     * @return list<array{slug: string, label: string, multiplier: float}>
     */
    private function calculateWeaknesses(array $defendingTypes): array
    {
        $matchups = config('pokemon.type_matchups', []);
        $multipliers = array_fill_keys(array_keys(config('pokemon.type_labels', [])), 1.0);

        foreach ($defendingTypes as $defendingType) {
            $rules = $matchups[$defendingType] ?? [];

            foreach ($rules['weak_to'] ?? [] as $attackingType) {
                $multipliers[$attackingType] *= 2;
            }

            foreach ($rules['resists'] ?? [] as $attackingType) {
                $multipliers[$attackingType] *= 0.5;
            }

            foreach ($rules['immune_to'] ?? [] as $attackingType) {
                $multipliers[$attackingType] = 0;
            }
        }

        arsort($multipliers);

        return collect($multipliers)
            ->filter(fn (float $multiplier): bool => $multiplier > 1)
            ->map(fn (float $multiplier, string $slug): array => [
                'slug' => $slug,
                'label' => Str::ucfirst(config("pokemon.type_labels.{$slug}", $slug)),
                'multiplier' => $multiplier,
            ])
            ->values()
            ->all();
    }
}; ?>

<div data-pokemon-detail-modal>
    <x-modal name="pokemon-details" :show="$showInitially" maxWidth="6xl" panelClass="pokemon-detail-modal-panel" focusable>
        @if ($slug !== '')
            <article
                @if (! $detailLoaded) wire:init="loadDetail" @endif
                x-data="{ tab: 'overview' }"
                class="pokemon-detail-dialog group"
                data-primary-type="{{ $primaryType }}"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pokemon-detail-title"
            >
                <button
                    type="button"
                    class="pokemon-detail-close"
                    x-on:click="$dispatch('close-modal', 'pokemon-details')"
                    aria-label="Fechar detalhes"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>

                @if ($notFound)
                    <div class="pokemon-detail-state">
                        <x-empty-state message="Pokémon não encontrado." />
                    </div>
                @elseif ($headerUnavailable)
                    <div class="pokemon-detail-state">
                        <x-empty-state message="Não foi possível carregar os detalhes agora. O serviço de dados está temporariamente indisponível.">
                            <x-slot name="action">
                                <x-secondary-button wire:click="retry">Tentar novamente</x-secondary-button>
                            </x-slot>
                        </x-empty-state>
                    </div>
                @elseif (! $foundLocally && ! $detailLoaded)
                    <div class="pokemon-detail-state animate-pulse" aria-label="Carregando detalhes">
                        <div class="mx-auto h-48 w-48 rounded-full bg-slate-100"></div>
                        <div class="mx-auto mt-5 h-7 w-52 rounded bg-slate-100"></div>
                    </div>
                @else
                    <div class="pokemon-detail-layout">
                        <section class="pokemon-detail-visual">
                            <svg class="pokemon-detail-visual-bg" viewBox="0 0 480 640" preserveAspectRatio="none" aria-hidden="true">
                                <circle cx="300" cy="146" r="108" />
                                <circle cx="300" cy="146" r="76" />
                                <path d="M0 466c76-58 145-71 217-31 64 35 119 30 183-22 34-27 62-37 80-41v268H0Z" />
                                <path d="M0 535c80-32 154-32 221 8 65 39 144 43 259-3v100H0Z" />
                            </svg>

                            <p class="pokemon-detail-number">
                                <span class="mini-pokeball" aria-hidden="true"></span>
                                #{{ str_pad((string) $number, 4, '0', STR_PAD_LEFT) }}
                            </p>

                            <div x-data="{ broken: false }" class="pokemon-detail-art">
                                <img
                                    x-show="! broken"
                                    x-on:error="broken = true"
                                    src="{{ $spriteUrl }}"
                                    alt="{{ ucfirst($name) }}"
                                />
                                <svg x-cloak x-show="broken" class="pokemon-detail-placeholder" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 3c-3.31 0-6 2.69-6 6 0 2.22 1.21 4.15 3 5.19V16a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1.81c1.79-1.04 3-2.97 3-5.19 0-3.31-2.69-6-6-6ZM9 20a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1H9v1Z" />
                                </svg>
                            </div>

                            <div class="pokemon-detail-description">
                                <span class="pokemon-detail-description-icon" aria-hidden="true">i</span>
                                <div>
                                    <strong>Descrição</strong>
                                    @if (! $detailLoaded)
                                        <span class="pokemon-detail-description-skeleton"></span>
                                    @else
                                        <p>{{ $flavorText ?: 'Descrição indisponível para este Pokémon.' }}</p>
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section class="pokemon-detail-content">
                            <header class="pokemon-detail-heading">
                                <div>
                                    <h2 id="pokemon-detail-title">{{ ucfirst($name) }}</h2>
                                    <div class="pokemon-detail-subheading">
                                        <p>{{ $genus ?: 'Pokémon '.($typeBadges[0]['label'] ?? '') }}</p>
                                        <div class="pokemon-detail-types">
                                            @foreach ($typeBadges as $badge)
                                                <span class="pokemon-detail-type" data-type="{{ $badge['slug'] }}">
                                                    <i style="--detail-type-icon: url('{{ asset('images/icons/types/glyphs/'.$badge['slug'].'.svg') }}')"></i>
                                                    {{ $badge['label'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                @if ($number !== null)
                                    <div class="pokemon-detail-favorite">
                                        <livewire:pokemon.favorite-toggle
                                            :number="$number"
                                            :name="$name"
                                            :favorited="$this->favorited"
                                            variant="icon"
                                            :key="'detail-modal-favorite-'.$number"
                                        />
                                    </div>
                                @endif
                            </header>

                            @if (! $detailLoaded)
                                <section class="pokemon-detail-loading" aria-label="Carregando informações completas">
                                    @for ($i = 0; $i < 8; $i++)
                                        <span></span>
                                    @endfor
                                </section>
                            @elseif ($detailUnavailable)
                                <section class="pokemon-detail-state pokemon-detail-state--compact">
                                    <x-empty-state message="Não foi possível carregar os detalhes agora.">
                                        <x-slot name="action">
                                            <x-secondary-button wire:click="retry">Tentar novamente</x-secondary-button>
                                        </x-slot>
                                    </x-empty-state>
                                </section>
                            @else
                                <div x-cloak x-show="tab === 'overview'" class="pokemon-detail-tab-panel">
                                    <dl class="pokemon-detail-measures">
                                        <div>
                                            <dt><span aria-hidden="true">↕</span> Altura</dt>
                                            <dd>{{ $heightM !== null ? number_format($heightM, 1, ',', '.').' m' : 'Indisponível' }}</dd>
                                        </div>
                                        <div>
                                            <dt><span aria-hidden="true">●</span> Peso</dt>
                                            <dd>{{ $weightKg !== null ? number_format($weightKg, 1, ',', '.').' kg' : 'Indisponível' }}</dd>
                                        </div>
                                        <div>
                                            <dt><span aria-hidden="true">★</span> XP base</dt>
                                            <dd>{{ $baseExperience ?? 'Indisponível' }}</dd>
                                        </div>
                                    </dl>

                                    <section class="pokemon-detail-overview-stats" aria-label="Principais estatísticas">
                                        @foreach ($this->overviewStatRows as $row)
                                            <div class="pokemon-detail-stat" data-band="{{ $row['band'] }}">
                                                <span>{{ $row['label'] }}</span>
                                                <div><i style="width: {{ $row['percent'] }}%"></i></div>
                                                <strong>{{ $row['value'] }}</strong>
                                            </div>
                                        @endforeach
                                    </section>

                                    <div class="pokemon-detail-overview-meta">
                                        <section class="pokemon-detail-chip-section">
                                            <h3>Habilidades</h3>
                                            @if (empty($abilities))
                                                <p>Informação indisponível.</p>
                                            @else
                                                <div class="pokemon-detail-chips">
                                                    @foreach ($abilities as $ability)
                                                        <span>
                                                            {{ $ability['name'] }}
                                                            @if ($ability['hidden'])<small>(oculta)</small>@endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </section>

                                        <section class="pokemon-detail-chip-section">
                                            <h3>Fraquezas</h3>
                                            @if (empty($weaknesses))
                                                <p>Informação indisponível.</p>
                                            @else
                                                <div class="pokemon-detail-chips pokemon-detail-weaknesses">
                                                    @foreach ($weaknesses as $weakness)
                                                        <span data-type="{{ $weakness['slug'] }}">
                                                            {{ $weakness['label'] }}
                                                            @if ($weakness['multiplier'] >= 4)<small>×4</small>@endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </section>
                                    </div>

                                    <section class="pokemon-detail-evolution">
                                        <h3>Evolução</h3>
                                        @if (count($evolutions) <= 1)
                                            <p>Este Pokémon não possui uma cadeia evolutiva disponível.</p>
                                        @else
                                            <div class="pokemon-detail-evolution-chain">
                                                @foreach ($evolutions as $index => $evolution)
                                                    @if ($index > 0)
                                                        <span class="pokemon-detail-evolution-arrow" aria-hidden="true">→</span>
                                                    @endif
                                                    <div class="pokemon-detail-evolution-stage {{ $evolution['number'] === $number ? 'is-current' : '' }}">
                                                        <span>
                                                            @if ($evolution['sprite_url'])
                                                                <img src="{{ $evolution['sprite_url'] }}" alt="" loading="lazy" />
                                                            @endif
                                                        </span>
                                                        <strong>{{ ucfirst($evolution['name']) }}</strong>
                                                        @if ($evolution['min_level'])
                                                            <small>Nível {{ $evolution['min_level'] }}</small>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                </div>

                                <div x-cloak x-show="tab === 'stats'" class="pokemon-detail-tab-panel pokemon-detail-tab-panel--stats">
                                    <div class="pokemon-detail-section-title">
                                        <span>◉</span>
                                        <div><h3>Estatísticas base</h3><p>Valores naturais deste Pokémon</p></div>
                                    </div>

                                    @if (empty($stats))
                                        <p>Informação indisponível.</p>
                                    @else
                                        <div class="pokemon-detail-all-stats">
                                            @foreach ($this->statRows as $row)
                                                <div class="pokemon-detail-stat" data-band="{{ $row['band'] }}">
                                                    <span>{{ $row['label'] }}</span>
                                                    <div><i style="width: {{ $row['percent'] }}%"></i></div>
                                                    <strong>{{ $row['value'] }}</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div x-cloak x-show="tab === 'moves'" class="pokemon-detail-tab-panel pokemon-detail-tab-panel--moves">
                                    <div class="pokemon-detail-section-title">
                                        <span>↗</span>
                                        <div><h3>Movimentos</h3><p>Principais movimentos disponíveis</p></div>
                                    </div>

                                    @if (empty($moves))
                                        <p>Informação indisponível.</p>
                                    @else
                                        <div class="pokemon-detail-moves">
                                            @foreach ($moves as $move)
                                                <span>{{ $move }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </section>

                        <nav class="pokemon-detail-tabs" aria-label="Seções dos detalhes">
                            <button type="button" x-on:click="tab = 'overview'" :class="{ 'is-active': tab === 'overview' }">
                                <span class="mini-pokeball" aria-hidden="true"></span>
                                Visão geral
                            </button>
                            <button type="button" x-on:click="tab = 'stats'" :class="{ 'is-active': tab === 'stats' }">
                                <span aria-hidden="true">◉</span>
                                Estatísticas
                            </button>
                            <button type="button" x-on:click="tab = 'moves'" :class="{ 'is-active': tab === 'moves' }">
                                <span aria-hidden="true">↗</span>
                                Movimentos
                            </button>
                        </nav>
                    </div>
                @endif
            </article>
        @endif
    </x-modal>
</div>
