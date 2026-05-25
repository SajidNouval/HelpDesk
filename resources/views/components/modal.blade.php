@props([
    'id' => 'modal',
    'title' => '',
    'subtitle' => '',
    'size' => 'md', // sm, md, lg, xl
    'showCloseButton' => true,
    'closeOnBackdrop' => true,
])

@php
    $sizeClasses = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $modalSize = $sizeClasses[$size] ?? $sizeClasses['md'];
@endphp

<div id="{{ $id }}" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full {{ $modalSize }} max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl">
            <div>
                @if($title)
                    <h2 class="text-xl font-semibold text-gray-900">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="text-sm text-gray-600 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if($showCloseButton)
                <x-secondary-button type="button" data-close-modal class="text-gray-400 hover:text-gray-600 p-0 w-8 h-8 flex items-center justify-center rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </x-secondary-button>
            @endif
        </div>

        <!-- Content -->
        <div {{ $attributes->except(['class']) }}>
            {{ $slot }}
        </div>
    </div>
</div>