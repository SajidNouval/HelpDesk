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
                            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-green-800 font-medium">{{ session('success') }}</span>
                                </div>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-red-800 font-medium">{{ session('error') }}</span>
                                </div>
                            </div>
                        @endif

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
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                                            <span class="text-orange-600 font-semibold text-sm">{{ substr($article->title, 0, 1) }}</span>
                                                        </div>
                                                        <div>
                                                            <div class="text-gray-900 font-medium">{{ Str::limit($article->title, 40) }}</div>
                                                            <div class="text-xs text-gray-500">{{ $article->category?->name ?? 'Tanpa kategori' }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-2">
                                                            <span class="text-red-600 font-semibold text-xs">{{ substr($article->staff?->name ?? 'N', 0, 1) }}</span>
                                                        </div>
                                                        <span class="text-gray-600">{{ $article->staff?->name ?? 'Tidak ada' }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">{{ $article->views }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <div class="flex flex-col gap-1">
                                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">{{ $article->helpful_count }}</span>
                                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">{{ $article->not_helpful_count }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    @if($article->publish_status === 'pending')
                                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">Menunggu</span>
                                                    @elseif($article->publish_status === 'approved')
                                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Disetujui</span>
                                                    @elseif($article->publish_status === 'rejected')
                                                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Ditolak</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    @if($article->is_hidden)
                                                        <span class="px-2 py-1 bg-gray-200 text-gray-800 rounded text-xs font-semibold">Disembunyikan</span>
                                                    @else
                                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-semibold">Publik</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <div class="flex flex-col gap-2">
                                                        @if($article->publish_status === 'pending')
                                                            <div class="flex items-center justify-center gap-2">
                                                                <a href="{{ route('admin.articles.show', $article) }}" class="text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                                                    Lihat
                                                                </a>
                                                                <form method="POST" action="{{ route('admin.articles.approve', $article) }}" style="display: inline;">
                                                                    @csrf
                                                                    <button type="submit" class="text-xs px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                                                        Setujui
                                                                    </button>
                                                                </form>
                                                                <button type="button" onclick="openRejectModal({{ $article->id }})" class="text-xs px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition">
                                                                    Tolak
                                                                </button>
                                                            </div>
                                                        @elseif($article->publish_status === 'rejected')
                                                            <div class="flex items-center justify-center gap-2">
                                                                <a href="{{ route('admin.articles.show', $article) }}" class="text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                                                    Lihat
                                                                </a>
                                                                <form method="POST" action="{{ route('admin.articles.approve', $article) }}" style="display: inline;">
                                                                    @csrf
                                                                    <button type="submit" class="text-xs px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                                                        Setujui
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @else
                                                            <div class="flex items-center justify-center gap-2">
                                                                <a href="{{ route('admin.articles.show', $article) }}" class="text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                                                    Lihat
                                                                </a>
                                                                <form method="POST" action="{{ route('admin.articles.toggle-hide', $article) }}" style="display: inline;">
                                                                    @csrf
                                                                    <button type="submit" class="text-xs px-2 py-1 rounded transition @if($article->is_hidden) bg-blue-600 text-white hover:bg-blue-700 @else bg-yellow-600 text-white hover:bg-yellow-700 @endif">
                                                                        @if($article->is_hidden)
                                                                            Tampilkan
                                                                        @else
                                                                            Sembunyikan
                                                                        @endif
                                                                    </button>
                                                                </form>

                                                                <form method="POST" action="{{ route('admin.articles.reset-views', $article) }}" style="display: inline;">
                                                                    @csrf
                                                                    <button type="submit" class="text-xs px-2 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 transition" title="Reset Views">
                                                                        Reset View
                                                                    </button>
                                                                </form>

                                                                <form method="POST" action="{{ route('admin.articles.reset-feedback', $article) }}" style="display: inline;" onclick="return confirm('Hapus semua feedback artikel ini?');">
                                                                    @csrf
                                                                    <button type="submit" class="text-xs px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition" title="Reset Feedback">
                                                                        Reset Feedback
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endif
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
        let currentArticleId = null;

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

        // Close modal when clicking outside
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</x-app-layout>
