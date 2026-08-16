@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => "bg-white overflow-hidden shadow-sm sm:rounded-lg {$padding}"]) }}>
    {{ $slot }}
</div>
