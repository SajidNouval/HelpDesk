<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Dashboard Staf
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Dashboard</span>
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
                            <a href="{{ route('staff.dashboard') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.tickets.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
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

                    <!-- Profile Card -->
                    <div class="mt-8 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-2">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">
                
                <!-- Statistics Overview -->
                <div class="mb-6">
                    <p class="text-gray-600">
                        Total <span class="font-medium">{{ Auth::user()->tickets()->count() }}</span> tiket dan <span class="font-medium">{{ $articleCount }}</span> artikel
                    </p>
                </div>

                <!-- Tiket Hari Ini Section -->
                <div class="space-y-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Tiket Hari Ini</h2>
                        </div>
                        <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                            {{ $todayTickets->count() }} tiket
                        </span>
                    </div>

                    @php
                        $itemsPerPage = 5;
                        $sortedTickets = $todayTickets->sortByDesc('created_at');
                        $totalPages = $todayTickets->count() ? ceil($sortedTickets->count() / $itemsPerPage) : 0;
                    @endphp

                    @if ($todayTickets->count() > 0)
                        <div id="ticketsContainer" class="space-y-4">
                            @foreach ($sortedTickets as $index => $ticket)
                                <div class="ticket-item bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden" data-page="{{ ceil(($index + 1) / $itemsPerPage) }}" style="display: {{ $index < $itemsPerPage ? 'block' : 'none' }}">
                                    <div class="p-6">
                                        <div class="flex justify-between gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <h3 class="font-semibold text-gray-900 hover:text-red-600 transition">{{ $ticket->subject }}</h3>
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                        {{ $ticket->status === 'open' ? 'bg-red-100 text-red-800' :
                                                           ($ticket->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' :
                                                            'bg-green-100 text-green-800') }}">
                                                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                                    </span>
                                                </div>

                                                <p class="text-sm text-gray-600 mb-2 line-clamp-2">{{ Str::limit($ticket->message, 150) }}</p>

                                                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                                                    <span>{{ $ticket->category->name ?? 'Tanpa kategori' }}</span>
                                                    <span>•</span>
                                                    <span>{{ $ticket->name }}</span>
                                                    <span>•</span>
                                                    <span>{{ $ticket->created_at->format('d M Y, H:i') }}</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center">
                                                <a href="{{ route('staff.tickets.show', $ticket) }}" class="inline-flex items-center px-3 py-2 bg-red-500 text-white rounded-lg text-xs font-semibold hover:bg-red-600 transition">
                                                    Detail
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($totalPages > 1)
                            <div class="mt-6 flex items-center justify-center gap-4">
                                <button onclick="previousPage()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">
                                    &lt;
                                </button>
                                <span id="pageInfo" class="text-sm text-gray-600 font-semibold">
                                    Halaman <span id="currentPage">1</span> dari {{ $totalPages }}
                                </span>
                                <button onclick="nextPage()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">
                                    &gt;
                                </button>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-16 bg-white border border-gray-200 rounded-lg">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">Tidak Ada Tiket Hari Ini</h3>
                            <p class="text-gray-600">
                                Belum ada tiket yang masuk dalam 24 jam terakhir.
                            </p>
                        </div>
                    @endif
                    </div>

                    <!-- Quick Stats -->
                    <!-- <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p class="text-gray-600 text-sm font-semibold uppercase">Total Tiket</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ Auth::user()->tickets()->count() }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p class="text-gray-600 text-sm font-semibold uppercase">Total Artikel</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $articleCount }}</p>
                        </div>
                    </div> -->
                </div>

                <script>
                    let currentPage = 1;
                    const totalPages = {{ $totalPages }};

                    function showPage(page) {
                        const items = document.querySelectorAll('.ticket-item');
                        items.forEach(item => {
                            item.style.display = item.getAttribute('data-page') == page ? 'block' : 'none';
                        });
                        document.getElementById('currentPage').textContent = page;
                        currentPage = page;
                    }

                    function nextPage() {
                        if (currentPage < totalPages) {
                            showPage(currentPage + 1);
                        }
                    }

                    function previousPage() {
                        if (currentPage > 1) {
                            showPage(currentPage - 1);
                        }
                    }
                </script>
            </div>

        </div>
    </div>
</x-app-layout>
