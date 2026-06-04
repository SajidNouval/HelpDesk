<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Dashboard Admin
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Pantau aktivitas helpdesk, artikel, staf, dan performa sistem.
            </p>

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

                <!-- Statistics Overview Grid -->
                <div class="mb-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <!-- Total Staff -->
                    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Total Staff</p>
                        <h3 class="text-2xl font-bold mt-2 text-gray-900">{{ $staffCount }}</h3>
                    </div>
                    <!-- Total Artikel -->
                    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Total Artikel</p>
                        <h3 class="text-2xl font-bold mt-2 text-gray-900">{{ $articleCount }}</h3>
                    </div>
                    <!-- Total Tiket -->
                    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Total Tiket</p>
                        <h3 class="text-2xl font-bold mt-2 text-gray-900">{{ $totalTickets }}</h3>
                    </div>
                    <!-- Tiket Waiting -->
                    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Tiket Waiting</p>
                        <h3 class="text-2xl font-bold mt-2 text-gray-900">{{ $ticketsWaiting }}</h3>
                    </div>
                    <!-- Tiket Diproses -->
                    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Tiket Diproses</p>
                        <h3 class="text-2xl font-bold mt-2 text-gray-900">{{ $ticketsProcessing }}</h3>
                    </div>
                    <!-- Tiket Selesai -->
                    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Tiket Selesai</p>
                        <h3 class="text-2xl font-bold mt-2 text-gray-900">{{ $ticketsDone }}</h3>
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

                <!-- Top Artikel Terpopuler -->
                <div class="mb-6 bg-white border border-gray-200 rounded-xl shadow-sm p-6">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Top Artikel Terpopuler</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="text-left pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Judul</th>
                                    <th class="text-right pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Views</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($topArticles as $ta)
                                    <tr>
                                        <td class="py-2.5 text-gray-700 pr-2">
                                            <x-truncate-text :value="$ta->title" :limit="80" class="text-xs font-medium text-gray-900 block" />
                                        </td>
                                        <td class="py-2.5 text-right font-semibold text-gray-900 text-xs whitespace-nowrap">
                                            {{ $ta->views }} Views
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pending Articles Review Section -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Artikel Menunggu Persetujuan</h2>
                            <p class="text-sm text-gray-500">Daftar usulan artikel baru dari staf yang perlu Anda tinjau.</p>
                        </div>
                        <span class="px-2.5 py-0.5 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                            {{ $pendingArticles->count() }} artikel
                        </span>
                    </div>

                    @if ($pendingArticles->count() > 0)
                        <div class="grid grid-cols-1 gap-4">
                            @foreach ($pendingArticles as $article)
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 hover:shadow-md transition">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold text-gray-900 text-sm">{{ $article->title }}</h3>
                                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-[10px] font-semibold rounded-full">Pending</span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                                <span>Penulis: <strong class="text-gray-700 font-medium">{{ $article->staff?->name ?? 'Tidak ada' }}</strong></span>
                                                <span>•</span>
                                                <span>Kategori: <strong class="text-gray-700 font-medium">{{ $article->category->name ?? 'Tanpa kategori' }}</strong></span>
                                                <span>•</span>
                                                <span>{{ $article->created_at->format('d M Y, H:i') }}</span>
                                            </div>
                                        </div>
                                        <div class="shrink-0">
                                            <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium transition">
                                                Tinjau di Kelola Artikel
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">Tidak Ada Artikel Menunggu</h3>
                            <p class="text-xs text-gray-500">Semua pengajuan artikel telah diproses.</p>
                        </div>
                    @endif
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
