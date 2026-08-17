@props([
    'tone',
    'value',
    'label',
])

<article {{ $attributes->class(['catalog-stat', 'catalog-stat--'.$tone]) }}>
    <span class="catalog-stat-icon" aria-hidden="true">
        {{ $icon }}
    </span>

    <span>
        <strong>{{ $value }}</strong>
        <small>{{ $label }}</small>
    </span>
</article>
