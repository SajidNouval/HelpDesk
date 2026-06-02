<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Dashboard Admin
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
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
                        Menu Admin
                    </h3>

                    <ul class="space-y-3 text-gray-700">
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

                <!-- Statistics Overview -->
                <div class="mb-6">
                    <p class="text-gray-600">
                        Total <span class="font-medium">{{ $staffCount }}</span> staf dan <span class="font-medium">{{ $articleCount }}</span> artikel
                    </p>
                </div>

                <!-- Global Ticket Summary -->
                <div class="mb-6 grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-xs uppercase tracking-[0.15em] text-gray-500">Total Tiket</p>
                        <h3 class="text-2xl font-semibold mt-2 text-gray-900">{{ $totalTickets }}</h3>
                    </div>
                    <div class="p-4 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-xs uppercase tracking-[0.15em] text-gray-500">Tiket Waiting</p>
                        <h3 class="text-2xl font-semibold mt-2 text-gray-900">{{ $ticketsWaiting }}</h3>
                    </div>
                    <div class="p-4 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-xs uppercase tracking-[0.15em] text-gray-500">Tiket Diproses</p>
                        <h3 class="text-2xl font-semibold mt-2 text-gray-900">{{ $ticketsProcessing }}</h3>
                    </div>
                    <div class="p-4 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-xs uppercase tracking-[0.15em] text-gray-500">Tiket Selesai</p>
                        <h3 class="text-2xl font-semibold mt-2 text-gray-900">{{ $ticketsDone }}</h3>
                    </div>
                </div>

                <div class="mb-6 p-6 bg-white border border-gray-200 rounded-3xl shadow-sm">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-[0.2em] text-gray-500">Live Service</p>
                            <h3 class="text-2xl font-semibold mt-2 text-gray-900">
                                {{ $liveServiceEnabled ? 'Aktif' : 'Nonaktif' }}
                            </h3>
                            <p class="mt-2 text-sm text-gray-600">
                                Live chat tiket hanya akan tersedia jika layanan ini aktif. Laporan/report tetap dapat dibuat kapan saja.
                            </p>
                        </div>
                        <form action="{{ route('admin.live-service.toggle') }}" method="POST" class="flex items-center gap-3">
                            @csrf
                            <input type="hidden" name="status" value="{{ $liveServiceEnabled ? 'off' : 'on' }}">
                            <x-primary-button type="submit" class="rounded-3xl px-5 py-3">
                                {{ $liveServiceEnabled ? 'Matikan Live Service' : 'Nyalakan Live Service' }}
                            </x-primary-button>
                        </form>
                    </div>
                </div>

                <!-- Chatbot Statistics + Top Lists -->
                <div class="mb-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="p-6 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-sm uppercase tracking-[0.2em] text-gray-500">Statistik Chatbot</p>
                        <div class="mt-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Total Pertanyaan</span>
                                <span class="font-semibold text-gray-900">{{ $chatbotStats['total_questions'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Pertanyaan Hari Ini</span>
                                <span class="font-semibold text-gray-900">{{ $chatbotStats['today'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Terjawab</span>
                                <span class="font-semibold text-gray-900">{{ $chatbotStats['answered'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Tidak Terjawab</span>
                                <span class="font-semibold text-gray-900">{{ $chatbotStats['unanswered'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-sm uppercase tracking-[0.2em] text-gray-500">Top Artikel Terpopuler</p>
                        <div class="mt-4 space-y-3">
                            @foreach($topArticles as $ta)
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($ta->title, 60) }}</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $ta->views }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-6 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-sm uppercase tracking-[0.2em] text-gray-500">Top Performer Staff</p>
                        <div class="mt-4 space-y-3">
                            @foreach($topStaff as $ts)
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-700">{{ $ts->name }}</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $ts->completed_count ?? 0 }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Pending Articles Review Section -->
                <div class="space-y-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Artikel Menunggu Persetujuan</h2>
                        </div>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                            {{ $pendingArticles->count() }} artikel
                        </span>
                    </div>

                    @if ($pendingArticles->count() > 0)
                        <div class="space-y-4">
                            @foreach ($pendingArticles as $article)
                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex justify-between gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <h3 class="font-semibold text-gray-900">{{ $article->title }}</h3>
                                                    <x-status-badge status="pending" />
                                                </div>

                                                <p class="text-sm text-gray-600 mb-2">Penulis: <span class="font-medium">{{ $article->staff?->name ?? 'Tidak ada' }}</span></p>

                                                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                                                    <span>{{ $article->category->name ?? 'Tanpa kategori' }}</span>
                                                    <span>•</span>
                                                    <span>{{ $article->created_at->format('d M Y, H:i') }}</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <form method="POST" action="{{ route('admin.articles.approve', $article) }}" style="display: inline;">
                                                    @csrf
                                                    <x-primary-button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold">
                                                        Setujui
                                                    </x-primary-button>
                                                </form>
                                                <x-danger-button type="button" data-open-modal="#rejectModal" data-modal-form-action="/admin/articles/{id}/reject" data-article-id="{{ $article->id }}" class="inline-flex items-center px-3 py-2 text-xs font-semibold">
                                                    Tolak
                                                </x-danger-button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-16 bg-white border border-gray-200 rounded-lg">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">Tidak Ada Artikel Menunggu</h3>
                        </div>
                    @endif
                </div>

                <!-- Per-Article Statistics -->
                <div class="mt-8 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistik Artikel</h3>

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
                                                <td class="px-6 py-4 text-gray-900 font-medium">{{ $article->title }}</td>
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
                                <div class="mt-6 flex items-center justify-center gap-4">
                                    <a href="{{ $articles->previousPageUrl() }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold {{ $articles->onFirstPage() ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $articles->onFirstPage() ? 'aria-disabled=true tabindex=-1' : '' }}>&lt;</a>
                                    <span class="text-sm text-gray-600 font-semibold">Halaman {{ $articles->currentPage() }} dari {{ $articles->lastPage() }}</span>
                                    <a href="{{ $articles->nextPageUrl() }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold {{ $articles->currentPage() === $articles->lastPage() ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $articles->currentPage() === $articles->lastPage() ? 'aria-disabled=true tabindex=-1' : '' }}>&gt;</a>
                                </div>
                            @endif
                        @else
                            <p class="text-center text-gray-500 py-8">Belum ada artikel yang dibuat.</p>
                        @endif
                    </div>
                </div>

                <!-- Statistik Kinerja Staff -->
                <div class="mt-8 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Statistik Kinerja Staff</h3>
                            
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($staffStats as $staff)
                                <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="font-semibold text-gray-800">{{ $staff->name }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $staff->id }}</div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                                        <div class="flex justify-between"><span>Total Selesai</span><span class="font-semibold text-gray-900">{{ $staff->tickets_done }}</span></div>
                                        <div class="flex justify-between"><span>Total Ditolak</span><span class="font-semibold text-gray-900">{{ $staff->tickets_rejected }}</span></div>
                                        <div class="flex justify-between"><span>Total Menunggu</span><span class="font-semibold text-gray-900">{{ $staff->tickets_waiting }}</span></div>
                                        <div class="flex justify-between"><span>Artikel Disetujui</span><span class="font-semibold text-gray-900">{{ $staff->articles_approved }}</span></div>
                                        <div class="flex justify-between col-span-2"><span>Artikel Ditolak</span><span class="font-semibold text-gray-900">{{ $staff->articles_rejected }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex items-center justify-center gap-4">
                            <a href="{{ $staffStats->previousPageUrl() }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold {{ $staffStats->onFirstPage() ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $staffStats->onFirstPage() ? 'aria-disabled=true tabindex=-1' : '' }}>&lt;</a>
                            <span class="text-sm text-gray-600 font-semibold">Halaman {{ $staffStats->currentPage() }} dari {{ $staffStats->lastPage() }}</span>
                            <a href="{{ $staffStats->nextPageUrl() }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold {{ $staffStats->currentPage() === $staffStats->lastPage() ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $staffStats->currentPage() === $staffStats->lastPage() ? 'aria-disabled=true tabindex=-1' : '' }}>&gt;</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-lg">
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">Tolak Artikel</h3>
            </div>
            <form method="POST" id="rejectForm" data-ajax data-close-on-success class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Catatan Penolakan</label>
                    <textarea name="rejection_note" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none" rows="4" placeholder="Jelaskan alasan penolakan..." required></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <x-secondary-button type="button" data-close-modal class="px-4 py-2 text-sm rounded-xl">Batal</x-secondary-button>
                    <x-danger-button type="submit" class="px-4 py-2 text-sm rounded-xl">Tolak</x-danger-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
