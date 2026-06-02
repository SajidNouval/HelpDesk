@props([
    'id' => 'confirm-dialog',
    'title' => 'Konfirmasi',
    'message' => '',
    'primaryText' => 'Konfirmasi',
    'secondaryText' => 'Batal',
])

<div id="{{ $id }}" data-confirm-dialog class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-sm">
        <!-- Icon & Content -->
        <div class="p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-12 w-12 rounded-2xl bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                    @if($message)
                        <p class="mt-2 text-sm text-gray-600">{{ $message }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Slot for additional content -->
        @if($slot->isNotEmpty())
            <div class="px-6">
                {{ $slot }}
            </div>
        @endif

        <!-- Actions -->
        <div class="px-6 py-4 bg-gray-50 rounded-b-2xl border-t border-gray-100 flex gap-3 justify-end">
            <button type="button" data-confirm-cancel class="inline-flex items-center justify-center rounded-2xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:border-gray-400 hover:bg-gray-50 transition">
                {{ $secondaryText }}
            </button>
            <button type="button" data-confirm-submit class="inline-flex items-center justify-center rounded-2xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 transition focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                {{ $primaryText }}
            </button>
        </div>
    </div>
</div>
