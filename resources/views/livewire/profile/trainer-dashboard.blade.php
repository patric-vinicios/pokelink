<?php

use App\Models\Pokemon;
use App\Models\Type;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    #[On('profile-updated')]
    public function refreshProfile(string $name): void
    {
        // Re-render the overview after the account form saves the new name.
    }

    #[Computed]
    public function favoriteCount(): int
    {
        return auth()->user()->favorites()->count();
    }

    #[Computed]
    public function favoriteTypeCount(): int
    {
        return Type::query()
            ->join('pokemon_type', 'types.id', '=', 'pokemon_type.type_id')
            ->join('favorites', 'favorites.pokemon_number', '=', 'pokemon_type.pokemon_number')
            ->where('favorites.user_id', auth()->id())
            ->distinct()
            ->count('types.id');
    }

    #[Computed]
    public function catalogCount(): int
    {
        return Pokemon::query()->count();
    }

    #[Computed]
    public function latestFavoriteNumber(): string
    {
        $number = auth()->user()->favorites()
            ->orderByDesc('favorites.created_at')
            ->value('pokemon.number');

        return $number === null
            ? '—'
            : '#'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    #[Computed]
    public function favoriteType(): ?Type
    {
        return Type::query()
            ->select('types.*')
            ->join('pokemon_type', 'types.id', '=', 'pokemon_type.type_id')
            ->join('favorites', 'favorites.pokemon_number', '=', 'pokemon_type.pokemon_number')
            ->where('favorites.user_id', auth()->id())
            ->groupBy('types.id', 'types.slug', 'types.label_pt', 'types.created_at', 'types.updated_at')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('types.label_pt')
            ->first();
    }

    /** @return Collection<int, Pokemon> */
    #[Computed]
    public function favoriteShowcase(): Collection
    {
        return auth()->user()->favorites()
            ->with('types')
            ->orderByDesc('favorites.created_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function trainerId(): string
    {
        return '#'.str_pad((string) auth()->id(), 4, '0', STR_PAD_LEFT);
    }

    #[Computed]
    public function journeyDays(): int
    {
        return max(1, (int) auth()->user()->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
    }
}; ?>

<x-pokedex.hero
    class="trainer-profile-page"
    eyebrow=""
    title="Seu perfil de"
    highlight="treinador"
    :inline-title="true"
    stats-label="Resumo do treinador"
>
    <x-slot name="copy">
        Gerencie suas informações, insígnias e equipe favorita<br class="hidden sm:block">
        e acompanhe sua jornada como treinador.
    </x-slot>

    <x-slot name="stats">
        <x-pokedex.stat
            tone="red"
            :value="number_format($this->catalogCount, 0, ',', '.')"
            label="Pokémon na Pokédex"
        >
            <x-slot name="icon">
                <span class="mini-pokeball mini-pokeball--white"></span>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat tone="gold" :value="$this->favoriteCount" label="Favoritos">
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" />
                </svg>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat tone="purple" :value="$this->favoriteTypeCount" label="Tipos favoritos">
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" />
                    <circle cx="12" cy="10" r="2" />
                </svg>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat tone="blue" :value="$this->latestFavoriteNumber" label="Último favorito">
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <rect x="4" y="4" width="6" height="6" rx="1" />
                    <rect x="14" y="4" width="6" height="6" rx="1" />
                    <rect x="4" y="14" width="6" height="6" rx="1" />
                    <rect x="14" y="14" width="6" height="6" rx="1" />
                </svg>
            </x-slot>
        </x-pokedex.stat>
    </x-slot>

    <div class="profile-dashboard">
        <section class="profile-overview" aria-labelledby="profile-trainer-name">
            <div class="profile-overview-identity">
                <div class="profile-overview-avatar" aria-hidden="true">
                    <span>{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <i class="mini-pokeball"></i>
                </div>

                <div>
                    <h2 id="profile-trainer-name">{{ auth()->user()->name }}</h2>
                    <p>Treinador</p>
                    <small>ID de treinador <strong>{{ $this->trainerId }}</strong></small>
                </div>
            </div>

            <div class="profile-overview-facts">
                <div>
                    <span>Membro desde</span>
                    <strong>{{ auth()->user()->created_at->format('d/m/Y') }}</strong>
                </div>
                <div>
                    <span>Jornada</span>
                    <strong>{{ $this->journeyDays }} {{ $this->journeyDays === 1 ? 'dia' : 'dias' }}</strong>
                </div>
                <div>
                    <span>Afinidade</span>
                    <strong>{{ $this->favoriteType ? Str::ucfirst($this->favoriteType->label_pt) : 'Ainda não definida' }}</strong>
                </div>
            </div>

            <p class="profile-overview-note">
                @if ($this->favoriteCount > 0)
                    Sua coleção reúne {{ $this->favoriteCount }} {{ $this->favoriteCount === 1 ? 'Pokémon favorito' : 'Pokémon favoritos' }}
                    de {{ $this->favoriteTypeCount }} {{ $this->favoriteTypeCount === 1 ? 'tipo diferente' : 'tipos diferentes' }}.
                @else
                    Favorite seus primeiros Pokémon para começar a personalizar seu perfil de treinador.
                @endif
            </p>
        </section>

        <div class="profile-dashboard-grid">
            <section class="profile-dashboard-section profile-personal-section" aria-labelledby="profile-personal-title">
                <header>
                    <div>
                        <span>Conta</span>
                        <h2 id="profile-personal-title">Informações pessoais</h2>
                    </div>
                </header>

                <div id="profile-inline-editor" class="profile-inline-editor">
                    <livewire:profile.update-profile-information-form />

                    <div class="profile-info-list profile-info-list--account">
                        <x-profile.info-row label="E-mail" :value="auth()->user()->email">
                            <x-slot name="icon">
                                <svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4" width="15" height="12" rx="2" /><path d="m3.5 6 6.5 5 6.5-5" /></svg>
                            </x-slot>
                        </x-profile.info-row>

                        <x-profile.info-row label="Membro desde" :value="auth()->user()->created_at->format('d/m/Y')">
                            <x-slot name="icon">
                                <svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4" width="15" height="13.5" rx="2" /><path d="M6 2.5v3M14 2.5v3M2.5 8h15" /></svg>
                            </x-slot>
                        </x-profile.info-row>

                        <x-profile.info-row label="Tipo favorito" :value="$this->favoriteType ? Str::ucfirst($this->favoriteType->label_pt) : 'Ainda não definido'">
                            <x-slot name="icon">
                                @if ($this->favoriteType)
                                    <x-pokedex.type-icon :slug="$this->favoriteType->slug" :size="16" />
                                @else
                                    <span class="mini-pokeball"></span>
                                @endif
                            </x-slot>
                        </x-profile.info-row>
                    </div>

                    <livewire:profile.update-password-form />
                </div>
            </section>

            <section class="profile-dashboard-section profile-favorites-section" aria-labelledby="profile-favorites-title">
                <header>
                    <div>
                        <span>Coleção</span>
                        <h2 id="profile-favorites-title">Seus Pokémon favoritos</h2>
                    </div>
                    <a href="{{ route('favoritos') }}" wire:navigate>Ver todos</a>
                </header>

                @if ($this->favoriteShowcase->isNotEmpty())
                    <div class="profile-favorites-grid">
                        @foreach ($this->favoriteShowcase as $pokemon)
                            <x-profile.favorite-pokemon
                                :pokemon="$pokemon"
                                wire:key="profile-favorite-{{ $pokemon->number }}"
                            />
                        @endforeach
                    </div>
                @else
                    <div class="profile-favorites-empty">
                        <span class="mini-pokeball" aria-hidden="true"></span>
                        <p>Você ainda não selecionou nenhum favorito.</p>
                        <a href="{{ route('dashboard') }}" wire:navigate>Explorar Pokédex</a>
                    </div>
                @endif
            </section>
        </div>

    </div>
</x-pokedex.hero>
