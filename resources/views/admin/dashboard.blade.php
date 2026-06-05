<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Dashboard Admin
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Dashboard</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-12 gap-6">

            <!-- Sidebar Left -->
            <div class="col-span-12 md:col-span-3">
                <div class="border-r border-gray-200 pr-4">
                    <h3 class="text-sm uppercase text-gray-400 mb-4 font-medium tracking-wider">
                        Menu Admin
                    </h3>

                    <ul class="space-y-1 text-gray-700">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.users.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Staf
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.categories.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Kategori
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">

                @if(session('success'))
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-medium">
                        {{ session('success') }}
                    </div>
                @endif

                <!-- Statistics Overview -->
                <div class="mb-6 bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Ringkasan Statistik</h3>
                        
                        <!-- Main Stats Row -->
                        <div class="flex flex-wrap items-center gap-6 mb-5 pb-5 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Staff</p>
                                    <p class="text-xl font-bold text-gray-900">{{ $staffCount }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Artikel</p>
                                    <p class="text-xl font-bold text-gray-900">{{ $articleCount }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Tiket</p>
                                    <p class="text-xl font-bold text-gray-900">{{ $totalTickets }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ticket Status Row -->
                        <div class="flex flex-wrap items-center gap-6">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                                <span class="text-sm text-gray-600">Total Tiket Diproses: <strong class="text-gray-900">{{ $ticketsWaiting }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                                <span class="text-sm text-gray-600">Total Tiket Selesai: <strong class="text-gray-900">{{ $ticketsDone }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Service Settings Card -->
                <div class="mb-6 p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Live Service</span>
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $liveServiceEnabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $liveServiceEnabled ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">
                                Live chat tiket hanya akan tersedia jika layanan ini aktif. Laporan/report tetap dapat dibuat kapan saja.
                            </p>
                        </div>
                        <form action="{{ route('admin.live-service.toggle') }}" method="POST" class="shrink-0">
                            @csrf
                            <input type="hidden" name="status" value="{{ $liveServiceEnabled ? 'off' : 'on' }}">
                            <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition whitespace-nowrap">
                                Kelola Live Service
                            </button>
                        </form>
                    </div>
                </div>

                

                <!-- Pending Articles Review Section -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Artikel Menunggu Persetujuan</h3>
                                <p class="text-sm text-gray-500">Daftar usulan artikel baru dari staf yang perlu Anda tinjau.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-0.5 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                    {{ $pendingArticles->count() }} artikel
                                </span>
                                <a href="{{ route('admin.articles.index') }}" class="text-xs font-semibold text-red-500 hover:text-red-600 transition flex items-center gap-1">
                                    Tinjau di Kelola Artikel →
                                </a>
                            </div>
                        </div>

                        @if ($pendingArticles->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Judul Artikel</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Penulis</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kategori</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($pendingArticles as $article)
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 text-gray-900 font-medium">
                                                    <x-truncate-text :value="$article->title" class="block text-gray-700" />
                                                </td>
                                                <td class="px-6 py-4 text-gray-600">{{ $article->staff?->name ?? 'Tidak ada' }}</td>
                                                <td class="px-6 py-4 text-gray-600">{{ $article->category->name ?? 'Tanpa kategori' }}</td>
                                                <td class="px-6 py-4 text-gray-600">{{ $article->created_at->format('d M Y, H:i') }}</td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">Pending</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($pendingArticles->hasPages())
                                <div class="mt-6 flex items-center justify-center gap-1">
                                    <a href="{{ $pendingArticles->previousPageUrl() }}" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors {{ $pendingArticles->onFirstPage() ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}" {{ $pendingArticles->onFirstPage() ? 'aria-disabled=true tabindex=-1' : '' }}>
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                    </a>
                                    <span class="text-sm text-gray-600 font-medium px-2 font-semibold">Halaman {{ $pendingArticles->currentPage() }} dari {{ $pendingArticles->lastPage() }}</span>
                                    <a href="{{ $pendingArticles->nextPageUrl() }}" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors {{ $pendingArticles->currentPage() === $pendingArticles->lastPage() ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}" {{ $pendingArticles->currentPage() === $pendingArticles->lastPage() ? 'aria-disabled=true tabindex=-1' : '' }}>
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                                    </a>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-12">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="text-sm font-semibold text-gray-900 mb-1">Tidak Ada Artikel Menunggu</h3>
                                <p class="text-xs text-gray-500">Semua pengajuan artikel telah diproses.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Per-Article Statistics -->
                <div class="mt-8 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Statistik Artikel</h3>
                            <a href="{{ route('admin.articles.index') }}" class="text-xs font-semibold text-red-500 hover:text-red-600 transition flex items-center gap-1">
                                Lihat Semua Artikel →
                            </a>
                        </div>

                        @if($articles->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Judul Artikel</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Penulis</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Views</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Membantu</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tidak Membantu</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Total Feedback</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($articles as $article)
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 text-gray-900 font-medium">
                                                    <x-truncate-text :value="$article->title" class="block text-gray-700" />
                                                </td>
                                                <td class="px-6 py-4 text-gray-600">{{ $article->staff?->name ?? 'Tidak ada' }}</td>
                                                <td class="px-6 py-4 text-center text-gray-900 font-semibold">{{ $article->views }}</td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">{{ $article->helpful_count }}</span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">{{ $article->not_helpful_count }}</span>
                                                </td>
                                                <td class="px-6 py-4 text-center text-gray-600 font-medium">{{ $article->helpful_count + $article->not_helpful_count }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($articles->hasPages())
                                <div class="mt-6 flex items-center justify-center gap-1">
                                    <a href="{{ $articles->previousPageUrl() }}" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors {{ $articles->onFirstPage() ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}" {{ $articles->onFirstPage() ? 'aria-disabled=true tabindex=-1' : '' }}>
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                    </a>
                                    <span class="text-sm text-gray-600 font-medium px-2 font-semibold">Halaman {{ $articles->currentPage() }} dari {{ $articles->lastPage() }}</span>
                                    <a href="{{ $articles->nextPageUrl() }}" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors {{ $articles->currentPage() === $articles->lastPage() ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}" {{ $articles->currentPage() === $articles->lastPage() ? 'aria-disabled=true tabindex=-1' : '' }}>
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                                    </a>
                                </div>
                            @endif
                        @else
                            <p class="text-center text-gray-500 py-8">Belum ada artikel yang dibuat.</p>
                        @endif
                    </div>
                </div>

                <!-- Statistik Kinerja Staff -->
                <div class="mt-8 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Statistik Kinerja Staff</h3>
                        </div>

                        <div>
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nama Staff</th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tiket Selesai</th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tiket Menunggu</th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Artikel Disetujui</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($staffStats as $staff)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 text-gray-900 font-medium break-all max-w-xs" title="{{ $staff->name }}">
                                                {{ $staff->name }}
                                            </td>
                                            <td class="px-6 py-4 text-center text-gray-900 font-semibold">{{ $staff->tickets_done }}</td>
                                            <td class="px-6 py-4 text-center text-gray-900 font-semibold">{{ $staff->tickets_waiting }}</td>
                                            <td class="px-6 py-4 text-center text-gray-900 font-semibold">{{ $staff->articles_approved }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($staffStats->hasPages())
                            <div class="mt-6 flex items-center justify-center gap-1">
                                <a href="{{ $staffStats->previousPageUrl() }}" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors {{ $staffStats->onFirstPage() ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}" {{ $staffStats->onFirstPage() ? 'aria-disabled=true tabindex=-1' : '' }}>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                </a>
                                <span class="text-sm text-gray-600 font-medium px-2">Halaman {{ $staffStats->currentPage() }} dari {{ $staffStats->lastPage() }}</span>
                                <a href="{{ $staffStats->nextPageUrl() }}" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors {{ $staffStats->currentPage() === $staffStats->lastPage() ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}" {{ $staffStats->currentPage() === $staffStats->lastPage() ? 'aria-disabled=true tabindex=-1' : '' }}>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
