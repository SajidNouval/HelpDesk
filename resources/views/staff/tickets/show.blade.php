<x-app-layout>
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Tiket #{{ $ticket->id }} 
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('staff.tickets.index') }}" class="text-red-500 hover:text-red-600 font-medium">Tiket Saya</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">#{{ $ticket->id }} </span>
            </div>
        </div>
    </div>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Success Alert -->
            @if (session('success'))
                <x-alert type="success" class="mb-4">
                    {{ session('success') }}
                </x-alert>
            @endif

            <div class="grid gap-8 xl:grid-cols-[1.9fr_0.9fr]">
                <div class="space-y-6">
                    <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-slate-500">Detail Tiket</p>
                                    <h1 class="text-3xl font-semibold text-slate-900">#{{ $ticket->subject }}</h1>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-500">Kategori</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $ticket->category->name }}</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-500">Dibuat</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $ticket->created_at->format('d M y') }}</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-500">Diassign</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $ticket->assigned_at?->format('d M y H:i') ?? '-' }}</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-500">Ditutup</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $ticket->closed_at?->format('d M y H:i') ?? '-' }}</p>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6 mb-6">
                                <h2 class="text-lg font-semibold text-slate-900 mb-3">Informasi</h2>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="text-sm text-slate-500">Nama</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $ticket->name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-slate-500">Email</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $ticket->email }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-6 mb-6">
                                <h2 class="text-lg font-semibold text-slate-900 mb-3">Pesan Tiket</h2>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                    <p class="text-slate-900 whitespace-pre-wrap">{{ $ticket->message }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($ticket->status === 'progress')
                        <div id="chat-container" class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 flex flex-col h-[520px]">
                                <div class="mb-4">
                                    <h3 class="text-xl font-semibold text-slate-900">Pesan</h3>
                                </div>

                                <div id="messages-container" class="flex-1 overflow-y-auto rounded-3xl border border-slate-200 bg-slate-50 p-4 mb-4 space-y-3">
                                    <div id="messages-list">
                                        @foreach ($ticket->messages->sortByDesc('created_at') as $message)
                                            <div class="mb-3 {{ $message->sender_type === 'staff' ? 'text-right' : 'text-left' }}">
                                                <div class="text-xs text-slate-500 mb-1">
                                                    @if ($message->sender_type === 'staff')
                                                        {{ $message->sender?->name ?? 'Staff' }}
                                                    @elseif (in_array($message->sender_type, ['guest', 'customer']))
                                                        Guest
                                                    @else
                                                        System
                                                    @endif
                                                </div>
                                                <div class="inline-block max-w-full px-4 py-3 rounded-2xl {{ $message->sender_type === 'staff' ? 'bg-blue-600 text-white' : 'bg-white text-slate-900 border border-slate-200' }}">
                                                    <p class="text-sm leading-relaxed">{{ $message->message }}</p>
                                                    <p class="text-xs opacity-75 mt-2">{{ $message->created_at->format('H:i') }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <form id="message-form" class="flex gap-3 border-t border-slate-200 pt-4">
                                    <input type="text" id="message-input" name="message" placeholder="Ketik pesan..."
                                        class="flex-1 rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                                    <x-primary-button type="submit" class="rounded-xl px-5 py-3 font-semibold">Kirim</x-primary-button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-slate-900">Log Progress</h3>
                                </div>
                            </div>

                            @unless ($ticket->status === 'closed')
                                <form id="log-form" action="{{ route('staff.tickets.logs.store', $ticket) }}" method="POST" class="space-y-3 mb-6">
                                    @csrf
                                    <div>
                                        <label for="description" class="text-sm font-semibold text-slate-900"></label>
                                        <textarea name="description" id="description" rows="4" required class="mt-2 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
                                    </div>
                                    <x-primary-button id="log-submit-btn" type="submit" class="rounded-3xl px-6 py-3 font-semibold">Simpan Log</x-primary-button>
                                </form>
                            @else
                                <div class="mb-6 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                    Tiket telah selesai. Log baru tidak dapat ditambahkan, tetapi riwayat log berikut masih dapat dilihat.
                                </div>
                            @endunless

                            <div id="logs-container" class="space-y-3 max-h-[320px] overflow-y-auto pr-2">
                                @forelse ($ticket->logs->sortByDesc('created_at')->take(5) as $log)
                                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                                        <div class="flex items-center justify-between gap-4 mb-2">
                                            <p class="text-sm font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->created_at->format('d M Y, H:i') }}</p>
                                        </div>
                                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $log->description }}</p>
                                    </div>
                                @empty
                                    <div class="rounded-3xl bg-slate-50 p-4 text-sm text-slate-500">
                                        Belum ada log tambahan.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if ($ticket->messages->count() > 0)
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6">
                                <h3 class="text-xl font-semibold text-slate-900 mb-6">Riwayat Chat</h3>
                                <div class="space-y-4 max-h-[420px] overflow-y-auto pr-2">
                                    @foreach ($ticket->messages->sortByDesc('created_at')->take(5) as $message)
                                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                            <p class="text-sm font-semibold text-slate-900">
                                                @if ($message->sender_type === 'staff')
                                                    {{ $message->sender?->name ?? 'Staff' }}
                                                @else
                                                    Guest
                                                @endif
                                            </p>
                                            <p class="text-xs text-slate-500 mb-2">
                                                {{ $message->created_at->format('d M Y, H:i') }}
                                            </p>
                                            <p class="text-slate-700 whitespace-pre-wrap">
                                                {{ $message->message }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <aside class="space-y-6">
                    <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                        <div class="p-6 space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Status Tiket</h3>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Status</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ ucfirst($ticket->status) }}</p>
                            </div>
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Priority</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">
                                    @if ($ticket->priority === 'low')
                                        Low
                                    @elseif ($ticket->priority === 'medium')
                                        Medium
                                    @else
                                        High
                                    @endif
                                </p>
                            </div>
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Dibuat oleh</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $ticket->name }}</p>
                                <p class="text-sm text-slate-500">{{ $ticket->email }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($ticket->status === 'progress')
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 space-y-4">
                                <h3 class="text-lg font-semibold text-slate-900">Tindakan Cepat</h3>
                                <form action="{{ route('staff.tickets.suspend', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <x-secondary-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold bg-yellow-500 hover:bg-yellow-600 text-white">Tangguhkan</x-secondary-button>
                                </form>
                                <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                    <x-primary-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold bg-green-600 hover:bg-green-700">Tandai Selesai</x-primary-button>
                                </form>
                                <form action="{{ route('staff.tickets.update-priority', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <label class="text-sm font-semibold text-slate-900">Ubah Priority</label>
                                    <select name="priority" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                        <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                        <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                        <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                    </select>
                                    <x-secondary-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold bg-slate-900 hover:bg-slate-800 text-white">Update Priority</x-secondary-button>
                                </form>
                            </div>
                        </div>
                    @elseif ($ticket->status === 'assigned')
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 space-y-4">
                                <h3 class="text-lg font-semibold text-slate-900">Tindakan Cepat</h3>
                                <p class="text-sm text-slate-500">Tiket sudah diassign, siap mulai pekerjaan.</p>
                                <div class="space-y-3">
                                    <form action="{{ route('staff.tickets.start-progress', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <x-primary-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold bg-blue-600 hover:bg-blue-700">Mulai Mengerjakan</x-primary-button>
                                    </form>
                                    <form action="{{ route('staff.tickets.reject', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <x-danger-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold">Tolak</x-danger-button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @elseif ($ticket->status === 'waiting')
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 space-y-4">
                                <h3 class="text-lg font-semibold text-slate-900">Tindakan Cepat</h3>
                                <div class="space-y-3">
                                    <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                        <x-primary-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold bg-green-600 hover:bg-green-700">Tandai Selesai</x-primary-button>
                                    </form>
                                    <form action="{{ route('staff.tickets.reject', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <x-danger-button type="submit" class="w-full rounded-3xl px-4 py-3 font-semibold">Tolak</x-danger-button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 text-center">
                                <p class="text-lg font-semibold text-slate-900">Tiket Ditutup</p>
                                <p class="text-sm text-slate-500">Tiket ini sudah tidak aktif lagi.</p>
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </div>

    <div id="ticket-data" data-ticket-id="{{ $ticket->id }}" class="hidden"></div>
</x-app-layout>
