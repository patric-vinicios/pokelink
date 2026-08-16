@props(['message'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-16 px-6']) }}>
    @isset($icon)
        <div class="mb-4 text-gray-400">
            {{ $icon }}
        </div>
    @endisset

    <p class="text-gray-600">{{ $message }}</p>

    @isset($action)
        <div class="mt-6">
            {{ $action }}
        </div>
    @endisset
</div>
