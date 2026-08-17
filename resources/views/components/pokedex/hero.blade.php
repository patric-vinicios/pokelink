@props([
    'eyebrow',
    'title' => null,
    'highlight' => null,
    'inlineTitle' => false,
    'statsColumns' => 4,
    'statsLabel' => 'Resumo da página',
])

<div {{ $attributes->class(['pokedex-page']) }}>
    <section @class(['catalog-hero', 'catalog-hero--inline-title' => $inlineTitle])>
        <div class="catalog-hero-glow" aria-hidden="true"></div>

        <div class="catalog-hero-content">
            <p class="catalog-welcome">
                <span class="mini-pokeball" aria-hidden="true"></span>
                {{ $eyebrow }}
            </p>

            <h1>
                @isset($headline)
                    {{ $headline }}
                @else
                    {{ $title }} <span>{{ $highlight }}</span>
                @endisset
            </h1>

            <p class="catalog-hero-copy">
                {{ $copy }}
            </p>
        </div>
    </section>

    <div class="catalog-content">
        @isset($stats)
            <section class="catalog-stats catalog-stats--{{ $statsColumns }}" aria-label="{{ $statsLabel }}">
                {{ $stats }}
            </section>
        @endisset

        {{ $slot }}
    </div>
</div>
