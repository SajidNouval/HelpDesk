<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Dashboard Staf
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Pantau aktivitas tiket, artikel, dan status layanan Anda.
            </p>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
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
                        Menu Staf
                    </h3>

                    <ul class="space-y-1 text-gray-700">
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
                    <div class="mt-8 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h4 class="font-semibold text-gray-700 mb-2">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Right -->
            @php
                $totalTickets = Auth::user()->tickets()->count();
                $ticketsWaiting = Auth::user()->tickets()->where('status', 'waiting')->count();
                $ticketsProcessing = Auth::user()->tickets()->whereIn('status', ['assigned', 'progress'])->count();
                $ticketsDone = Auth::user()->tickets()->where('status', 'closed')->count();

                $totalArticles = Auth::user()->articles()->count();
                $articlesApproved = Auth::user()->articles()->where('publish_status', 'approved')->count();
                $articlesPending = Auth::user()->articles()->where('publish_status', 'pending')->count();
                $articlesRejected = Auth::user()->articles()->where('publish_status', 'rejected')->count();

                $helpfulFeedbackCount = \App\Models\ArticleFeedback::whereHas('article', function($q) {
                    $q->where('staff_id', auth()->id());
                })->where('is_helpful', true)->count();

                $notHelpfulFeedbackCount = \App\Models\ArticleFeedback::whereHas('article', function($q) {
                    $q->where('staff_id', auth()->id());
                })->where('is_helpful', false)->count();

                $sortedTickets = $todayTickets->sortByDesc('created_at');
            @endphp

            <div class="col-span-12 md:col-span-9">

                <!-- Live Service Status Card -->
                <div class="mb-6 p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Live Service</span>
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $liveServiceEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $liveServiceEnabled ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">
                                Live chat tiket hanya akan tersedia jika layanan ini aktif. Laporan/report tetap dapat dibuat kapan saja.
                            </p>
                        </div>
                        <div class="shrink-0">
                            <span class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-sm font-semibold {{ $liveServiceEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $liveServiceEnabled ? 'Live Chat Dibuka' : 'Live Chat Ditutup' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Section: Statistik Artikel & Tiket -->
                <div class="mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Card 1: Statistik Artikel -->
                        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Statistik Artikel</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                    <span class="text-gray-600">Total Artikel</span>
                                    <span class="font-semibold text-gray-900 text-base">{{ $totalArticles }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                    <span class="text-gray-600">Disetujui</span>
                                    <span class="font-semibold text-green-600 text-base">{{ $articlesApproved }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                    <span class="text-gray-600">Pending</span>
                                    <span class="font-semibold text-yellow-600 text-base">{{ $articlesPending }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Ditolak</span>
                                    <span class="font-semibold text-red-600 text-base">{{ $articlesRejected }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Statistik Tiket -->
                        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Statistik Tiket</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                    <span class="text-gray-600">Total Tiket</span>
                                    <span class="font-semibold text-gray-900 text-base">{{ $totalTickets }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                    <span class="text-gray-600">Waiting</span>
                                    <span class="font-semibold text-yellow-600 text-base">{{ $ticketsWaiting }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                    <span class="text-gray-600">Diproses</span>
                                    <span class="font-semibold text-red-600 text-base">{{ $ticketsProcessing }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Selesai</span>
                                    <span class="font-semibold text-green-600 text-base">{{ $ticketsDone }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tiket Hari Ini Section -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Tiket Hari Ini</h2>
                            <p class="text-sm text-gray-500">Daftar tiket masuk hari ini yang ditugaskan kepada Anda.</p>
                        </div>
                        <span class="px-2.5 py-0.5 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                            {{ $todayTickets->count() }} tiket
                        </span>
                    </div>

                    @if ($todayTickets->count() > 0)
                        <div class="grid grid-cols-1 gap-4">
                            @foreach ($sortedTickets->take(5) as $ticket)
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 hover:shadow-md transition">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold text-gray-900 text-sm">
                                                    <x-truncate-text :value="$ticket->subject" />
                                                </h3>
                                                <x-status-badge :status="$ticket->status" />
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                                <span>Kategori: <strong class="text-gray-700 font-medium">{{ $ticket->category->name ?? 'Tanpa kategori' }}</strong></span>
                                                <span>•</span>
                                                <span>Pelapor: <strong class="text-gray-700 font-medium">{{ $ticket->name }}</strong></span>
                                                <span>•</span>
                                                <span>{{ $ticket->created_at->format('d M Y, H:i') }}</span>
                                            </div>
                                        </div>
                                        <div class="shrink-0">
                                            <a href="{{ route('staff.tickets.index') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium transition">
                                                Kelola Tiket →
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($todayTickets->count() > 5)
                            <div class="mt-4 text-right">
                                <a href="{{ route('staff.tickets.index') }}" class="text-sm font-semibold text-red-500 hover:text-red-600 transition inline-flex items-center gap-1">
                                    Lihat Semua Tiket →
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-12 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">Tidak Ada Tiket Hari Ini</h3>
                            <p class="text-xs text-gray-500">Belum ada tiket yang ditugaskan hari ini.</p>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
