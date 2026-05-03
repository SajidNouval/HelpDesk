<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
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

                    <!-- Profile Card -->
                    <div class="mt-8 p-4 bg-gray-50 rounded">
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

                <!-- Header with Add Button -->
                <div class="mb-6 flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Daftar Artikel</h2>
                    </div>
                </div>

                <!-- Articles List -->
                @if($articles->count() > 0)
                    <div class="space-y-4">
                        @foreach($articles as $article)
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                                <a href="{{ route('staff.articles.show', $article) }}" class="hover:text-blue-600 transition">
                                                    {{ $article->title }}
                                                </a>
                                            </h3>
                                            <p class="text-sm text-gray-600 mb-2">Pembuat: {{ $article->staff->name }} • {{ $article->category->name }}</p>
                                            <div class="flex items-center gap-4 text-sm text-gray-500">
                                                <span>Views: {{ $article->views }}</span>
                                                <span>Bantu: {{ $article->helpful_count }}</span>
                                                <span>Tidak Bantu: {{ $article->not_helpful_count }}</span>
                                            </div>
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
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <a href="{{ route('staff.articles.show', $article) }}" class="text-blue-600 hover:text-blue-800 font-medium transition">
                                            Lihat Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8">
                        {{ $articles->links() }}
                    </div>
                @else
                    <div class="text-center py-16 bg-white border border-gray-200 rounded-lg">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Belum Ada Artikel</h3>
                        <p class="text-gray-600 mb-4">
                            Mulai buat artikel bantuan pertama Anda untuk membantu pelanggan.
                        </p>
                        <a href="{{ route('staff.articles.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                            Buat Artikel Baru
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </div>

</x-app-layout>