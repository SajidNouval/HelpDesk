@props(['priority'])

@php
    $colorClasses = match($priority) {
        'low' => 'bg-gray-100 text-gray-700',
        'medium' => 'bg-yellow-100 text-yellow-800',
        'high' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
    
    $label = match($priority) {
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        default => ucfirst($priority ?? 'low'),
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold {{ $colorClasses }}">
    {{ $label }}
</span>