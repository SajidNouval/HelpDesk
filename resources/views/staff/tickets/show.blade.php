<x-app-layout>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header with Back Button and Breadcrumb -->
        <div class="mb-6">
            <a href="{{ route('staff.tickets.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali ke Daftar Tiket
            </a>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <a href="{{ route('staff.dashboard') }}" class="hover:text-gray-900">Dashboard</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <a href="{{ route('staff.tickets.index') }}" class="hover:text-gray-900">Tiket</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <span class="text-gray-900">Tiket #{{ $ticket->id }}</span>
            </div>
        </div>

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
                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Tiket #{{ $ticket->id }}</h2>
                                <p class="text-sm text-gray-500">{{ $ticket->subject }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 mb-6 pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Kategori</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $ticket->category->name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Dibuat</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $ticket->created_at->format('d M Y') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Diassign</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $ticket->assigned_at?->format('d M Y H:i') ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Status</p>
                                    <p class="text-sm font-medium text-gray-900">
                                        @if($ticket->closed_at)
                                            {{ $ticket->closed_at->format('d M Y H:i') }}
                                        @else
                                            <x-status-badge :status="$ticket->status" />
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6 pt-4 border-t border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">Informasi Pelanggan</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Nama</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $ticket->name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Email</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $ticket->email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">Pesan Tiket</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-900 whitespace-pre-wrap text-sm leading-relaxed">{{ $ticket->message }}</p>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Chat Box Section (Only when in progress) -->
                @if ($ticket->status === 'progress')
                    <div id="chat-container" class="bg-white border border-gray-100 rounded-lg shadow-sm">
                        <div class="p-6 flex flex-col h-[520px]">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Pesan</h3>
                            </div>

                            <div id="messages-container" class="flex-1 overflow-y-auto rounded-lg border border-gray-100 bg-gray-50 p-4 mb-4 space-y-3">
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
                                                        : 'bg-white text-gray-900 rounded-2xl rounded-tl-sm border border-gray-200' }}">
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

                            <form id="message-form" class="flex gap-3 border-t border-gray-100 pt-4">
                                <input type="text" id="message-input" name="message" placeholder="Ketik pesan..."
                                    class="flex-1 h-11 px-4 border border-gray-300 rounded-lg text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" required>
                                <button type="submit" class="h-11 px-5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Kirim</button>
                            </form>
                        </div>
                    </div>
                @endif


                <!-- Log Progress Card -->
                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Log Progress</h3>
                        </div>

                        @unless ($ticket->status === 'closed')
                            <form id="log-form" action="{{ route('staff.tickets.logs.store', $ticket) }}" method="POST" class="space-y-3 mb-6">
                                @csrf
                                <div>
                                    <textarea name="description" id="description" rows="4" required class="w-full rounded-lg border border-gray-300 p-4 text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none" placeholder="Tambahkan log progress..."></textarea>
                                </div>
                                <button id="log-submit-btn" type="submit" class="h-10 px-5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Simpan Log</button>
                            </form>
                        @else
                            <div class="mb-6 rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm text-gray-700">
                                Tiket telah selesai. Log baru tidak dapat ditambahkan, tetapi riwayat log berikut masih dapat dilihat.
                            </div>
                        @endunless

                        <div id="logs-container" class="space-y-3 max-h-[320px] overflow-y-auto pr-2">
                            @forelse ($ticket->logs->sortByDesc('created_at')->take(5) as $log)
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <p class="text-sm font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</p>
                                        <p class="text-xs text-gray-500">{{ $log->created_at->format('d M Y, H:i') }}</p>
                                    </div>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $log->description }}</p>
                                </div>
                            @empty
                                <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 text-center">
                                    Belum ada log tambahan.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Riwayat Chat Card -->
                @if ($ticket->messages->count() > 0)
                    <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Riwayat Chat</h3>
                            </div>
                            <div class="space-y-4 max-h-[420px] overflow-y-auto pr-2">
                                @foreach ($ticket->messages->sortByDesc('created_at')->take(5) as $message)
                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
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

                <!-- Priority & Quick Actions Card -->
                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-6 space-y-6">
                        <!-- Priority Section -->
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-gray-900">Priority</h3>
                        </div>

                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-medium text-gray-900">
                                <x-priority-badge :priority="$ticket->priority" />
                            </p>
                        </div>

                        <!-- Quick Actions Section -->
                        @if ($ticket->status === 'progress')
                            <div class="pt-4 border-t border-gray-100">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-semibold text-gray-900">Tindakan Cepat</h3>
                                </div>
                                <form action="{{ route('staff.tickets.suspend', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full h-10 px-4 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition">Tangguhkan</button>
                                </form>
                                <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                    <button type="submit" class="w-full h-10 px-5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition">Tandai Selesai</button>
                                </form>
                                <form action="{{ route('staff.tickets.update-priority', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <label class="text-xs font-semibold text-gray-700 block mb-1">Ubah Priority</label>
                                    <select name="priority" class="w-full h-11 px-4 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                        <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                        <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                        <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                    </select>
                                    <button type="submit" class="w-full h-10 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium transition">Update Priority</button>
                                </form>
                            </div>
                        @elseif ($ticket->status === 'assigned')
                            <div class="pt-4 border-t border-gray-100">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-semibold text-gray-900">Tindakan Cepat</h3>
                                </div>
                                <p class="text-sm text-gray-500 mb-3">Tiket sudah diassign, siap mulai pekerjaan.</p>
                                <div class="space-y-3">
                                    <form action="{{ route('staff.tickets.start-progress', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full h-10 px-5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Mulai Mengerjakan</button>
                                    </form>
                                    <form action="{{ route('staff.tickets.reject', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full h-10 px-4 rounded-lg border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition">Tolak</button>
                                    </form>
                                </div>
                            </div>
                        @elseif ($ticket->status === 'waiting')
                            <div class="pt-4 border-t border-gray-100">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-semibold text-gray-900">Tindakan Cepat</h3>
                                </div>
                                <div class="space-y-3">
                                    <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                        <button type="submit" class="w-full h-10 px-5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition">Tandai Selesai</button>
                                    </form>
                                    <form action="{{ route('staff.tickets.reject', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full h-10 px-4 rounded-lg border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition">Tolak</button>
                                    </form>
                                    <form action="{{ route('staff.tickets.update-priority', $ticket) }}" method="POST" class="space-y-3">
                                        @csrf
                                        @method('PATCH')
                                        <label class="text-xs font-semibold text-gray-700 block mb-1">Ubah Priority</label>
                                        <select name="priority" class="w-full h-11 px-4 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                            <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                            <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                            <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                        </select>
                                        <button type="submit" class="w-full h-10 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium transition">Update Priority</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="pt-4 border-t border-gray-100 text-center">
                                <p class="text-base font-semibold text-gray-900">Tiket Ditutup</p>
                                <p class="text-sm text-gray-500 mt-1">Tiket ini sudah tidak aktif lagi.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div id="ticket-data" data-ticket-id="{{ $ticket->id }}" class="hidden"></div>
</x-app-layout>
