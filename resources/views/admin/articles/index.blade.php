<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-900">Daftar Artikel</h2>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        @if (session('success'))
                            <div class="mb-6 p-4 bg-green-100 border border-green-200 rounded-lg text-green-800 flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="font-medium">{{ session('success') }}</span>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="mb-6 p-4 bg-red-100 border border-red-200 rounded-lg text-red-800 flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <span class="font-medium">{{ session('error') }}</span>
                            </div>
                        @endif

                        <div id="ajaxNotification" class="fixed top-6 right-6 z-50 space-y-3"></div>

                        @if($articles->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-gray-600 font-semibold">Artikel</th>
                                            <th class="px-4 py-3 text-left text-gray-600 font-semibold">Penulis</th>
                                            <th class="px-4 py-3 text-center text-gray-600 font-semibold">Views</th>
                                            <th class="px-4 py-3 text-center text-gray-600 font-semibold">Feedback</th>
                                            <th class="px-4 py-3 text-center text-gray-600 font-semibold">Status</th>
                                            <th class="px-4 py-3 text-center text-gray-600 font-semibold">Visibilitas</th>
                                            <th class="px-4 py-3 text-center text-gray-600 font-semibold">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($articles as $article)
                                            <tr data-article-id="{{ $article->id }}" class="hover:bg-gray-50 transition">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center">
                                                        <div>
                                                            <div class="text-gray-900 font-medium">{{ Str::limit($article->title, 40) }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center">
                                                        <span class="text-gray-600">{{ $article->staff?->name ?? 'Tidak ada' }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="article-views px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">{{ $article->views }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <div class="flex flex-col gap-1">
                                                        <span class="article-helpful px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">{{ $article->helpful_count }}</span>
                                                        <span class="article-not-helpful px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">{{ $article->not_helpful_count }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    @if($article->publish_status === 'pending')
                                                        <span class="article-status px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">Menunggu</span>
                                                    @elseif($article->publish_status === 'approved')
                                                        <span class="article-status px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Disetujui</span>
                                                    @elseif($article->publish_status === 'rejected')
                                                        <span class="article-status px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Ditolak</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    @if($article->is_hidden)
                                                        <span class="article-visibility px-2 py-1 bg-gray-200 text-gray-800 rounded text-xs font-semibold">Disembunyikan</span>
                                                    @else
                                                        <span class="article-visibility px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-semibold">Publik</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <div class="flex flex-col gap-2">
                                                        <div class="article-action-pending {{ $article->publish_status !== 'pending' ? 'hidden' : '' }}">
                                                            <div class="flex items-center justify-center gap-2">
                                                                <a href="{{ route('admin.articles.show', $article) }}" class="inline-flex items-center text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                                                    Lihat
                                                                </a>
                                                                <form method="POST" action="{{ route('admin.articles.approve', $article) }}" class="ajax-action-form inline" data-article-id="{{ $article->id }}">
                                                                    @csrf
                                                                    <button type="submit" class="inline-flex items-center text-xs px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                                                        Setujui
                                                                    </button>
                                                                </form>
                                                                <button type="button" onclick="openRejectModal({{ $article->id }})" class="inline-flex items-center text-xs px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition">
                                                                    Tolak
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="article-action-rejected {{ $article->publish_status !== 'rejected' ? 'hidden' : '' }}">
                                                            <div class="flex items-center justify-center gap-2">
                                                                <a href="{{ route('admin.articles.show', $article) }}" class="inline-flex items-center text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                                                    Lihat
                                                                </a>
                                                                <form method="POST" action="{{ route('admin.articles.approve', $article) }}" class="ajax-action-form inline" data-article-id="{{ $article->id }}">
                                                                    @csrf
                                                                    <button type="submit" class="inline-flex items-center text-xs px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                                                        Setujui
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <div class="article-action-approved {{ $article->publish_status !== 'approved' ? 'hidden' : '' }}">
                                                            <div class="flex items-center justify-center gap-2">
                                                                <a href="{{ route('admin.articles.show', $article) }}" class="inline-flex items-center text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                                                    Lihat
                                                                </a>
                                                                <form method="POST" action="{{ route('admin.articles.toggle-hide', $article) }}" class="ajax-action-form inline" data-article-id="{{ $article->id }}">
                                                                    @csrf
                                                                    <button type="submit" class="toggle-visibility-button inline-flex items-center text-xs px-2 py-1 rounded transition {{ $article->is_hidden ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-yellow-600 text-white hover:bg-yellow-700' }}">
                                                                        @if($article->is_hidden)
                                                                            Tampilkan
                                                                        @else
                                                                            Sembunyikan
                                                                        @endif
                                                                    </button>
                                                                </form>

                                                                <form method="POST" action="{{ route('admin.articles.reset-views', $article) }}" class="ajax-action-form inline" data-article-id="{{ $article->id }}">
                                                                    @csrf
                                                                    <button type="submit" class="inline-flex items-center text-xs px-2 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 transition" title="Reset Views">
                                                                        Reset View
                                                                    </button>
                                                                </form>

                                                                <form method="POST" action="{{ route('admin.articles.reset-feedback', $article) }}" class="ajax-action-form inline" data-article-id="{{ $article->id }}" data-confirm="Hapus semua feedback artikel ini?">
                                                                    @csrf
                                                                    <button type="submit" class="inline-flex items-center text-xs px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition" title="Reset Feedback">
                                                                        Reset Feedback
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
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
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada artikel</h3>
                                <p class="mt-1 text-sm text-gray-500">Belum ada artikel yang dibuat oleh staf.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tolak Artikel</h3>
            <form method="POST" id="rejectForm">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Catatan Penolakan</label>
                    <textarea name="rejection_note" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" rows="4" placeholder="Jelaskan alasan penolakan..." required></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const notificationContainer = document.getElementById('ajaxNotification');
        let currentArticleId = null;

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        }

        function showNotification(message, type = 'success') {
            if (!notificationContainer) {
                alert(message);
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = `px-4 py-3 rounded-lg text-white shadow ${type === 'error' ? 'bg-red-600' : 'bg-green-600'}`;
            wrapper.textContent = message;

            notificationContainer.appendChild(wrapper);

            setTimeout(() => {
                wrapper.remove();
            }, 4000);
        }

        function setStatusBadge(row, status) {
            const statusEl = row.querySelector('.article-status');
            if (!statusEl) return;

            statusEl.className = 'article-status px-3 py-1 rounded-full text-xs font-semibold';

            if (status === 'pending') {
                statusEl.classList.add('bg-yellow-100', 'text-yellow-800');
                statusEl.textContent = 'Menunggu';
            } else if (status === 'approved') {
                statusEl.classList.add('bg-green-100', 'text-green-800');
                statusEl.textContent = 'Disetujui';
            } else if (status === 'rejected') {
                statusEl.classList.add('bg-red-100', 'text-red-800');
                statusEl.textContent = 'Ditolak';
            }
        }

        function setVisibilityBadge(row, isHidden) {
            const visibilityEl = row.querySelector('.article-visibility');
            if (!visibilityEl) return;

            visibilityEl.className = 'article-visibility px-2 py-1 rounded text-xs font-semibold';
            if (isHidden) {
                visibilityEl.classList.add('bg-gray-200', 'text-gray-800');
                visibilityEl.textContent = 'Disembunyikan';
            } else {
                visibilityEl.classList.add('bg-blue-100', 'text-blue-800');
                visibilityEl.textContent = 'Publik';
            }
        }

        function updateActionVisibility(row, publishStatus) {
            const pendingBlock = row.querySelector('.article-action-pending');
            const rejectedBlock = row.querySelector('.article-action-rejected');
            const approvedBlock = row.querySelector('.article-action-approved');

            if (pendingBlock) pendingBlock.classList.toggle('hidden', publishStatus !== 'pending');
            if (rejectedBlock) rejectedBlock.classList.toggle('hidden', publishStatus !== 'rejected');
            if (approvedBlock) approvedBlock.classList.toggle('hidden', publishStatus !== 'approved');
        }

        function updateToggleButton(row, isHidden) {
            const toggleButton = row.querySelector('.toggle-visibility-button');
            if (!toggleButton) return;

            toggleButton.textContent = isHidden ? 'Tampilkan' : 'Sembunyikan';
            toggleButton.className = 'toggle-visibility-button inline-flex items-center text-xs px-2 py-1 rounded transition';
            if (isHidden) {
                toggleButton.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
            } else {
                toggleButton.classList.add('bg-yellow-600', 'text-white', 'hover:bg-yellow-700');
            }
        }

        function updateArticleRow(articleId, payload) {
            const row = document.querySelector(`tr[data-article-id="${articleId}"]`);
            if (!row) return;

            if (payload.views !== undefined) {
                const viewsEl = row.querySelector('.article-views');
                if (viewsEl) viewsEl.textContent = payload.views;
            }

            if (payload.helpful_count !== undefined) {
                const helpfulEl = row.querySelector('.article-helpful');
                if (helpfulEl) helpfulEl.textContent = payload.helpful_count;
            }

            if (payload.not_helpful_count !== undefined) {
                const notHelpfulEl = row.querySelector('.article-not-helpful');
                if (notHelpfulEl) notHelpfulEl.textContent = payload.not_helpful_count;
            }

            if (payload.publish_status !== undefined) {
                setStatusBadge(row, payload.publish_status);
                updateActionVisibility(row, payload.publish_status);
            }

            if (payload.is_hidden !== undefined) {
                setVisibilityBadge(row, payload.is_hidden);
                updateToggleButton(row, payload.is_hidden);
            }
        }

        async function sendAjaxForm(form) {
            const confirmText = form.dataset.confirm;
            if (confirmText && !confirm(confirmText)) {
                return null;
            }

            const url = form.action;
            const method = form.method.toUpperCase() || 'POST';
            const formData = new FormData(form);

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: formData,
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => null);
                    throw new Error(errorData?.message || 'Terjadi kesalahan jaringan.');
                }

                return await response.json();
            } catch (error) {
                showNotification(error.message || 'Gagal mengirim permintaan.', 'error');
                return null;
            }
        }

        function handleFormSubmit(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const articleId = form.dataset.articleId || currentArticleId;

            sendAjaxForm(form).then(result => {
                if (!result || !result.success) {
                    return;
                }

                showNotification(result.message, 'success');
                if (articleId && result.article) {
                    updateArticleRow(articleId, result.article);
                }

                if (form.id === 'rejectForm') {
                    closeRejectModal();
                }
            });
        }

        function openRejectModal(articleId) {
            currentArticleId = articleId;
            document.getElementById('rejectModal').classList.remove('hidden');
            document.getElementById('rejectForm').action = `/admin/articles/${articleId}/reject`;
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            currentArticleId = null;
            document.getElementById('rejectForm').reset();
        }

        document.querySelectorAll('.ajax-action-form').forEach((form) => {
            form.addEventListener('submit', handleFormSubmit);
        });

        document.getElementById('rejectForm')?.addEventListener('submit', handleFormSubmit);

        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</x-app-layout>
