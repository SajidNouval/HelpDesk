<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Kelola Tiket
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Tiket Saya</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid grid-cols-12 gap-8">

            <!-- Sidebar Left -->
            <div class="col-span-12 md:col-span-3">
                <div class="border-r border-gray-200 pr-4">
                    <h3 class="text-sm uppercase text-gray-400 mb-4 font-medium tracking-wider">
                        Menu Staf
                    </h3>

                    <ul class="space-y-3 text-gray-700">
                        <li>
                            <a href="{{ route('staff.dashboard') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.tickets.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Tiket
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Artikel
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.create') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Buat Artikel Baru
                            </a>
                        </li>
                    </ul>

                    <!-- Stats Card -->
                    <div class="mt-8 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-3">Statistik</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Tiket</span>
                                <span class="font-semibold text-gray-900">{{ $tickets->count() }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Selesai</span>
                                <span class="font-semibold text-green-600">{{ $completedTickets->count() }}</span>
                            </div>
                            @if ($waitingTickets->count() > 0)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Menunggu</span>
                                    <span class="font-semibold text-orange-600">{{ $waitingTickets->count() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Profile Card -->
                    <div class="mt-4 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-2">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">
                
                <!-- Success/Error Alert -->
                @if (session('success'))
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded-lg flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                <!-- Active Ticket Section -->
                @if ($activeTicket)
                    <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden border-l-4 border-blue-600">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                
                                <h2 class="text-2xl font-bold text-gray-900">
                                    #{{ $activeTicket->id }} - {{ $activeTicket->subject }}
                                </h2>
                            </div>
                            
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 py-4 border-t border-gray-200">
                            <!-- Left Column -->
                            <div>
                                <div class="mb-4">
                                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Kategori</p>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">
                                        {{ $activeTicket->category->name }}
                                    </p>
                                </div>

                                <div class="mb-4">
                                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Dari</p>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">
                                        {{ $activeTicket->name }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">{{ $activeTicket->email }}</p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Dibuat</p>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">
                                        {{ $activeTicket->created_at->format('d M Y, H:i') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-2">Pesan</p>
                                <p class="text-gray-900 leading-relaxed">
                                    {{ $activeTicket->message }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                            @if ($activeTicket->status === 'assigned')
                                <form action="{{ route('staff.tickets.start-progress', $activeTicket) }}" method="POST" class="flex-1">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                                        Mulai Mengerjakan
                                    </button>
                                </form>
                            @elseif ($activeTicket->status === 'progress')
                                <form action="{{ route('staff.tickets.complete', $activeTicket) }}" method="POST" class="flex-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="priority" value="{{ $activeTicket->priority }}">
                                    <button type="submit" onclick="return confirm('Tandai tiket ini sebagai selesai?')" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                                        Tandai Selesai
                                    </button>
                                </form>

                                <a href="{{ route('staff.tickets.show', $activeTicket) }}" class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-center font-semibold">
                                    Detail Lengkap
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <!-- No Active Ticket -->
                <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="p-12 text-center">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Tidak Ada Tiket Aktif</h3>
                    </div>
                </div>
            @endif

            <!-- Tabs Navigation -->
            <div class="mb-6 border-b border-gray-200">
                <div class="flex gap-8">
                    <button class="tab-btn px-4 py-3 font-semibold border-b-2 border-red-500 text-red-600 transition" data-tab="all">
                        Semua Tiket
                    </button>
                    <button class="tab-btn px-4 py-3 font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition" data-tab="completed">
                        Selesai
                    </button>
                    @if ($waitingTickets->count() > 0)
                        <button class="tab-btn px-4 py-3 font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition" data-tab="waiting">
                            Menunggu <span class="ml-2 px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">{{ $waitingTickets->count() }}</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Tab Content -->
            <div id="all" class="tab-content">
                @php
                    $allTickets = $tickets->where('status', '!=', 'waiting')->values();
                    $allPages = max(1, ceil($allTickets->count() / 10));
                @endphp
                @if ($allTickets->count() > 0)
                    <div class="space-y-4">
                        @foreach ($allTickets as $ticket)
                            @php $page = floor($loop->index / 10) + 1; @endphp
                            <a data-tab-item="all" data-page="{{ $page }}" href="{{ route('staff.tickets.show', $ticket) }}" class="w-full block text-left bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden border-l-4 
                                    {{ $ticket->status === 'closed' ? 'border-green-500' : 'border-yellow-500' }} hover:scale-105 duration-200">
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-900 hover:text-red-600 transition text-lg">#{{ $ticket->id }} - {{ $ticket->subject }}</h4>
                                            <p class="text-sm text-gray-600 mt-1">{{ $ticket->category->name }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-block text-xs px-3 py-1 rounded-full font-semibold 
                                                {{ $ticket->status === 'closed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $ticket->status === 'closed' ? 'Selesai' : ' ' . ucfirst($ticket->status) }}
                                            </span>
                                            <p class="text-xs text-gray-500 mt-2">{{ $ticket->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700 mb-3 leading-relaxed">{{ Str::limit($ticket->message, 120) }}</p>
                                    @if ($ticket->status === 'closed')
                                        <p class="text-xs text-gray-500 pt-3 border-t border-gray-200">
                                            Ditutup: {{ $ticket->closed_at->format('d M Y, H:i') }}
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if ($allPages > 1)
                        <div class="mt-6 flex items-center justify-center gap-4">
                            <button type="button" onclick="previousPage('all')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">&lt;</button>
                            <span class="text-sm text-gray-600 font-semibold">Halaman <span id="allCurrentPage">1</span> dari {{ $allPages }}</span>
                            <button type="button" onclick="nextPage('all')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">&gt;</button>
                        </div>
                    @endif
                @else
                    <div class="text-center py-16 bg-white border border-gray-200 rounded-lg">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-600 font-medium">Tidak ada tiket</p>
                    </div>
                @endif
            </div>

            <div id="completed" class="tab-content hidden">
                @php
                    $completedPages = max(1, ceil($completedTickets->count() / 10));
                    $waitingPages = max(1, ceil($waitingTickets->count() / 10));
                @endphp
                @if ($completedTickets->count() > 0)
                    <div class="space-y-4">
                        @foreach ($completedTickets as $ticket)
                            @php $page = floor($loop->index / 10) + 1; @endphp
                            <a data-tab-item="completed" data-page="{{ $page }}" href="{{ route('staff.tickets.show', $ticket) }}" class="w-full block text-left bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden border-l-4 border-green-500 hover:scale-105 duration-200">
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-900 text-lg">#{{ $ticket->id }} - {{ $ticket->subject }}</h4>
                                            <p class="text-sm text-gray-600 mt-1">{{ $ticket->category->name }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-block text-xs px-3 py-1 rounded-full font-semibold bg-green-100 text-green-800">
                                                Selesai
                                            </span>
                                            <p class="text-xs text-gray-500 mt-2">{{ $ticket->closed_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-4 text-sm pt-3 border-t border-gray-200">
                                        <span class="text-gray-600">
                                            Waktu kerja: {{ $ticket->closed_at->diffInHours($ticket->assigned_at) }} jam
                                        </span>
                                        <span class="text-gray-600">
                                            Priority: 
                                            @if ($ticket->priority === 'low')
                                                Low
                                            @elseif ($ticket->priority === 'medium')
                                                Medium
                                            @else
                                                High
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if ($completedPages > 1)
                        <div class="mt-6 flex items-center justify-center gap-4">
                            <button type="button" onclick="previousPage('completed')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">&lt;</button>
                            <span class="text-sm text-gray-600 font-semibold">Halaman <span id="completedCurrentPage">1</span> dari {{ $completedPages }}</span>
                            <button type="button" onclick="nextPage('completed')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">&gt;</button>
                        </div>
                    @endif
                @else
                    <div class="text-center py-16 bg-white border border-gray-200 rounded-lg">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-600 font-medium">Belum ada tiket yang selesai</p>
                    </div>
                @endif
            </div>

            @if ($waitingTickets->count() > 0)
                <div id="waiting" class="tab-content hidden">
                        <div class="space-y-4">
                        @foreach ($waitingTickets as $ticket)
                            @php $page = floor($loop->index / 10) + 1; @endphp
                            <a data-tab-item="waiting" data-page="{{ $page }}" style="display: {{ $page === 1 ? 'block' : 'none' }};" href="{{ route('staff.tickets.show', $ticket) }}" class="block bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden border-l-4 border-orange-500 group">
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-900 group-hover:text-red-600 transition text-lg">#{{ $ticket->id }} - {{ $ticket->subject }}</h4>
                                            <p class="text-sm text-gray-600 mt-1">{{ $ticket->category->name }}</p>
                                        </div>
                                        <span class="inline-block text-xs px-3 py-1 rounded-full font-semibold bg-orange-100 text-orange-800">
                                            Menunggu
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700 leading-relaxed">{{ Str::limit($ticket->message, 120) }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if ($waitingPages > 1)
                        <div class="mt-6 flex items-center justify-center gap-4">
                            <button type="button" onclick="previousPage('waiting')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">&lt;</button>
                            <span class="text-sm text-gray-600 font-semibold">Halaman <span id="waitingCurrentPage">1</span> dari {{ $waitingPages }}</span>
                            <button type="button" onclick="nextPage('waiting')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">&gt;</button>
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

    <script>
        const currentPage = {
            all: 1,
            completed: 1,
            waiting: 1,
        };

        const totalPages = {
            all: {{ $allPages }},
            completed: {{ $completedPages }},
            waiting: {{ $waitingPages }},
        };

        function showPage(tab, page) {
            document.querySelectorAll(`[data-tab-item="${tab}"]`).forEach(item => {
                item.style.display = item.dataset.page == page ? 'block' : 'none';
            });
            const pageInfo = document.getElementById(`${tab}CurrentPage`);
            if (pageInfo) {
                pageInfo.textContent = page;
            }
            currentPage[tab] = page;
        }

        function nextPage(tab) {
            if (currentPage[tab] < totalPages[tab]) {
                showPage(tab, currentPage[tab] + 1);
            }
        }

        function previousPage(tab) {
            if (currentPage[tab] > 1) {
                showPage(tab, currentPage[tab] - 1);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            showPage('all', 1);
            showPage('completed', 1);
            showPage('waiting', 1);
        });

        // Tab navigation
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                
                // Remove active border from all buttons
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('border-red-500', 'text-red-600');
                    b.classList.add('border-transparent', 'text-gray-600');
                });
                
                // Show selected tab
                document.getElementById(tabName).classList.remove('hidden');
                
                // Add active border to clicked button
                this.classList.remove('border-transparent', 'text-gray-600');
                this.classList.add('border-red-500', 'text-red-600');
            });
        });
    </script>

</x-app-layout>
