<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-8 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-2xl font-bold text-gray-900">
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
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-12 gap-8">

            <!-- Sidebar Left -->
            <div class="col-span-12 md:col-span-3">
                <div class="border-r border-gray-200 pr-4">
                    <h3 class="text-sm uppercase text-gray-400 mb-4 font-medium tracking-wider">
                        Menu Staf
                    </h3>

                    <ul class="space-y-2 text-gray-700">
                        <li>
                            <a href="{{ route('staff.dashboard') }}" class="block rounded-l-md px-3 py-2 text-sm transition hover:text-red-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.tickets.index') }}" class="block rounded-l-md px-3 py-2 text-sm transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Tiket
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.index') }}" class="block rounded-l-md px-3 py-2 text-sm transition hover:text-red-500">
                                Kelola Artikel
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.create') }}" class="block rounded-l-md px-3 py-2 text-sm transition hover:text-red-500">
                                Buat Artikel Baru
                            </a>
                        </li>
                    </ul>

                    <!-- Stats Card -->
                    <div class="mt-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h4 class="font-semibold text-gray-700 mb-3 text-sm">Statistik</h4>
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
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h4 class="font-semibold text-gray-700 mb-2 text-sm">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            @php
                $allTickets = $tickets->values();
                $allPages = max(1, ceil($allTickets->count() / 10));
                $completedPages = max(1, ceil($completedTickets->count() / 10));
                $waitingPages = max(1, ceil($waitingTickets->count() / 10));
            @endphp

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9" data-page="staff-tickets" data-all-pages="{{ $allPages }}" data-completed-pages="{{ $completedPages }}" data-waiting-pages="{{ $waitingPages }}">

                <!-- Success/Error Alert -->
                @if (session('success'))
                    <x-alert type="success" class="mb-6">
                        {{ session('success') }}
                    </x-alert>
                @endif

                <!-- Active Ticket Section -->
                @if ($activeTicket)
                    <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden border-l-4 border-blue-600">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">
                                        #{{ $activeTicket->id }} - {{ $activeTicket->subject }}
                                    </h2>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 py-4 border-t border-gray-200">
                                <!-- Left Column -->
                                <div>
                                    <div class="mb-4">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Kategori</p>
                                        <p class="text-base font-semibold text-gray-900 mt-1">
                                            {{ $activeTicket->category->name }}
                                        </p>
                                    </div>

                                    <div class="mb-4">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Dari</p>
                                        <p class="text-base font-semibold text-gray-900 mt-1">
                                            {{ $activeTicket->name }}
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">{{ $activeTicket->email }}</p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Dibuat</p>
                                        <p class="text-base font-semibold text-gray-900 mt-1">
                                            {{ $activeTicket->created_at->format('d M Y, H:i') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-2">Pesan</p>
                                    <p class="text-sm text-gray-900 leading-relaxed">
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
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                                            Mulai Mengerjakan
                                        </button>
                                    </form>
                                    <form action="{{ route('staff.tickets.reject', $activeTicket) }}" method="POST" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium text-sm">
                                            Tolak
                                        </button>
                                    </form>
                                @elseif ($activeTicket->status === 'progress')
                                    <form action="{{ route('staff.tickets.complete', $activeTicket) }}" method="POST" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="priority" value="{{ $activeTicket->priority }}">
                                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm">
                                            Tandai Selesai
                                        </button>
                                    </form>

                                    <a href="{{ route('staff.tickets.show', $activeTicket) }}" class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-center font-medium text-sm">
                                        Detail Lengkap
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No Active Ticket -->
                    <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <x-empty-state 
                            icon="check"
                            title="Tidak Ada Tiket Aktif"
                            subtitle="Semua tiket telah ditangani. Periksa tab di bawah untuk melihat riwayat."
                            size="md"
                        />
                    </div>
                @endif

                @php
                    $allTickets = $tickets->values();
                    $allPages = max(1, ceil($allTickets->count() / 10));
                    $completedPages = max(1, ceil($completedTickets->count() / 10));
                    $waitingPages = max(1, ceil($waitingTickets->count() / 10));
                @endphp

                <!-- Tabs Navigation -->
                <div class="mb-6 border-b border-gray-200">
                    <div class="flex gap-8">
                        <button data-tab-btn class="tab-btn px-4 py-3 font-medium border-b-2 border-red-500 text-red-600 transition text-sm" data-tab="all">
                            Semua Tiket
                        </button>
                        <button data-tab-btn class="tab-btn px-4 py-3 font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition text-sm" data-tab="completed">
                            Selesai
                        </button>
                        @if ($waitingTickets->count() > 0)
                            <button data-tab-btn class="tab-btn px-4 py-3 font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition text-sm" data-tab="waiting">
                                Menunggu <span class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">{{ $waitingTickets->count() }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Tab Content -->
                <div id="all" data-tab-content class="tab-content">
                @php
                    $allTickets = $tickets->values();
                    $allPages = max(1, ceil($allTickets->count() / 10));
                @endphp
                    @if ($allTickets->count() > 0)
                        <div class="space-y-3">
                            @foreach ($allTickets as $ticket)
                                @php $page = floor($loop->index / 10) + 1; @endphp
                                <x-ticket-card 
                                    :ticket="$ticket"
                                    :href="route('staff.tickets.show', $ticket)"
                                    data-tab-item="all"
                                    data-page="{{ $page }}"
                                    class="{{ $page === 1 ? '' : 'hidden' }}"
                                />
                            @endforeach
                        </div>

                        @if ($allPages > 1)
                            <div class="mt-6 flex items-center justify-center gap-3">
                                <button type="button" data-pagination="previous" data-pagination-button data-tab="all" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium"><</button>
                                <span class="text-sm text-gray-600 font-medium">Halaman <span data-current-page="all">1</span> dari {{ $allPages }}</span>
                                <button type="button" data-pagination="next" data-pagination-button data-tab="all" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">></button>
                            </div>
                        @endif
                    @else
                        <x-empty-state 
                            icon="ticket"
                            title="Tidak ada tiket"
                            subtitle="Belum ada tiket di tab ini."
                            size="sm"
                        />
                    @endif
                </div>

                <div id="completed" data-tab-content class="tab-content hidden">
                    @php
                        $completedPages = max(1, ceil($completedTickets->count() / 10));
                        $waitingPages = max(1, ceil($waitingTickets->count() / 10));
                    @endphp
                    @if ($completedTickets->count() > 0)
                        <div class="space-y-3">
                            @foreach ($completedTickets as $ticket)
                                @php $page = floor($loop->index / 10) + 1; @endphp
                                <x-ticket-card 
                                    :ticket="$ticket"
                                    :href="route('staff.tickets.show', $ticket)"
                                    data-tab-item="completed"
                                    data-page="{{ $page }}"
                                    class="{{ $page === 1 ? '' : 'hidden' }}"
                                    :showWorkTime="true"
                                    :showPriority="true"
                                />
                            @endforeach
                        </div>

                        @if ($completedPages > 1)
                            <div class="mt-6 flex items-center justify-center gap-3">
                                <button type="button" data-pagination="previous" data-pagination-button data-tab="completed" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium"><</button>
                                <span class="text-sm text-gray-600 font-medium">Halaman <span data-current-page="completed">1</span> dari {{ $completedPages }}</span>
                                <button type="button" data-pagination="next" data-pagination-button data-tab="completed" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">></button>
                            </div>
                        @endif
                    @else
                        <x-empty-state 
                            icon="check"
                            title="Belum ada tiket yang selesai"
                            subtitle="Tiket yang telah diselesaikan akan muncul di sini."
                            size="sm"
                        />
                    @endif
                </div>

                @if ($waitingTickets->count() > 0)
                    <div id="waiting" data-tab-content class="tab-content hidden">
                        <div class="space-y-3">
                            @foreach ($waitingTickets as $ticket)
                                @php $page = floor($loop->index / 10) + 1; @endphp
                                <x-ticket-card 
                                    :ticket="$ticket"
                                    :href="route('staff.tickets.show', $ticket)"
                                    data-tab-item="waiting"
                                    data-page="{{ $page }}"
                                    class="{{ $page === 1 ? '' : 'hidden' }}"
                                />
                            @endforeach
                        </div>

                        @if ($waitingPages > 1)
                            <div class="mt-6 flex items-center justify-center gap-3">
                                <button type="button" data-pagination="previous" data-pagination-button data-tab="waiting" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium"><</button>
                                <span class="text-sm text-gray-600 font-medium">Halaman <span data-current-page="waiting">1</span> dari {{ $waitingPages }}</span>
                                <button type="button" data-pagination="next" data-pagination-button data-tab="waiting" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">></button>
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>

</x-app-layout>