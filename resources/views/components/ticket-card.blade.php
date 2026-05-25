@props([
    'ticket',
    'showStatus' => true,
    'showPriority' => false,
    'showWorkTime' => false,
    'href' => null,
    'target' => '_self',
])

@php
    $borderColor = match($ticket->status) {
        'closed' => 'border-green-500',
        'progress' => 'border-blue-500',
        'assigned' => 'border-indigo-500',
        'waiting' => 'border-orange-500',
        default => 'border-yellow-500',
    };
    
    $statusColor = match($ticket->status) {
        'closed' => 'bg-green-100 text-green-800',
        'progress' => 'bg-blue-100 text-blue-800',
        'assigned' => 'bg-indigo-100 text-indigo-800',
        'waiting' => 'bg-orange-100 text-orange-800',
        default => 'bg-yellow-100 text-yellow-800',
    };
    
    $statusLabel = match($ticket->status) {
        'closed' => 'Selesai',
        'progress' => 'Dalam Progress',
        'assigned' => 'Ditugaskan',
        'waiting' => 'Menunggu',
        default => ucfirst($ticket->status),
    };
    
    $wrapperClass = $href ? 'a' : 'div';
@endphp

<{{ $wrapperClass }} 
    @if($href) href="{{ $href }}" @endif
    target="{{ $target }}"
    {{ $attributes->merge(['class' => "w-full block text-left bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden border-l-4 $borderColor"]) }}
>
    <div class="p-4">
        <!-- Header: Title + Status -->
        <div class="flex justify-between items-start mb-3">
            <div class="flex-1">
                <h4 class="font-semibold text-gray-900 text-sm">#{{ $ticket->id }} - {{ $ticket->subject }}</h4>
                <p class="text-xs text-gray-600 mt-1">{{ $ticket->category->name }}</p>
            </div>
            <div class="text-right">
                @if($showStatus)
                    <span class="inline-block text-xs px-2 py-1 rounded-full font-medium {{ $statusColor }}">
                        {{ $statusLabel }}
                    </span>
                @endif
                @if($ticket->created_at)
                    <p class="text-xs text-gray-500 mt-1">{{ $ticket->created_at->diffForHumans() }}</p>
                @endif
            </div>
        </div>

        <!-- Message Preview -->
        <p class="text-sm text-gray-700 mb-2 leading-relaxed">{{ Str::limit($ticket->message, 120) }}</p>

        <!-- Additional Info Row -->
        @if($showWorkTime || $showPriority)
            <div class="flex flex-wrap gap-4 text-xs pt-2 border-t border-gray-200">
                @if($showWorkTime && $ticket->closed_at && $ticket->assigned_at)
                    <span class="text-gray-600">
                        Waktu kerja: {{ $ticket->closed_at->diffInHours($ticket->assigned_at) }} jam
                    </span>
                @endif
                @if($showPriority)
                    <span class="text-gray-600">
                        Priority: 
                        <x-priority-badge :priority="$ticket->priority" />
                    </span>
                @endif
                @if($ticket->status === 'closed' && $ticket->closed_at)
                    <span class="text-gray-500">
                        Ditutup: {{ $ticket->closed_at->format('d M Y, H:i') }}
                    </span>
                @endif
            </div>
        @endif
    </div>
</{{ $wrapperClass }}>