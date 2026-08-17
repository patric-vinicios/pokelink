@props([
    'number',
    'slug',
    'name',
    'types',
    'sprite',
])

@php
    $typeColors = config('pokemon.type_colors', []);
    $primaryType = $types->first()?->slug ?? 'normal';
@endphp

<article
    data-type="{{ $primaryType }}"
    data-pokemon-number="{{ $number }}"
    {{ $attributes->merge(['class' => 'pokemon-card group relative block']) }}
>
    <button
        type="button"
        wire:click="$dispatchTo('pokemon.detail-modal', 'open-pokemon', { slug: '{{ $slug }}' })"
        class="pokemon-card-link"
        aria-label="Ver detalhes de {{ ucfirst($name) }}"
    ></button>

    <span class="pokemon-card-number">
        <span
            class="pokemon-card-type-icon"
            style="--pokemon-card-type-icon: url('{{ asset('images/icons/types/glyphs/'.$primaryType.'.svg') }}')"
            aria-hidden="true"
        ></span>
        #{{ str_pad((string) $number, 4, '0', STR_PAD_LEFT) }}
    </span>

    <div class="pokemon-card-favorite">
        {{ $favorite ?? '' }}
    </div>

    <svg
        class="pokemon-card-decoration"
        viewBox="0 0 140 100"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        <path class="pokemon-card-wave pokemon-card-wave--soft" d="M-8 38C10 29 19 41 34 40C50 39 55 27 72 30C91 33 97 48 116 43C127 40 138 34 148 37V74C128 81 113 68 96 69C76 71 69 83 48 78C28 73 17 61-8 69Z" />
        <path class="pokemon-card-wave pokemon-card-wave--light" d="M-7 54C10 45 22 56 39 53C53 50 62 39 79 43C97 47 105 62 122 60C132 59 140 54 147 50V72C133 75 124 72 112 70C94 67 84 79 66 78C45 77 33 65 17 66C7 66 0 69-7 72Z" />

        <g class="pokemon-card-pokeball">
            <circle cx="115" cy="64" r="24" />
            <path d="M91 64H139" />
            <circle cx="115" cy="64" r="7.5" />
        </g>

    </svg>

    <div x-data="{ broken: false }" class="pokemon-card-art">
        <img
            x-show="! broken"
            x-on:error="broken = true"
            src="{{ $sprite }}"
            alt="{{ $name }}"
            loading="lazy"
        />
        <svg
            x-cloak
            x-show="broken"
            class="pokemon-card-placeholder"
            fill="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M12 3c-3.31 0-6 2.69-6 6 0 2.22 1.21 4.15 3 5.19V16a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1.81c1.79-1.04 3-2.97 3-5.19 0-3.31-2.69-6-6-6ZM9 20a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1H9v1Z" />
        </svg>
    </div>

    <div class="pokemon-card-copy">
        <p class="pokemon-card-name">{{ $name }}</p>

        <div class="pokemon-card-types">
            @foreach ($types as $pokemonType)
                <x-badge
                    :color="$typeColors[$pokemonType->slug] ?? 'gray'"
                    class="pokemon-card-badge"
                    data-type="{{ $pokemonType->slug }}"
                >
                    {{ ucfirst($pokemonType->label_pt) }}
                </x-badge>
            @endforeach
        </div>
    </div>
</article>
