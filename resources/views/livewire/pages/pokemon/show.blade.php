<?php

use App\Models\Pokemon;
use App\Services\PokeApi\PokeApiClient;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * Fixed PRD order and pt-BR labels for the six base stats, scaled
     * against 255 for the bar width.
     */
    private const STAT_LABELS = [
        'hp' => 'HP',
        'attack' => 'Ataque',
        'defense' => 'Defesa',
        'special_attack' => 'Ataque Especial',
        'special_defense' => 'Defesa Especial',
        'speed' => 'Velocidade',
    ];

    public string $slug = '';

    public ?int $number = null;

    public string $name = '';

    public ?string $spriteUrl = null;

    /** @var list<array{label: string, color: string}> */
    public array $typeBadges = [];

    public bool $foundLocally = false;

    public bool $notFound = false;

    public bool $headerUnavailable = false;

    public bool $detailLoaded = false;

    public bool $detailUnavailable = false;

    /** @var list<array{name: string, hidden: bool}> */
    public array $abilities = [];

    /** @var array<string, int> */
    public array $stats = [];

    public ?float $heightM = null;

    public ?float $weightKg = null;

    public ?string $flavorText = null;

    /** @var array<string, string> */
    public array $returnQuery = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        $this->returnQuery = array_filter([
            'q' => request()->query('q'),
            'tipo' => request()->query('tipo'),
            'page' => request()->query('page'),
        ], fn ($value) => $value !== null && $value !== '');

        $pokemon = Pokemon::query()->where('slug', $slug)->with('types')->first();

        if ($pokemon !== null) {
            $this->foundLocally = true;
            $this->applyHeaderFromLocal($pokemon);

            return;
        }

        // No local row to show while a lazy round-trip would be in flight,
        // so the existence check (found upstream / not found / unavailable)
        // has to resolve synchronously before anything renders.
        $this->loadDetail();
    }

    /**
     * Wired to wire:init for a locally-found Pokémon; reused directly by
     * mount() for the local-miss branch and by retry() for both branches.
     * Always ends with detailLoaded = true so the caller can stop showing a
     * skeleton regardless of outcome.
     */
    public function loadDetail(): void
    {
        if ($this->detailLoaded) {
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
    public function backUrl(): string
    {
        $url = route('dashboard');

        return $this->returnQuery === [] ? $url : $url.'?'.http_build_query($this->returnQuery);
    }

    #[Computed]
    public function statRows(): array
    {
        return collect(self::STAT_LABELS)
            ->map(function (string $label, string $key): array {
                $value = $this->stats[$key] ?? 0;

                return [
                    'label' => $label,
                    'value' => $value,
                    'percent' => (int) round(min(255, max(0, $value)) / 255 * 100),
                    'color' => match (true) {
                        $value < 60 => 'bg-red-500',
                        $value < 100 => 'bg-yellow-500',
                        default => 'bg-green-500',
                    },
                ];
            })
            ->values()
            ->all();
    }

    private function applyHeaderFromLocal(Pokemon $pokemon): void
    {
        $this->number = $pokemon->number;
        $this->name = $pokemon->name;
        $this->spriteUrl = $pokemon->sprite_url;
        $this->typeBadges = $pokemon->types
            ->map(fn ($type) => [
                'label' => ucfirst($type->label_pt),
                'color' => config("pokemon.type_colors.{$type->slug}", 'gray'),
            ])
            ->all();
    }

    private function applyHeaderFromUpstream(array $data): void
    {
        $this->number = $data['number'] ?? null;
        $this->name = $data['name'] ?? $this->slug;
        $this->spriteUrl = $data['sprite_url'] ?? null;
        $this->typeBadges = collect($data['types'] ?? [])
            ->map(fn (string $typeSlug) => [
                'label' => ucfirst(config("pokemon.type_labels.{$typeSlug}", $typeSlug)),
                'color' => config("pokemon.type_colors.{$typeSlug}", 'gray'),
            ])
            ->all();
    }

    private function applyDetailFromPayload(array $data): void
    {
        $this->abilities = collect($data['abilities'] ?? [])
            ->map(fn (array $ability) => [
                'name' => ucfirst(str_replace('-', ' ', (string) ($ability['name'] ?? ''))),
                'hidden' => (bool) ($ability['hidden'] ?? false),
            ])
            ->all();

        $this->stats = $data['stats'] ?? [];
        $this->heightM = $data['height_m'] ?? null;
        $this->weightKg = $data['weight_kg'] ?? null;
        $this->flavorText = $data['flavor_text'] ?? null;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight capitalize">
            {{ $name !== '' ? $name : $slug }}
        </h2>
    </x-slot>

    <div class="mb-4">
        <a href="{{ $this->backUrl }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
            &larr; Voltar aos resultados
        </a>
    </div>

    @if ($notFound)
        <x-card>
            <x-empty-state message="Pokémon não encontrado.">
                <x-slot name="action">
                    <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        Buscar Pokémon
                    </a>
                </x-slot>
            </x-empty-state>
        </x-card>
    @elseif ($headerUnavailable)
        <x-card>
            <x-empty-state message="Não foi possível carregar os detalhes agora. O serviço de dados está temporariamente indisponível.">
                <x-slot name="action">
                    <x-secondary-button wire:click="retry">
                        Tentar novamente
                    </x-secondary-button>
                </x-slot>
            </x-empty-state>
        </x-card>
    @else
        <div @if ($foundLocally && ! $detailLoaded) wire:init="loadDetail" @endif class="space-y-6">
            <x-card>
                <div class="flex flex-col gap-6 md:flex-row">
                    <div x-data="{ broken: false }" class="mx-auto w-full max-w-[19rem] md:mx-0 md:w-[19rem]">
                        <img
                            x-show="! broken"
                            x-on:error="broken = true"
                            src="{{ $spriteUrl }}"
                            alt="{{ $name }}"
                            class="mx-auto h-auto w-full max-w-[475px] object-contain"
                        />
                        <svg
                            x-cloak
                            x-show="broken"
                            class="mx-auto h-full w-full text-gray-300"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M12 3c-3.31 0-6 2.69-6 6 0 2.22 1.21 4.15 3 5.19V16a1 1 0 001 1h4a1 1 0 001-1v-1.81c1.79-1.04 3-2.97 3-5.19 0-3.31-2.69-6-6-6zM9 20a1 1 0 001 1h4a1 1 0 001-1v-1H9v1z" />
                        </svg>
                    </div>

                    <div class="flex-1">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm text-gray-500">#{{ str_pad((string) $number, 4, '0', STR_PAD_LEFT) }}</p>
                                <h1 class="text-2xl font-bold capitalize text-gray-900">{{ $name }}</h1>
                            </div>

                            <div data-favorite-slot></div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach ($typeBadges as $badge)
                                <x-badge :color="$badge['color']">{{ $badge['label'] }}</x-badge>
                            @endforeach
                        </div>

                        @if ($flavorText)
                            <p class="mt-4 text-sm text-gray-600">{{ $flavorText }}</p>
                        @endif

                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Altura</dt>
                                <dd class="font-medium text-gray-900">
                                    {{ $heightM !== null ? number_format($heightM, 1, ',', '.').' m' : 'Informação indisponível.' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Peso</dt>
                                <dd class="font-medium text-gray-900">
                                    {{ $weightKg !== null ? number_format($weightKg, 1, ',', '.').' kg' : 'Informação indisponível.' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </x-card>

            @if (! $detailLoaded)
                <x-card>
                    <div class="animate-pulse space-y-4">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="h-4 w-full rounded bg-gray-200"></div>
                        @endfor
                    </div>
                </x-card>
            @elseif ($detailUnavailable)
                <x-card>
                    <x-empty-state message="Não foi possível carregar os detalhes agora. O serviço de dados está temporariamente indisponível.">
                        <x-slot name="action">
                            <x-secondary-button wire:click="retry">
                                Tentar novamente
                            </x-secondary-button>
                        </x-slot>
                    </x-empty-state>
                </x-card>
            @else
                <x-card>
                    <h3 class="font-semibold text-gray-900">Habilidades</h3>

                    @if (empty($abilities))
                        <p class="mt-2 text-sm text-gray-600">Informação indisponível.</p>
                    @else
                        <ul class="mt-2 space-y-1 text-sm text-gray-700">
                            @foreach ($abilities as $ability)
                                <li>
                                    <span class="capitalize">{{ $ability['name'] }}</span>
                                    @if ($ability['hidden'])
                                        <span class="text-gray-400">(oculta)</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-card>
                    <h3 class="font-semibold text-gray-900">Estatísticas base</h3>

                    @if (empty($stats))
                        <p class="mt-2 text-sm text-gray-600">Informação indisponível.</p>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($this->statRows as $row)
                                <div>
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span>{{ $row['label'] }}</span>
                                        <span>{{ $row['value'] }}</span>
                                    </div>
                                    <div class="mt-1 h-2 w-full rounded-full bg-gray-200">
                                        <div class="h-2 rounded-full {{ $row['color'] }}" style="width: {{ $row['percent'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endif
        </div>
    @endif
</div>
