<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Kelola Artikel
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Kelola artikel bantuan untuk pelanggan.
            </p>

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
                        <li>
                            <a href="{{ route('staff.articles.create') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Buat Artikel Baru
                            </a>
                        </li>
                    </ul>

                    <!-- Profile Card -->
                    <div class="mt-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-2">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">

                <!-- Success Alert -->
                @if (session('success'))
                    <x-alert type="success" class="mb-6">
                        {{ session('success') }}
                    </x-alert>
                @endif

                <!-- Header with Add Button -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Daftar Artikel</h2>
                        <p class="text-sm text-gray-500">Artikel yang telah Anda buat.</p>
                    </div>
                    <div class="flex items-center">
                        <a href="{{ route('staff.articles.create') }}" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Buat Artikel Baru
                        </a>
                    </div>
                </div>

                <!-- Articles List -->
                @if($articles->count() > 0)
                    <div class="space-y-3">
                        @foreach($articles as $article)
                            <div class="bg-white border border-gray-100 rounded-xl shadow-sm hover:shadow-md transition">
                                <div class="p-5">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h3 class="text-base font-semibold text-gray-900 mb-1">
                                                <a href="{{ route('staff.articles.show', $article) }}" class="hover:text-red-600 transition">
                                                    {{ $article->title }}
                                                </a>
                                            </h3>
                                            <p class="text-xs text-gray-600 mb-2">Pembuat: {{ $article->staff->name }} • {{ $article->category->name }}</p>
                                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                                <span>Views: {{ $article->views }}</span>
                                                <span>Bantu: {{ $article->helpful_count }}</span>
                                                <span>Tidak Bantu: {{ $article->not_helpful_count }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($article->publish_status === 'pending')
                                                <span class="status-badge status-pending">
                                                    Menunggu
                                                </span>
                                            @elseif($article->publish_status === 'approved')
                                                <span class="status-badge status-approved">
                                                    Disetujui
                                                </span>
                                            @elseif($article->publish_status === 'rejected')
                                                <span class="status-badge status-rejected">
                                                    Ditolak
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex gap-3 pt-3 border-t border-gray-200">
                                        <a href="{{ route('staff.articles.show', $article) }}">
                                            <x-secondary-button class="px-3 py-1.5 text-xs">Lihat Detail</x-secondary-button>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    @if($articles->hasPages())
                        <div class="mt-6">
                            <x-pagination :paginator="$articles" :elements="$articles->links()->elements" />
                        </div>
                    @endif
                @else
                    <x-empty-state 
                        icon="document"
                        title="Belum Ada Artikel"
                        subtitle="Mulai buat artikel bantuan pertama Anda untuk membantu pelanggan."
                        actionText="Buat Artikel Baru"
                        actionUrl="{{ route('staff.articles.create') }}"
                        actionIcon="plus"
                        size="md"
                    />
                @endif

            </div>
        </div>
    </div>

</x-app-layout>