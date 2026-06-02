@props([
    'id' => 'toast-' . uniqid(),
    'type' => 'success', // success, error, info
    'message' => '',
    'duration' => 4000,
])

@php
    $typeClasses = [
        'success' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'info' => 'bg-blue-50 text-blue-800 border-blue-200',
        'warning' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
    ];
    $iconClass = [
        'success' => 'text-green-500',
        'error' => 'text-red-500',
        'info' => 'text-blue-500',
        'warning' => 'text-yellow-500',
    ];
    $bgClass = $typeClasses[$type] ?? $typeClasses['info'];
    $iconColor = $iconClass[$type] ?? $iconClass['info'];
@endphp

<div id="{{ $id }}" class="pointer-events-auto w-full max-w-sm rounded-2xl border shadow-lg {{ $bgClass }}" role="alert">
    <div class="flex gap-3 p-4">
        <div class="flex-shrink-0 pt-0.5">
            @if($type === 'success')
                <svg class="h-5 w-5 {{ $iconColor }}" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            @elseif($type === 'error')
                <svg class="h-5 w-5 {{ $iconColor }}" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            @else
                <svg class="h-5 w-5 {{ $iconColor }}" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            @endif
        </div>
        <div class="flex-1">
            <p class="text-sm font-medium">{{ $message }}</p>
        </div>
        <button type="button" data-toast-close class="ml-auto inline-flex text-current opacity-50 hover:opacity-75 transition">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
</div>

@if($duration > 0)
<script>
    const toastId = '{{ $id }}';
    const toastEl = document.getElementById(toastId);
    if (toastEl) {
        setTimeout(() => {
            toastEl.style.animation = 'fadeOut 300ms ease-out forwards';
            setTimeout(() => toastEl.remove(), 300);
        }, {{ $duration }});
    }
</script>
@endif
