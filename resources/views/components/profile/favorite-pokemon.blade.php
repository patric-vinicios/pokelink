@props(['pokemon'])

@php($primaryType = $pokemon->types->first())

<article {{ $attributes->class(['profile-favorite-pokemon']) }} data-type="{{ $primaryType?->slug ?? 'normal' }}">
    <span class="profile-favorite-number">#{{ str_pad((string) $pokemon->number, 4, '0', STR_PAD_LEFT) }}</span>

    <div class="profile-favorite-art">
        @if ($pokemon->sprite_url)
            <img src="{{ $pokemon->sprite_url }}" alt="{{ ucfirst($pokemon->name) }}" loading="lazy">
        @else
            <span aria-hidden="true" class="mini-pokeball"></span>
        @endif
    </div>

    <div class="profile-favorite-copy">
            <strong>{{ Illuminate\Support\Str::ucfirst($pokemon->name) }}</strong>

        @if ($primaryType)
            <span class="profile-favorite-type" data-type="{{ $primaryType->slug }}">
                <x-pokedex.type-icon :slug="$primaryType->slug" :size="11" />
                {{ Illuminate\Support\Str::ucfirst($primaryType->label_pt) }}
            </span>
        @endif
    </div>
</article>
