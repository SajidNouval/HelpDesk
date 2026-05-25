@props([
    'visible' => true,
    'text' => 'Sedang mengetik...'
])

<div class="typing-indicator-container {{ $visible ? '' : 'hidden' }}" id="typing-indicator">
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <div class="typing-dots flex items-center gap-1">
            <span class="typing-dot"></span>
            <span class="typing-dot"></span>
            <span class="typing-dot"></span>
        </div>
        <span class="typing-text">{{ $text }}</span>
    </div>
</div>

<style>
    .typing-indicator-container {
        padding: 8px 12px;
        background: #f3f4f6;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
    }

    .typing-dots {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background: #9ca3af;
        border-radius: 50%;
        animation: typingBounce 1.4s infinite ease-in-out both;
    }

    .typing-dot:nth-child(1) {
        animation-delay: -0.32s;
    }

    .typing-dot:nth-child(2) {
        animation-delay: -0.16s;
    }

    @keyframes typingBounce {
        0%, 80%, 100% {
            transform: scale(0.8);
            opacity: 0.5;
        }
        40% {
            transform: scale(1);
            opacity: 1;
        }
    }
</style>