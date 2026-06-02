<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Detail Artikel
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('staff.articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Artikel</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">{{ Str::limit($article->title, 30) }}</span>
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
                        Menu Staf
                    </h3>

                    <ul class="space-y-3 text-gray-700">
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
                        <li>
                            <a href="{{ route('staff.articles.create') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Buat Artikel Baru
                            </a>
                        </li>
                    </ul>

                    <!-- Article Stats -->
                    <div class="mt-8 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-3">Statistik Artikel</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Views</span>
                                <span class="font-semibold text-gray-900">{{ $article->views }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bantu</span>
                                <span class="font-semibold text-green-600">{{ $article->helpful_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tidak Bantu</span>
                                <span class="font-semibold text-red-600">{{ $article->not_helpful_count }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Card -->
                    <div class="mt-4 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-2">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">

                <!-- Success/Error Alert -->
                @if (session('success'))
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded-lg flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                <!-- Article Header -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $article->title }}</h1>
                                <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-3">
                                    <span>Kategori: <span class="font-medium">{{ $article->category->name }}</span></span>
                                    <span>•</span>
                                    <span>Dibuat oleh: <span class="font-medium">{{ $article->staff->name }}</span></span>
                                    <span>•</span>
                                    <span>Dibuat: <span class="font-medium">{{ $article->created_at->format('d M Y') }}</span></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($article->publish_status === 'pending')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Menunggu Persetujuan
                                        </span>
                                    @elseif($article->publish_status === 'approved')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Disetujui & Dipublikasi
                                        </span>
                                    @elseif($article->publish_status === 'rejected')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Ditolak
                                        </span>
                                    @endif
                                    @if($article->excerpt)
                                        <span class="text-sm text-gray-600 italic">{{ $article->excerpt }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($article->publish_status === 'rejected' && $article->rejection_note)
                            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <h4 class="font-semibold text-red-900 mb-2">Alasan Penolakan</h4>
                                <p class="text-red-800">{{ $article->rejection_note }}</p>
                                <p class="text-sm text-red-700 mt-3">Anda dapat mengedit artikel ini dan mengirimkan kembali untuk disetujui.</p>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            @if($article->staff_id === auth()->id())
                                <a href="{{ route('staff.articles.edit', $article) }}">
                                    <x-primary-button class="inline-flex items-center px-4 py-2">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit Artikel
                                    </x-primary-button>
                                </a>
                                <form method="POST" action="{{ route('staff.articles.reset-views', $article) }}" class="inline" data-confirm="Reset jumlah view artikel?">
                                    @csrf
                                    <x-secondary-button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Reset View
                                    </x-secondary-button>
                                </form>
                                <form method="POST" action="{{ route('staff.articles.reset-feedback', $article) }}" class="inline" data-confirm="Reset semua review artikel?">
                                    @csrf
                                    <x-secondary-button type="submit" class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Reset Review
                                    </x-secondary-button>
                                </form>
                                <form id="delete-article-form-{{ $article->id }}" method="POST" action="{{ route('staff.articles.destroy', $article) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" data-delete-article="delete-article-form-{{ $article->id }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-red-600 border border-red-600 rounded-lg hover:bg-red-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Hapus
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('staff.articles.index') }}">
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
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Konten Artikel</h2>
                        <div class="prose prose-gray max-w-none">
                            {!! $article->content !!}
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <x-confirm-dialog 
        id="confirm-delete-article"
        title="Hapus Artikel"
        message="Artikel yang dihapus tidak dapat dikembalikan."
        primaryText="Hapus"
        secondaryText="Batal"
    />

    <script>
        // Handle delete button clicks
        document.querySelectorAll('[data-delete-article]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const formId = this.getAttribute('data-delete-article');
                window.confirmDialog.open('confirm-delete-article', {
                    onConfirm: function() {
                        document.getElementById(formId).submit();
                    }
                });
            });
        });
    </script>

    
</x-app-layout>