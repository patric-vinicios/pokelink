@props([
    'number',
    'slug',
    'name',
    'types',
    'sprite',
    'search' => null,
    'type' => null,
    'page' => null,
])

@php
    $href = route('pokemon.show', ['slug' => $slug]);

    $returnQuery = array_filter([
        'q' => $search,
        'tipo' => $type,
        'page' => $page,
    ], fn ($value) => $value !== null && $value !== '');

    if ($returnQuery !== []) {
        $href .= '?'.http_build_query($returnQuery);
    }

    $typeColors = config('pokemon.type_colors', []);
@endphp

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->merge(['class' => 'group relative block rounded-lg bg-white p-4 shadow-sm transition hover:-translate-y-1 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2']) }}
>
    <div class="absolute right-2 top-2">
        {{ $favorite ?? '' }}
    </div>

    <div x-data="{ broken: false }" class="mx-auto aspect-square w-full max-w-[7rem]">
        <img
            x-show="! broken"
            x-on:error="broken = true"
            src="{{ $sprite }}"
            alt="{{ $name }}"
            loading="lazy"
            class="mx-auto h-full w-full object-contain"
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

    <p class="mt-2 text-center text-xs text-gray-500">
        #{{ str_pad((string) $number, 4, '0', STR_PAD_LEFT) }}
    </p>
    <p class="text-center font-medium capitalize text-gray-900">
        {{ $name }}
    </p>

    <div class="mt-2 flex flex-wrap justify-center gap-1">
        @foreach ($types as $pokemonType)
            <x-badge :color="$typeColors[$pokemonType->slug] ?? 'gray'">
                {{ ucfirst($pokemonType->label_pt) }}
            </x-badge>
        @endforeach
    </div>
</a>
