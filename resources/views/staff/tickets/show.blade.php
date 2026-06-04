<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-12 gap-6">

            <!-- Left Column (Ticket Details, Chat, Logs) -->
            <div class="col-span-12 lg:col-span-8 space-y-6">

                <!-- Success Alert -->
                @if (session('success'))
                    <x-alert type="success" class="mb-4">
                        {{ session('success') }}
                    </x-alert>
                @endif

                <!-- Ticket Info Card -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <p class="text-xs uppercase tracking-wider text-gray-500">Detail Tiket</p>
                                <h2 class="text-2xl font-semibold text-gray-900 mt-1">#{{ $ticket->subject }}</h2>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <p class="text-xs uppercase tracking-wider text-gray-500">Kategori</p>
                                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $ticket->category->name }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <p class="text-xs uppercase tracking-wider text-gray-500">Dibuat</p>
                                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $ticket->created_at->format('d M Y') }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <p class="text-xs uppercase tracking-wider text-gray-500">Diassign</p>
                                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $ticket->assigned_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <p class="text-xs uppercase tracking-wider text-gray-500">Ditutup</p>
                                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $ticket->closed_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 mb-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-3">Informasi Pelanggan</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-sm text-gray-500">Nama</p>
                                    <p class="mt-1 font-semibold text-gray-900">{{ $ticket->name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Email</p>
                                    <p class="mt-1 font-semibold text-gray-900">{{ $ticket->email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-3">Pesan Tiket</h3>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                                <p class="text-gray-900 whitespace-pre-wrap text-sm leading-relaxed">{{ $ticket->message }}</p>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Chat Box Section (Only when in progress) -->
                @if ($ticket->status === 'progress')
                    <div id="chat-container" class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                        <div class="p-6 flex flex-col h-[520px]">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Pesan</h3>
                            </div>

                            <div id="messages-container" class="flex-1 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-4 mb-4 space-y-3">
                                <div id="messages-list">
                                    @foreach ($ticket->messages->sortBy('created_at') as $message)
                                        @php
                                            $isGuest = in_array($message->sender_type, ['guest', 'customer']);
                                            $senderLabel = $isGuest ? 'Guest' : ($message->sender?->name ?? 'Staff');
                                        @endphp
                                        {{-- Guest → right side (red bubble), Staff → left side (grey bubble) --}}
                                        <div class="flex {{ $isGuest ? 'justify-end' : 'justify-start' }} mb-3"
                                             data-message-id="{{ $message->id }}">
                                            <div class="max-w-xs lg:max-w-sm">
                                                <p class="text-xs text-gray-500 mb-1 {{ $isGuest ? 'text-right' : '' }}">
                                                    {{ $senderLabel }}
                                                </p>
                                                <div class="px-4 py-3 shadow-sm
                                                    {{ $isGuest
                                                        ? 'bg-red-600 text-white rounded-2xl rounded-tr-sm'
                                                        : 'bg-gray-100 text-gray-900 rounded-2xl rounded-tl-sm' }}">
                                                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                                                    <p class="text-xs mt-1.5 {{ $isGuest ? 'opacity-70 text-right' : 'text-gray-400' }}">
                                                        {{ $message->created_at->format('H:i') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <form id="message-form" class="flex gap-3 border-t border-gray-200 pt-4">
                                <input type="text" id="message-input" name="message" placeholder="Ketik pesan..."
                                    class="flex-1 h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" required>
                                <button type="submit" class="h-11 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Kirim</button>
                            </form>
                        </div>
                    </div>
                @endif


                <!-- Log Progress Card -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Log Progress</h3>
                            </div>
                        </div>

                        @unless ($ticket->status === 'closed')
                            <form id="log-form" action="{{ route('staff.tickets.logs.store', $ticket) }}" method="POST" class="space-y-3 mb-6">
                                @csrf
                                <div>
                                    <label for="description" class="text-sm font-semibold text-gray-900"></label>
                                    <textarea name="description" id="description" rows="4" required class="w-full rounded-xl border border-gray-300 p-4 text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none"></textarea>
                                </div>
                                <button id="log-submit-btn" type="submit" class="h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Simpan Log</button>
                            </form>
                        @else
                            <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                                Tiket telah selesai. Log baru tidak dapat ditambahkan, tetapi riwayat log berikut masih dapat dilihat.
                            </div>
                        @endunless

                        <div id="logs-container" class="space-y-3 max-h-[320px] overflow-y-auto pr-2">
                            @forelse ($ticket->logs->sortByDesc('created_at')->take(5) as $log)
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <p class="text-sm font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</p>
                                        <p class="text-xs text-gray-500">{{ $log->created_at->format('d M Y, H:i') }}</p>
                                    </div>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $log->description }}</p>
                                </div>
                            @empty
                                <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 text-center">
                                    Belum ada log tambahan.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Riwayat Chat Card -->
                @if ($ticket->messages->count() > 0)
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Riwayat Chat</h3>
                            <div class="space-y-4 max-h-[420px] overflow-y-auto pr-2">
                                @foreach ($ticket->messages->sortByDesc('created_at')->take(5) as $message)
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <p class="text-sm font-semibold text-gray-900">
                                            @if ($message->sender_type === 'staff')
                                                {{ $message->sender?->name ?? 'Staff' }}
                                            @else
                                                Guest
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500 mb-2">
                                            {{ $message->created_at->format('d M Y, H:i') }}
                                        </p>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                                            {{ $message->message }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column (Sidebar Actions & Status) -->
            <aside class="col-span-12 lg:col-span-4 space-y-6">
                
                <!-- Status & Priority Card -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="p-6 space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Status Tiket</h3>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Status</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                <x-status-badge :status="$ticket->status" />
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Priority</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                <x-priority-badge :priority="$ticket->priority" />
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Dibuat oleh</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">{{ $ticket->name }}</p>
                            <p class="text-sm text-gray-500">{{ $ticket->email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                @if ($ticket->status === 'progress')
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                        <div class="p-6 space-y-4">
                            <h3 class="text-base font-semibold text-gray-900">Tindakan Cepat</h3>
                            <form action="{{ route('staff.tickets.suspend', $ticket) }}" method="POST" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="w-full h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition">Tangguhkan</button>
                            </form>
                            <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                <button type="submit" class="w-full h-10 px-5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition">Tandai Selesai</button>
                            </form>
                            <form action="{{ route('staff.tickets.update-priority', $ticket) }}" method="POST" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <label class="text-xs font-semibold text-gray-700 block mb-1">Ubah Priority</label>
                                <select name="priority" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                    <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                </select>
                                <button type="submit" class="w-full h-10 px-4 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium transition">Update Priority</button>
                            </form>
                        </div>
                    </div>
                @elseif ($ticket->status === 'assigned')
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                        <div class="p-6 space-y-4">
                            <h3 class="text-base font-semibold text-gray-900">Tindakan Cepat</h3>
                            <p class="text-sm text-gray-500">Tiket sudah diassign, siap mulai pekerjaan.</p>
                            <div class="space-y-3">
                                <form action="{{ route('staff.tickets.start-progress', $ticket) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Mulai Mengerjakan</button>
                                </form>
                                <form action="{{ route('staff.tickets.reject', $ticket) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full h-10 px-4 rounded-xl border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition">Tolak</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @elseif ($ticket->status === 'waiting')
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                        <div class="p-6 space-y-4">
                            <h3 class="text-base font-semibold text-gray-900">Tindakan Cepat</h3>
                            <div class="space-y-3">
                                <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                    <button type="submit" class="w-full h-10 px-5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition">Tandai Selesai</button>
                                </form>
                                <form action="{{ route('staff.tickets.reject', $ticket) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full h-10 px-4 rounded-xl border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition">Tolak</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                        <div class="p-6 text-center">
                            <p class="text-base font-semibold text-gray-900">Tiket Ditutup</p>
                            <p class="text-sm text-gray-500 mt-1">Tiket ini sudah tidak aktif lagi.</p>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>

    <div id="ticket-data" data-ticket-id="{{ $ticket->id }}" class="hidden"></div>
</x-app-layout>
