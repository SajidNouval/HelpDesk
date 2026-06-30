<x-app-layout>
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700">Status Tiket #{{ $ticket->id }}</h1>
            <p class="mt-2 text-gray-500">Lihat perkembangan tiket Anda, log staff, dan riwayat pesan.</p>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-3 flex items-center flex-wrap gap-x-1">
                <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
                <span class="text-gray-400">/</span>
                <a href="{{ route('articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Artikel Bantuan</a>
                <span class="text-gray-400">/</span>
                <span class="text-gray-700">Status Tiket #{{ $ticket->id }}</span>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10 space-y-6">
        <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Tiket</p>
                    <h2 class="text-2xl font-semibold text-gray-800">#{{ $ticket->id }} - {{ $ticket->subject }}</h2>
                </div>
                <x-status-badge :status="$ticket->status" />
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-3xl border border-gray-200 p-4 bg-gray-50">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Email</p>
                    <p class="mt-2 text-gray-900">{{ $ticket->email }}</p>
                </div>
                <div class="rounded-3xl border border-gray-200 p-4 bg-gray-50">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Kategori</p>
                    <p class="mt-2 text-gray-900">{{ $ticket->category->name ?? 'Tanpa Kategori' }}</p>
                </div>
                <div class="rounded-3xl border border-gray-200 p-4 bg-gray-50">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Verifikasi</p>
                    <p class="mt-2 text-gray-900">{{ $ticket->email_verified_at ? $ticket->email_verified_at->format('d M Y H:i') : 'Belum diverifikasi' }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Detail Pesan</h3>
                <p class="text-gray-700 leading-relaxed">{{ $ticket->message }}</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Log</h3>
                @if($ticket->logs->isEmpty())
                    <p class="text-sm text-gray-500">Belum ada log.</p>
                @else
                    <ul class="space-y-3 text-sm text-gray-700">
                        @foreach($ticket->logs as $log)
                            <li class="rounded-2xl border border-gray-200 p-3 bg-gray-50">
                                <p class="font-semibold">{{ Str::title(str_replace('_', ' ', $log->action)) }}</p>
                                <p class="text-gray-500">{{ $log->description }}</p>
                                <p class="text-xs text-gray-400 mt-2">{{ $log->created_at->format('d M Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Chat</h3>
            @if($ticket->messages->isEmpty())
                <p class="text-sm text-gray-500">Belum ada pesan.</p>
            @else
                <div class="space-y-4">
                    @foreach($ticket->messages as $message)
                        <div class="rounded-3xl border border-gray-200 p-4 {{ $message->sender_type === 'guest' ? 'bg-red-50' : 'bg-gray-50' }}">
                            <div class="flex items-center justify-between mb-2 text-xs text-gray-500">
                                <span>{{ ucfirst($message->sender_type) }}</span>
                                <span>{{ $message->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <p class="text-gray-700">{{ $message->message }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
