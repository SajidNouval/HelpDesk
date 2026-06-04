<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Tinjau Artikel
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Lihat detail dan tinjau artikel bantuan untuk dipublikasikan.
            </p>

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

                <!-- Article Header Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-4">
                    <div class="p-6">

                        <!-- Title & Meta -->
                        <div class="mb-3">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-2">{{ $article->title }}</h2>
                            <div class="flex flex-wrap gap-3 text-sm text-gray-600">
                                <span>Kategori: <span class="font-medium text-gray-900">{{ $article->category?->name ?? 'Tanpa kategori' }}</span></span>
                                <span class="text-gray-300">|</span>
                                <span>Penulis: <span class="font-medium text-gray-900">{{ $article->staff?->name ?? 'Tidak ada' }}</span></span>
                                <span class="text-gray-300">|</span>
                                <span>Dibuat: <span class="font-medium text-gray-900">{{ $article->created_at->format('d M Y') }}</span></span>
                            </div>
                        </div>

                        <!-- Status Badge + Excerpt/Visibility -->
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            @if($article->publish_status === 'approved')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Disetujui</span>
                            @elseif($article->publish_status === 'pending')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Menunggu Persetujuan</span>
                            @elseif($article->publish_status === 'rejected')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Ditolak</span>
                            @endif
                            @if($article->is_hidden)
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Disembunyikan dari Publik</span>
                            @endif
                        </div>

                        <!-- Statistics Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 text-center">
                                <div class="text-sm text-gray-500 mb-1">Views</div>
                                <div class="text-2xl font-semibold text-gray-900">{{ $article->views }}</div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 text-center">
                                <div class="text-sm text-gray-500 mb-1">Feedback Membantu</div>
                                <div class="text-2xl font-semibold text-green-600">{{ $article->helpful_count }}</div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 text-center">
                                <div class="text-sm text-gray-500 mb-1">Feedback Tidak Membantu</div>
                                <div class="text-2xl font-semibold text-red-600">{{ $article->not_helpful_count }}</div>
                            </div>
                        </div>

                        @if($article->rejection_note)
                            <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
                                <svg class="w-5 h-5 text-red-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-red-900 mb-1">Catatan Penolakan</h4>
                                    <p class="text-red-800">{{ $article->rejection_note }}</p>
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="pt-4 border-t border-gray-200 flex flex-wrap items-center gap-2">
                            @if($article->publish_status !== 'approved')
                                <form id="approveForm" method="POST" action="{{ route('admin.articles.approve', $article) }}" data-confirm="Setujui artikel ini?">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Setujui Artikel
                                    </button>
                                </form>
                                <button type="button" data-open-modal="#rejectModal" data-modal-form-action="/admin/articles/{id}/reject" data-article-id="{{ $article->id }}"
                                        class="inline-flex items-center h-10 px-4 rounded-xl border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Tolak Artikel
                                </button>
                            @endif
                            <a href="{{ route('admin.articles.index') }}"
                               class="inline-flex items-center h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Article Content Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Konten Artikel</h3>
                        <div class="prose prose-gray max-w-none">
                            {!! $article->content !!}
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
</x-app-layout>