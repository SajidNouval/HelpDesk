@props([
    'type' => 'text', // text, circle, rect, card, table-row, article
    'count' => 1,
    'size' => 'md', // sm, md, lg
    'animated' => true
])

@php
    $animationClass = $animated ? 'animate-pulse' : '';
    
    $sizeClasses = [
        'sm' => [
            'text' => 'h-3',
            'circle' => 'w-8 h-8',
            'rect' => 'h-8',
        ],
        'md' => [
            'text' => 'h-4',
            'circle' => 'w-10 h-10',
            'rect' => 'h-10',
        ],
        'lg' => [
            'text' => 'h-5',
            'circle' => 'w-12 h-12',
            'rect' => 'h-12',
        ],
    ];
    $currentSize = $sizeClasses[$size] ?? $sizeClasses['md'];
@endphp

@for($i = 0; $i < $count; $i++)
    @if($type === 'text')
        <div class="{{ $currentSize['text'] }} bg-gray-200 rounded {{ $animationClass }}" style="width: {{ $i === $count - 1 ? '60%' : '100%' }};"></div>
    @elseif($type === 'circle')
        <div class="rounded-full bg-gray-200 {{ $currentSize['circle'] }} {{ $animationClass }}"></div>
    @elseif($type === 'rect')
        <div class="bg-gray-200 rounded {{ $currentSize['rect'] }} w-full {{ $animationClass }}"></div>
    @elseif($type === 'card')
        <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3 {{ $animationClass }}">
            <div class="flex items-center space-x-3">
                <div class="rounded-full bg-gray-200 {{ $currentSize['circle'] }}"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
            </div>
            <div class="space-y-2">
                <div class="h-3 bg-gray-200 rounded w-full"></div>
                <div class="h-3 bg-gray-200 rounded w-5/6"></div>
                <div class="h-3 bg-gray-200 rounded w-4/6"></div>
            </div>
        </div>
    @elseif($type === 'table-row')
        <tr class="border-b border-gray-100">
            <td class="px-6 py-4">
                <div class="flex items-center space-x-3">
                    <div class="rounded-full bg-gray-200 w-8 h-8 {{ $animationClass }}"></div>
                    <div class="space-y-2 flex-1">
                        <div class="h-4 bg-gray-200 rounded w-3/4 {{ $animationClass }}"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2 {{ $animationClass }}"></div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="h-4 bg-gray-200 rounded w-full {{ $animationClass }}"></div>
            </td>
            <td class="px-6 py-4">
                <div class="h-4 bg-gray-200 rounded w-2/3 {{ $animationClass }}"></div>
            </td>
            <td class="px-6 py-4">
                <div class="h-4 bg-gray-200 rounded w-1/2 {{ $animationClass }}"></div>
            </td>
        </tr>
    @elseif($type === 'article')
        <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4 {{ $animationClass }}">
            <div class="flex items-start justify-between">
                <div class="flex-1 space-y-2">
                    <div class="h-5 bg-gray-200 rounded w-3/4"></div>
                    <div class="flex items-center space-x-4">
                        <div class="h-3 bg-gray-200 rounded w-24"></div>
                        <div class="h-3 bg-gray-200 rounded w-20"></div>
                        <div class="h-3 bg-gray-200 rounded w-16"></div>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <div class="h-3 bg-gray-200 rounded w-full"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
                <div class="h-3 bg-gray-200 rounded w-2/3"></div>
            </div>
        </div>
    @endif
@endfor