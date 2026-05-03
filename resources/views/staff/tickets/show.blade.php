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
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded">
                    {{ session('success') }}
                </div>
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

                                <form id="message-form" class="flex gap-3 border-t border-slate-200 pt-4" onsubmit="return false;">
                                    <input type="text" id="message-input" name="message" placeholder="Ketik pesan..."
                                        class="flex-1 rounded-full border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <button type="submit" class="rounded-full bg-blue-600 px-5 py-3 text-white hover:bg-blue-700 transition font-semibold">
                                        Kirim
                                    </button>
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
                                        <textarea name="description" id="description" rows="4" required class="mt-2 w-full rounded-3xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-slate-300"></textarea>
                                    </div>
                                    <button type="submit" id="log-submit-btn" class="rounded-3xl bg-slate-900 px-6 py-3 text-white hover:bg-slate-800 transition font-semibold">
                                        Simpan Log
                                    </button>
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
                                    <button type="submit" onclick="return confirm('Tangguhkan tiket ini? Chat akan dihentikan sementara dan tiket berstatus waiting.')" class="w-full rounded-3xl bg-yellow-500 px-4 py-3 text-white font-semibold hover:bg-yellow-600 transition">
                                        Tangguhkan
                                    </button>
                                </form>
                                <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                    <button type="submit" onclick="return confirm('Tandai tiket ini sebagai selesai?')" class="w-full rounded-3xl bg-green-600 px-4 py-3 text-white font-semibold hover:bg-green-700 transition">
                                        Tandai Selesai
                                    </button>
                                </form>
                                <form action="{{ route('staff.tickets.update-priority', $ticket) }}" method="POST" class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <label class="text-sm font-semibold text-slate-900">Ubah Priority</label>
                                    <select name="priority" class="w-full rounded-3xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-slate-300">
                                        <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                        <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                        <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                    </select>
                                    <button type="submit" class="w-full rounded-3xl bg-slate-900 px-4 py-3 text-white font-semibold hover:bg-slate-800 transition">
                                        Update Priority
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif ($ticket->status === 'assigned')
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 space-y-4">
                                <h3 class="text-lg font-semibold text-slate-900">Tindakan Cepat</h3>
                                <p class="text-sm text-slate-500">Tiket sudah diassign, siap mulai pekerjaan.</p>
                                <form action="{{ route('staff.tickets.start-progress', $ticket) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full rounded-3xl bg-blue-600 px-4 py-3 text-white font-semibold hover:bg-blue-700 transition">
                                        Mulai Mengerjakan
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif ($ticket->status === 'waiting')
                        <div class="bg-white border border-slate-200 shadow-sm sm:rounded-3xl overflow-hidden">
                            <div class="p-6 space-y-4 text-center">
                                <form action="{{ route('staff.tickets.complete', $ticket) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                                    <button type="submit" onclick="return confirm('Tandai tiket waiting ini sebagai selesai?')" class="w-full rounded-3xl bg-green-600 px-4 py-3 text-white font-semibold hover:bg-green-700 transition">
                                        Tandai Selesai
                                    </button>
                                </form>
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

<script>
    const ticketId = document.getElementById('ticket-data').dataset.ticketId;
    const messagesList = document.getElementById('messages-list');

    function scrollMessagesToBottom() {
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    let lastMessageIds = new Set(); // Track message IDs untuk cegah duplikasi

    // Fungsi untuk mendapatkan CSRF token dari meta tag
    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    // Load riwayat pesan
    async function loadMessages() {
        try {
            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                console.error('Failed to load messages:', response.status, response.statusText);
                return;
            }

            const messages = await response.json();
            messagesList.innerHTML = '';
            lastMessageIds.clear();
            
            messages.forEach(msg => {
                appendMessage(msg);
                if (msg.id) lastMessageIds.add(msg.id);
            });
            scrollMessagesToBottom();
        } catch (err) {
            console.error('Error loading messages:', err);
        }
    }

    // Load logs
    async function loadLogs() {
        try {
            const response = await fetch(`/api/tickets/${ticketId}/logs`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                console.error('Failed to load logs:', response.status, response.statusText);
                return;
            }

            const logs = await response.json();
            const logsContainer = document.getElementById('logs-container');
            logsContainer.innerHTML = '';

            if (logs.length === 0) {
                logsContainer.innerHTML = '<div class="p-4 bg-slate-50 rounded-lg text-sm text-slate-600">Belum ada log tambahan.</div>';
                return;
            }

            logs.reverse().forEach(log => {
                const logDiv = document.createElement('div');
                logDiv.className = 'bg-slate-50 p-4 rounded-lg border border-slate-200';
                logDiv.innerHTML = `
                    <div class="flex items-center justify-between gap-4 mb-2">
                        <p class="text-sm font-semibold text-slate-900">${ucfirst(log.action.replace(/_/g, ' '))}</p>
                        <p class="text-xs text-slate-500">${new Date(log.created_at).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'})} ${new Date(log.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'})}</p>
                    </div>
                    <p class="text-sm text-slate-700 whitespace-pre-wrap">${log.description}</p>
                `;
                logsContainer.appendChild(logDiv);
            });
        } catch (err) {
            console.error('Error loading logs:', err);
        }
    }

    // Listen pesan baru via WebSocket (jika tersedia)
    let websocketConnected = false;

    function setupWebSocketListener() {
        if (!ticketId) return;

        if (typeof window.Echo === 'undefined' || window.Echo === null) {
            console.warn('⚠ Echo is not initialized. Real-time chat unavailable.');
            showWebSocketError();
            return;
        }

        try {
            const channelName = `ticket.${ticketId}`;
            const connector = window.Echo.connector;
            const pusherConnection = connector?.pusher?.connection;
            const socketReady = connector?.socket?.readyState === WebSocket.OPEN;
            const connectedState = pusherConnection?.state === 'connected';

            const subscribeToChannel = () => {
                if (!ticketId) return;
                window.Echo.channel(channelName)
                    .listen('MessageSent', async (e) => {
                        console.log('📨 New message received via WebSocket', e);
                        await loadMessages();
                    });
                websocketConnected = true;
                console.log('✓ WebSocket listener initialized for ticket:', ticketId);
            };

            if (socketReady || connectedState) {
                subscribeToChannel();
                return;
            }

            if (pusherConnection) {
                pusherConnection.bind('connected', () => {
                    console.log('🔗 WebSocket connection established, setting up listener');
                    subscribeToChannel();
                });

                pusherConnection.bind('error', (error) => {
                    console.error('✗ WebSocket connection error:', error);
                    websocketConnected = false;
                    showWebSocketError();
                });

                pusherConnection.bind('disconnected', () => {
                    console.warn('⚠ WebSocket connection closed');
                    websocketConnected = false;
                    showWebSocketError();
                });

                return;
            }

            subscribeToChannel();
        } catch (error) {
            console.error('Failed to setup WebSocket listener:', error);
            showWebSocketError();
        }
    }

    function showWebSocketError() {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded mb-4';
        errorDiv.innerHTML = '<p class="font-semibold">⚠️ Real-time chat tidak tersedia. Pastikan server Reverb berjalan.</p>';
        const container = document.getElementById('chat-container');
        if (container) {
            container.prepend(errorDiv);
        } else {
            document.body.prepend(errorDiv);
        }
    }

    // Initialize WebSocket listener dengan fallback polling
    function initializeWebSocket() {
        if (typeof window.Echo !== 'undefined' && window.Echo !== null) {
            setupWebSocketListener();
        } else {
            console.log('Waiting for Echo to initialize...');
            setTimeout(initializeWebSocket, 1000);
        }
    }
    
    initializeWebSocket();
    
    // Fallback polling setiap 5 detik jika WebSocket tidak tersedia
    setInterval(() => {
        if (!websocketConnected) {
            loadMessages();
        }
    }, 5000);

    // Kirim pesan
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');

    if (messageForm && messageInput) {
        const sendButton = messageForm.querySelector('button[type="submit"]');

        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;

            // Optimistic UI - tampilkan pesan langsung
            const optimisticMsg = {
                id: 'temp-' + Date.now(),
                message: message,
                sender_type: 'staff',
                sender_name: '{{ auth()->user()->name ?? "Staff" }}',
                created_at: new Date().toISOString()
            };
            appendMessage(optimisticMsg);
            lastMessageIds.add(optimisticMsg.id);

            messageInput.disabled = true;
            if (sendButton) sendButton.disabled = true;

            fetch('/api/messages', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ ticket_id: ticketId, message: message })
            })
            .then(async res => {
                if (res.ok) {
                    messageInput.value = '';
                    // Reload messages untuk update dengan ID asli dari server
                    setTimeout(() => loadMessages(), 500);
                } else {
                    // Hapus optimistic message jika gagal
                    const optimisticEl = Array.from(messagesList.children).pop();
                    if (optimisticEl) optimisticEl.remove();
                    lastMessageIds.delete(optimisticMsg.id);
                    console.error('Failed to send message:', res.status);
                }
            })
            .catch(err => {
                // Hapus optimistic message jika error
                const optimisticEl = Array.from(messagesList.children).pop();
                if (optimisticEl) optimisticEl.remove();
                lastMessageIds.delete(optimisticMsg.id);
                console.error('Error sending message:', err);
            })
            .finally(() => {
                messageInput.disabled = false;
                if (sendButton) sendButton.disabled = false;
                messageInput.focus();
            });
        });
    }

    // Handle log form submission
    const logForm = document.getElementById('log-form');
    const logTextarea = document.getElementById('description');
    const logSubmitBtn = document.getElementById('log-submit-btn');

    if (logForm && logTextarea && logSubmitBtn) {
        logForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const description = logTextarea.value.trim();
            if (!description) return;

            logTextarea.disabled = true;
            logSubmitBtn.disabled = true;
            logSubmitBtn.textContent = 'Menyimpan...';

            try {
                const response = await fetch(logForm.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ description: description })
                });

                if (response.ok) {
                    logTextarea.value = '';
                    await loadLogs(); // Reload logs without page refresh
                    
                    // Show success message
                    showSuccessMessage('Log berhasil ditambahkan!');
                } else {
                    console.error('Failed to save log:', response.status);
                    showErrorMessage('Gagal menyimpan log. Silakan coba lagi.');
                }
            } catch (err) {
                console.error('Error saving log:', err);
                showErrorMessage('Terjadi kesalahan. Silakan coba lagi.');
            } finally {
                logTextarea.disabled = false;
                logSubmitBtn.disabled = false;
                logSubmitBtn.textContent = 'Simpan Log';
                logTextarea.focus();
            }
        });
    }

    // Utility functions
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function showSuccessMessage(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'fixed top-4 right-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded z-50';
        alertDiv.innerHTML = `<p class="font-semibold">${message}</p>`;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    }

    function showErrorMessage(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'fixed top-4 right-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded z-50';
        alertDiv.innerHTML = `<p class="font-semibold">${message}</p>`;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    }

    // Tampilkan pesan di chat box
    function appendMessage(msg) {
        // Jangan tampilkan duplikasi berdasarkan ID
        if (msg.id && lastMessageIds.has(msg.id)) {
            return;
        }

        const div = document.createElement('div');
        div.className = `mb-3 ${msg.sender_type === 'staff' ? 'text-right' : 'text-left'}`;
        div.dataset.messageId = msg.id || 'temp';
        
        // Add sender name
        const senderDiv = document.createElement('div');
        senderDiv.className = 'text-xs text-gray-500 mb-1';
        senderDiv.textContent = msg.sender_name || 'Guest';
        div.appendChild(senderDiv);
        
        const messageBubble = document.createElement('div');
        messageBubble.className = `inline-block max-w-xs px-4 py-3 rounded-2xl ${msg.sender_type === 'staff' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100'}`;
        
        const messageText = document.createElement('p');
        messageText.className = 'text-sm leading-relaxed';
        messageText.textContent = msg.message;
        
        const timestamp = document.createElement('p');
        timestamp.className = 'text-xs opacity-75 mt-2';
        const msgTime = typeof msg.created_at === 'string' 
            ? new Date(msg.created_at)
            : msg.created_at;
        timestamp.textContent = msgTime.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
        
        messageBubble.appendChild(messageText);
        messageBubble.appendChild(timestamp);
        div.appendChild(messageBubble);
        messagesList.appendChild(div);
        
        if (msg.id) lastMessageIds.add(msg.id);
        scrollMessagesToBottom();
    }

    // Load messages dan logs saat page load
    loadMessages();
    loadLogs();
</script>
