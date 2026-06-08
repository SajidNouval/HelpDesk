<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
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
                    </ul>

                    
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

                <!-- Statistics Overview -->
                <div class="mb-6 bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Ringkasan Statistik</h3>

                        <!-- Main Stats Row -->
                        <div class="flex flex-wrap items-center gap-6 mb-5 pb-5 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Artikel</p>
                                    <p class="text-xl font-bold text-gray-900">{{ $totalArticles }}</p>
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

                        <!-- Article Status Row -->
                        <div class="flex flex-wrap items-center gap-6 mb-5 pb-5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                                <span class="text-sm text-gray-600">Artikel Disetujui: <strong class="text-gray-900">{{ $articlesApproved }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                                <span class="text-sm text-gray-600">Artikel Pending: <strong class="text-gray-900">{{ $articlesPending }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                                <span class="text-sm text-gray-600">Artikel Ditolak: <strong class="text-gray-900">{{ $articlesRejected }}</strong></span>
                            </div>
                        </div>

                        <!-- Ticket Status Row -->
                        <div class="flex flex-wrap items-center gap-6 mb-5 pb-5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                                <span class="text-sm text-gray-600">Tiket Waiting: <strong class="text-gray-900">{{ $ticketsWaiting }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                                <span class="text-sm text-gray-600">Tiket Diproses: <strong class="text-gray-900">{{ $ticketsProcessing }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                                <span class="text-sm text-gray-600">Tiket Selesai: <strong class="text-gray-900">{{ $ticketsDone }}</strong></span>
                            </div>
                        </div>

                        <!-- Live Service Status Row -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $liveServiceEnabled ? 'bg-green-400' : 'bg-gray-400' }}"></span>
                            <span class="text-sm text-gray-600">Status Live Service: <strong class="text-gray-900">{{ $liveServiceEnabled ? 'Aktif' : 'Nonaktif' }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Tiket Hari Ini Section -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Tiket Hari Ini</h3>
                                <p class="text-sm text-gray-500">Daftar tiket masuk hari ini yang ditugaskan kepada Anda.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-0.5 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                    {{ $todayTickets->count() }} tiket
                                </span>
                                <a href="{{ route('staff.tickets.index') }}" class="text-xs font-semibold text-red-500 hover:text-red-600 transition flex items-center gap-1">
                                    Lihat Semua Tiket →
                                </a>
                            </div>
                        </div>

                        @if ($todayTickets->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Subjek</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kategori</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pelapor</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($sortedTickets->take(10) as $ticket)
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 text-gray-900 font-medium">
                                                    <x-truncate-text :value="$ticket->subject" class="block text-gray-700" />
                                                </td>
                                                <td class="px-6 py-4 text-gray-600">{{ $ticket->category->name ?? 'Tanpa kategori' }}</td>
                                                <td class="px-6 py-4 text-gray-600">{{ $ticket->name }}</td>
                                                <td class="px-6 py-4 text-gray-600">{{ $ticket->created_at->format('d M Y, H:i') }}</td>
                                                <td class="px-6 py-4 text-center">
                                                    <x-status-badge :status="$ticket->status" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($todayTickets->count() > 10)
                                <div class="mt-4 text-right">
                                    <a href="{{ route('staff.tickets.index') }}" class="text-sm font-semibold text-red-500 hover:text-red-600 transition inline-flex items-center gap-1">
                                        Lihat Semua Tiket →
                                    </a>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-12">
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
    </div>
</x-app-layout>
