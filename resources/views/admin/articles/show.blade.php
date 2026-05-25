<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Tinjau Artikel
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Kelola Artikel</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">{{ Str::limit($article->title, 40) }}</span>
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

                    <!-- Article Info Card -->
                    <div class="mt-8 bg-white rounded-lg border border-gray-200 p-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Info Artikel</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Views:</span>
                                <span class="font-semibold text-blue-600">{{ $article->views }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Membantu:</span>
                                <span class="font-semibold text-green-600">{{ $article->helpful_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tidak Membantu:</span>
                                <span class="font-semibold text-red-600">{{ $article->not_helpful_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Dibuat:</span>
                                <span class="font-semibold">{{ $article->created_at->format('d M Y') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-span-12 md:col-span-9">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                    <!-- Article Header -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                            <div class="lg:max-w-3xl">
                                <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $article->title }}</h2>
                                <div class="flex flex-wrap gap-3 text-sm text-gray-600 mb-4">
                                    <span>Kategori: <span class="font-semibold text-gray-900">{{ $article->category?->name ?? 'Tanpa kategori' }}</span></span>
                                    <span>•</span>
                                    <span>Penulis: <span class="font-semibold text-gray-900">{{ $article->staff?->name ?? 'Tidak ada' }}</span></span>
                                </div>
                                <div class="flex flex-wrap gap-2 items-center">
                                    @if($article->publish_status === 'approved')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Disetujui</span>
                                    @elseif($article->publish_status === 'pending')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Menunggu Persetujuan</span>
                                    @elseif($article->publish_status === 'rejected')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Ditolak</span>
                                    @endif
                                    @if($article->is_hidden)
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Disembunyikan</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 mt-4 lg:mt-0">
                                @if($article->publish_status !== 'approved')
                                    <form id="approveForm" method="POST" action="{{ route('admin.articles.approve', $article) }}">
                                        @csrf
                                        <x-primary-button type="submit" class="inline-flex items-center px-4 py-2">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Setujui Artikel
                                        </x-primary-button>
                                    </form>
                                    <x-danger-button type="button" data-open-modal="#rejectModal" data-modal-form-action="/admin/articles/{id}/reject" data-article-id="{{ $article->id }}" class="inline-flex items-center px-4 py-2">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Tolak Artikel
                                    </x-danger-button>
                                @endif
                                <a href="{{ route('admin.articles.index') }}">
                                    <x-secondary-button class="inline-flex items-center px-4 py-2">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                        </svg>
                                        Kembali
                                    </x-secondary-button>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Article Content -->
                    <div class="p-6">
                        @if($article->rejection_note)
                            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-red-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <h3 class="font-semibold text-red-900 mb-1">Catatan Penolakan</h3>
                                        <p class="text-red-800">{{ $article->rejection_note }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="prose prose-lg max-w-none text-gray-700">
                            {!! $article->content !!}
                        </div>

                        <!-- Article Statistics -->
                        <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-blue-600 font-medium">Views</p>
                                        <p class="text-xl font-bold text-blue-900">{{ $article->views }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-green-600 font-medium">Feedback Membantu</p>
                                        <p class="text-xl font-bold text-green-900">{{ $article->helpful_count }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.737 3h4.017c.163 0 .326.02.485.06L17 4m-7 10v2a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-red-600 font-medium">Feedback Tidak Membantu</p>
                                        <p class="text-xl font-bold text-red-900">{{ $article->not_helpful_count }}</p>
                                    </div>
                                </div>
                            </div>
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
                    <textarea name="rejection_note" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" rows="4" placeholder="Jelaskan alasan penolakan..." required></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <x-secondary-button type="button" data-close-modal class="px-4 py-2 text-sm rounded-xl">Batal</x-secondary-button>
                    <x-danger-button type="submit" class="px-4 py-2 text-sm rounded-xl">Tolak</x-danger-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>