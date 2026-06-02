@props(['status'])

@php
    $color = match($status) {
        'progress' => 'bg-red-100 text-red-800',
        'assigned' => 'bg-red-100 text-red-800',
        'waiting', 'pending' => 'bg-yellow-100 text-yellow-800',
        'closed', 'rejected' => 'bg-red-100 text-red-800',
        'approved' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $color }}">
    {{ ucfirst($status) }}
</span>
