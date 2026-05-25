@props([
    'loading' => false,
    'loadingText' => 'Memproses...',
    'disabled' => false,
])

<button 
    {{ $attributes->merge(['type' => 'submit']) }}
    {{ ($loading || $disabled) ? 'disabled' : '' }}
    class="{{ $attributes->get('class', '') }} {{ $loading || $disabled ? 'opacity-70 cursor-not-allowed' : '' }} inline-flex items-center justify-center gap-2"
>
    @if($loading)
        <x-spinner size="sm" :color="str_contains($attributes->get('class', ''), 'bg-') && str_contains($attributes->get('class', ''), 'white') ? 'white' : 'indigo'" />
        <span>{{ $loadingText }}</span>
    @else
        {{ $slot }}
    @endif
</button>