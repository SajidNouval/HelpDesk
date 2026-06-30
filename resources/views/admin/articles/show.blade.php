<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Tinjau Artikel
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Kelola Artikel</a>
                <span class="mx-2 text-gray-400">/</span>
                <x-truncate-text :value="$article->title" :limit="40" class="text-gray-700" />
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

            <!-- Main Content Area -->
            <div class="col-span-12 md:col-span-9">

                <!-- Single Article Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="p-6">

                        <!-- Title -->
                        <div class="mb-3">
                            <h1 class="text-3xl font-semibold text-gray-900">{{ $article->title }}</h1>
                        </div>

                        <!-- Status Badges -->
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            @if($article->publish_status === 'approved')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Disetujui</span>
                            @elseif($article->publish_status === 'pending')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Menunggu Persetujuan</span>
                            @elseif($article->publish_status === 'rejected')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Ditolak</span>
                            @endif
                            @if($article->is_hidden)
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Diarsipkan</span>
                            @endif
                        </div>

                        <!-- Meta Info -->
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 mb-4">
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span>{{ $article->staff?->name ?? 'Tidak ada' }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>{{ $article->created_at->format('d M Y') }}</span>
                            </div>
                        </div>

                        @if($article->rejection_note)
                            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-red-900 mb-1 text-sm">Catatan Penolakan</h4>
                                    <p class="text-red-800 text-sm">{{ $article->rejection_note }}</p>
                                </div>
                            </div>
                        @endif

                        <!-- Summary -->
                        @if($article->excerpt)
                            <div class="mb-4">
                                <p class="text-sm text-gray-700 leading-relaxed">{{ $article->excerpt }}</p>
                            </div>
                        @endif

                        <!-- Article Content -->
                        <div class="mb-4">
                            <div class="prose prose-gray max-w-none">
                                {!! $article->content !!}
                            </div>
                        </div>

                        <!-- Keywords -->
                        @if($article->keywords)
                            <div class="mb-4">
                                <p class="text-sm text-gray-600 mb-2">Kata kunci: {{ implode(', ', array_map('trim', explode(',', $article->keywords))) }}</p>
                            </div>
                        @endif

                        <!-- Category -->
                        <div class="mb-6">
                            <p class="text-sm text-gray-600">Kategori: {{ $article->category?->name ?? 'Tanpa kategori' }}</p>
                        </div>

                        <!-- Compact Statistics -->
                        <div class="flex flex-wrap items-center justify-end gap-4 mb-6 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <span><span class="font-semibold text-gray-900">{{ $article->views }}</span> Views</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                                </svg>
                                <span><span class="font-semibold text-green-600">{{ $article->helpful_count }}</span> Membantu</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76.94m-7 10v5a2 2 0 002 2h.096c-.5 0-.905-.405-.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"></path>
                                </svg>
                                <span><span class="font-semibold text-red-600">{{ $article->not_helpful_count }}</span> Tidak Membantu</span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 border-t border-gray-200 flex flex-wrap items-center justify-end gap-3">
                            @if($article->publish_status !== 'approved')
                                <button type="button" onclick="confirmApprove('{{ route('admin.articles.approve', [$article, 'return_url' => request('return_url')]) }}')" class="inline-flex items-center h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Setujui
                                </button>
                                <button type="button" onclick="openRejectModal('{{ route('admin.articles.reject', [$article, 'return_url' => request('return_url')]) }}')"
                                        class="inline-flex items-center h-10 px-5 rounded-xl border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Tolak
                                </button>
                            @endif
                            @if($article->publish_status === 'approved')
                                <button type="button" onclick="confirmToggleHide('{{ route('admin.articles.toggle-hide', [$article, 'return_url' => request('return_url')]) }}', {{ $article->is_hidden ? 'true' : 'false' }})" class="inline-flex items-center h-10 px-5 rounded-xl border {{ $article->is_hidden ? 'bg-yellow-50 text-yellow-700 border-yellow-200 hover:bg-yellow-100' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' }} text-sm font-medium transition gap-2" title="{{ $article->is_hidden ? 'Tampilkan Arsip' : 'Arsipkan' }}">
                                    @if($article->is_hidden)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Tampilkan Arsip
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                        </svg>
                                        Arsipkan
                                    @endif
                                </button>
                            @endif
                            <a href="{{ request('return_url', route('admin.articles.index')) }}"
                               class="inline-flex items-center h-10 px-5 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Kembali
                            </a>
                        </div>
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
                <input type="hidden" name="return_url" value="{{ request('return_url') }}">
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