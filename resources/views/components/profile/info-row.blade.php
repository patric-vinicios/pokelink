@props([
    'label',
    'value',
])

<div {{ $attributes->class(['profile-info-row']) }}>
    <span class="profile-info-row-icon" aria-hidden="true">
        {{ $icon }}
    </span>

    <span class="profile-info-row-label">{{ $label }}</span>
    <strong>{{ $value }}</strong>
</div>
