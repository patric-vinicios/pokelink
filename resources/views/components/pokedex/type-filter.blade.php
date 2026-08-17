@props([
    'types',
    'selected' => '',
    'model' => 'type',
    'id' => 'type',
    'label' => 'Filtrar por tipo',
])

<div {{ $attributes->class(['catalog-type-field']) }}>
    <div class="catalog-filter-heading">
        <label for="{{ $id }}">{{ $label }}</label>

        @isset($action)
            {{ $action }}
        @endisset
    </div>

    <select wire:model.live="{{ $model }}" id="{{ $id }}" class="catalog-type-select sm:hidden">
        <option value="">Todos os tipos</option>
        @foreach ($types as $option)
            <option value="{{ $option->label_pt }}">{{ ucfirst($option->label_pt) }}</option>
        @endforeach
    </select>

    <div class="catalog-type-pills hidden sm:flex" role="group" aria-label="{{ $label }}">
        <button
            type="button"
            wire:click="$set(@js($model), '')"
            class="{{ $selected === '' ? 'is-active' : '' }}"
            data-type="all"
            title="Todos os tipos"
            aria-label="Todos os tipos"
            aria-pressed="{{ $selected === '' ? 'true' : 'false' }}"
        >
            <span class="catalog-all-types-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <rect x="4" y="4" width="6" height="6" rx="1" />
                    <rect x="14" y="4" width="6" height="6" rx="1" />
                    <rect x="4" y="14" width="6" height="6" rx="1" />
                    <rect x="14" y="14" width="6" height="6" rx="1" />
                </svg>
            </span>
        </button>

        @foreach ($types as $option)
            <button
                type="button"
                wire:click="$set(@js($model), @js($option->label_pt))"
                class="{{ $selected === $option->label_pt ? 'is-active' : '' }}"
                data-type="{{ $option->slug }}"
                title="{{ ucfirst($option->label_pt) }}"
                aria-label="Tipo {{ $option->label_pt }}"
                aria-pressed="{{ $selected === $option->label_pt ? 'true' : 'false' }}"
            >
                <x-pokedex.type-icon :slug="$option->slug" />
            </button>
        @endforeach
    </div>
</div>
