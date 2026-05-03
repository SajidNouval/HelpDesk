<x-app-layout>
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Tinjau Artikel
            </h1>

            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Kelola Artikel</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">{{ Str::limit($article->title, 40) }}</span>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="lg:max-w-3xl">
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">{{ $article->title }}</h2>
                        <div class="flex flex-wrap gap-3 text-sm text-gray-600 mb-4">
                            <span>Kategori: <span class="font-semibold text-gray-900">{{ $article->category?->name ?? 'Tanpa kategori' }}</span></span>
                            <span>•</span>
                            <span>Penulis: <span class="font-semibold text-gray-900">{{ $article->staff?->name ?? 'Tidak ada' }}</span></span>
                            <span>•</span>
                            <span>Dibuat: <span class="font-semibold text-gray-900">{{ $article->created_at->format('d M Y') }}</span></span>
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $article->publish_status === 'approved' ? 'bg-green-100 text-green-800' : ($article->publish_status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ $article->publish_status === 'approved' ? 'Disetujui' : ($article->publish_status === 'pending' ? 'Menunggu Persetujuan' : 'Ditolak') }}
                            </span>
                            @if($article->is_hidden)
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Disembunyikan</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($article->publish_status !== 'approved')
                            <form method="POST" action="{{ route('admin.articles.approve', $article) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                    Setujui Artikel
                                </button>
                            </form>
                        @endif
                        <button type="button" onclick="openRejectModal({{ $article->id }})" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            Tolak Artikel
                        </button>
                        <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            Kembali
                        </a>
                    </div>
                </div>

                @if($article->rejection_note)
                    <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <h3 class="font-semibold text-red-900 mb-2">Catatan Penolakan</h3>
                        <p class="text-red-800">{{ $article->rejection_note }}</p>
                    </div>
                @endif

                <div class="mt-8 prose prose-lg max-w-none text-gray-700">
                    {!! $article->content !!}
                </div>

                <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-500">Views</p>
                        <p class="mt-2 text-xl font-semibold text-gray-900">{{ $article->views }}</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-500">Feedback Membantu</p>
                        <p class="mt-2 text-xl font-semibold text-green-700">{{ $article->helpful_count }}</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-500">Feedback Tidak Membantu</p>
                        <p class="mt-2 text-xl font-semibold text-red-700">{{ $article->not_helpful_count }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        function openRejectModal(articleId) {
            const modal = document.getElementById('rejectModal');
            const form = document.getElementById('rejectForm');
            form.action = `/admin/articles/${articleId}/reject`;
            modal.classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.getElementById('rejectForm').reset();
        }

        document.getElementById('rejectModal')?.addEventListener('click', function (e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</x-app-layout>
