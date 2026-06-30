<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Kelola Artikel
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Artikel</span>
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
                            <a href="{{ route('staff.dashboard') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.tickets.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Tiket
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>

                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">



                <!-- Header Section with Statistics -->
                <div class="mb-6 bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Daftar Artikel</h2>
                                <p class="text-sm text-gray-500">Kelola artikel anda.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Search Input -->
                                <form action="{{ route('staff.articles.index') }}" method="GET" class="flex items-center">
                                    @if(request('sort'))
                                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                                    @endif
                                    @if(request('status'))
                                        <input type="hidden" name="status" value="{{ request('status') }}">
                                    @endif
                                    <div class="relative">
                                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari artikel..." class="w-48 h-10 pl-9 pr-4 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </form>

                                <!-- Status Filter Dropdown -->
                                <div class="relative">
                                    <select id="statusSelect"
                                            onchange="window.location.href=this.value"
                                            class="h-10 pl-4 pr-8 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none cursor-pointer">

                                        <option value="{{ route('staff.articles.index', ['status' => null, 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ !request('status') ? 'selected' : '' }}>
                                            Semua Status
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['status' => 'pending', 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ request('status') == 'pending' ? 'selected' : '' }}>
                                            Menunggu
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['status' => 'approved', 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ request('status') == 'approved' ? 'selected' : '' }}>
                                            Disetujui
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['status' => 'rejected', 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ request('status') == 'rejected' ? 'selected' : '' }}>
                                            Ditolak
                                        </option>

                                    </select>
                                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>

                                <!-- Sort Dropdown -->
                                <div class="relative">
                                    <select id="sortSelect"
                                            onchange="window.location.href=this.value"
                                            class="h-10 pl-4 pr-8 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none cursor-pointer">

                                        <option value="{{ route('staff.articles.index', ['sort' => 'created_desc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'created_desc' ? 'selected' : '' }}>
                                            Terbaru - Terlama
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['sort' => 'created_asc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'created_asc' ? 'selected' : '' }}>
                                            Terlama - Terbaru
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['sort' => 'title_asc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'title_asc' ? 'selected' : '' }}>
                                            A - Z
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['sort' => 'title_desc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'title_desc' ? 'selected' : '' }}>
                                            Z - A
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['sort' => 'views_desc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'views_desc' ? 'selected' : '' }}>
                                            Views Terbanyak
                                        </option>

                                        <option value="{{ route('staff.articles.index', ['sort' => 'views_asc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'views_asc' ? 'selected' : '' }}>
                                            Views Tersedikit
                                        </option>

                                    </select>
                                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>

                                <!-- Create Button -->
                                <a href="{{ route('staff.articles.create', ['return_url' => request()->fullUrl()]) }}" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Buat Artikel
                                </a>
                            </div>
                        </div>
 
                        <div class="flex flex-wrap items-center gap-6 pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Artikel</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($totalArticles) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Menunggu</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($pendingArticles) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Disetujui</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($approvedArticles) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Ditolak</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($rejectedArticles) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
 
                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-4">
                        @if($articles->count())
                            <table class="divide-y divide-gray-100" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col style="width: 45%;">
                                    <col style="width: 20%;">
                                    <col style="width: 15%;">
                                    <col style="width: 10%;">
                                    <col style="width: 10%;">
                                </colgroup>
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($articles as $article)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 overflow-hidden">
                                                <a href="{{ route('staff.articles.show', [$article, 'return_url' => request()->fullUrl()]) }}" class="truncate text-gray-900 font-medium hover:text-red-600 transition block">
                                                    {{ $article->title }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-gray-600 text-sm">{{ $article->category->name ?? 'Tanpa kategori' }}</div>
                                            </td>
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="text-gray-900 font-semibold text-sm text-center">{{ $article->views }}</div>
                                            </td>
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="text-center">
                                                    @if($article->publish_status === 'pending')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Menunggu</span>
                                                    @elseif($article->publish_status === 'approved')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disetujui</span>
                                                    @elseif($article->publish_status === 'rejected')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Ditolak</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <a href="{{ route('staff.articles.show', [$article, 'return_url' => request()->fullUrl()]) }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-red-500 hover:text-red-600 text-sm" title="Lihat">
                                                    Lihat
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <x-empty-state
                                icon="document"
                                title="Belum Ada Artikel"
                                subtitle="Mulai buat artikel bantuan pertama Anda untuk membantu pelanggan."
                                actionText="Buat Artikel Baru"
                                actionUrl="{{ route('staff.articles.create', ['return_url' => request()->fullUrl()]) }}"
                                actionIcon="plus"
                                size="md"
                            />
                        @endif
                    </div>

                    @if($articles->hasPages())
                        <div class="p-4 border-t border-gray-100">
                            <x-pagination :paginator="$articles" :elements="$articles->links()->elements" />
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

</x-app-layout>