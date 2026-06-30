<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Kelola Artikel
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Kelola Artikel</span>
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
                            <a href="{{ route('admin.dashboard') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
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
                            <a href="{{ route('admin.articles.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
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
                                <p class="text-sm text-gray-500">Kelola publikasi artikel.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Search Input -->
                                <form action="{{ route('admin.articles.index') }}" method="GET" class="flex items-center">
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

                                        <option value="{{ route('admin.articles.index', ['status' => null, 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ !request('status') ? 'selected' : '' }}>
                                            Semua Status
                                        </option>

                                        <option value="{{ route('admin.articles.index', ['status' => 'pending', 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ request('status') == 'pending' ? 'selected' : '' }}>
                                            Menunggu
                                        </option>

                                        <option value="{{ route('admin.articles.index', ['status' => 'approved', 'sort' => request('sort'), 'q' => request('q')]) }}"
                                            {{ request('status') == 'approved' ? 'selected' : '' }}>
                                            Disetujui
                                        </option>

                                        <option value="{{ route('admin.articles.index', ['status' => 'rejected', 'sort' => request('sort'), 'q' => request('q')]) }}"
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

                                        <option value="{{ route('admin.articles.index', ['sort' => 'created_desc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'created_desc' || !request('sort') ? 'selected' : '' }}>
                                            Terbaru - Terlama
                                        </option>

                                        <option value="{{ route('admin.articles.index', ['sort' => 'created_asc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'created_asc' ? 'selected' : '' }}>
                                            Terlama - Terbaru
                                        </option>

                                        <option value="{{ route('admin.articles.index', ['sort' => 'title_asc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'title_asc' ? 'selected' : '' }}>
                                            A - Z
                                        </option>

                                        <option value="{{ route('admin.articles.index', ['sort' => 'title_desc', 'status' => request('status'), 'q' => request('q')]) }}"
                                            {{ request('sort') == 'title_desc' ? 'selected' : '' }}>
                                            Z - A
                                        </option>

                                    </select>
                                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
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
                                    <col style="width: 20%;">
                                </colgroup>
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penulis</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($articles as $article)
                                        <tr data-article-id="{{ $article->id }}" class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-sm font-medium text-gray-900" title="{{ $article->title }}">{{ $article->title }}</div>
                                            </td>

                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-sm text-gray-700" title="{{ $article->staff?->name ?? 'Tidak ada' }}">{{ $article->staff?->name ?? 'Tidak ada' }}</div>
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                @if($article->publish_status === 'pending')
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Menunggu
                                                    </span>
                                                @elseif($article->publish_status === 'approved')
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Disetujui
                                                    </span>
                                                @else
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        Ditolak
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="inline-flex items-center gap-2">
                                                    <a href="{{ route('admin.articles.show', [$article, 'return_url' => request()->fullUrl()]) }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-red-500 hover:text-red-600" title="Lihat">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </a>
 
                                                    @if($article->publish_status === 'pending' || $article->publish_status === 'rejected')
                                                        <button type="button" onclick="confirmApprove('{{ route('admin.articles.approve', [$article, 'return_url' => request()->fullUrl()]) }}')" class="inline-flex items-center px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700" title="Setujui">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                        </button>
                                                    @endif
 
                                                    @if($article->publish_status === 'pending')
                                                        <button type="button" onclick="openRejectModal('{{ route('admin.articles.reject', [$article, 'return_url' => request()->fullUrl()]) }}')" class="inline-flex items-center px-3 py-1.5 rounded-md border border-red-600 text-red-600 hover:bg-red-50" title="Tolak">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>
                                                    @endif
 
                                                    @if($article->publish_status === 'approved')
                                                        <button type="button" onclick="confirmToggleHide('{{ route('admin.articles.toggle-hide', [$article, 'return_url' => request()->fullUrl()]) }}', {{ $article->is_hidden ? 'true' : 'false' }})" class="inline-flex items-center px-3 py-1.5 rounded-md border {{ $article->is_hidden ? 'bg-yellow-50 text-yellow-700 border-yellow-200 hover:bg-yellow-100' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' }}" title="{{ $article->is_hidden ? 'Tampilkan Arsip' : 'Arsipkan' }}">
                                                            @if($article->is_hidden)
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                </svg>
                                                            @else
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                                                </svg>
                                                            @endif
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            @if($articles->hasPages())
                                <div class="p-4 border-t border-gray-100">
                                    <x-pagination :paginator="$articles" :elements="$articles->links()->elements" />
                                </div>
                            @endif
                        @else
                            <x-empty-state
                                icon="document"
                                title="Belum ada artikel"
                                subtitle="Belum ada artikel yang dibuat oleh staf."
                                size="md"
                            />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl border border-gray-200 shadow-xl w-full max-w-lg">
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
                    <button type="button" data-close-modal class="h-10 px-4 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition">Batal</button>
                    <button type="submit" class="h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">Tolak</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Dialogs -->
    <x-confirm-dialog
        id="confirm-approve-article"
        title="Setujui Publikasi"
        message="Apakah Anda yakin ingin menyetujui publikasi artikel ini?"
        primaryText="Setujui"
        secondaryText="Batal"
    />

    <x-confirm-dialog
        id="confirm-archive-article"
        title="Arsipkan Artikel"
        message="Apakah Anda yakin ingin mengarsipkan artikel ini dari publik?"
        primaryText="Arsipkan"
        secondaryText="Batal"
    />

    <x-confirm-dialog
        id="confirm-show-article"
        title="Tampilkan ke Publik"
        message="Apakah Anda yakin ingin menampilkan artikel ini kembali ke publik?"
        primaryText="Tampilkan"
        secondaryText="Batal"
    />

    <script>
    function openRejectModal(actionUrl) {
        const modal = document.getElementById('rejectModal');
        const form = document.getElementById('rejectForm');

        // Set form action URL
        form.action = actionUrl;

        // Clear previous rejection note
        form.querySelector('textarea[name="rejection_note"]').value = '';

        // Show modal
        modal.classList.remove('hidden');
    }

    function confirmApprove(actionUrl) {
        window.confirmDialog.open('confirm-approve-article', {
            onConfirm: function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function confirmToggleHide(actionUrl, isHidden) {
        const dialogId = isHidden ? 'confirm-show-article' : 'confirm-archive-article';
        window.confirmDialog.open(dialogId, {
            onConfirm: function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    </script>
</x-app-layout>
