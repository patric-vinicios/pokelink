@props([
    'slug',
    'size' => 16,
])

<img
    src="{{ asset('images/icons/types/glyphs/'.$slug.'.svg') }}"
    alt=""
    width="{{ $size }}"
    height="{{ $size }}"
    aria-hidden="true"
    {{ $attributes }}
>
