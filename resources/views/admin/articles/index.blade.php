<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-8 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-2xl font-bold text-gray-900">
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
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-12 gap-8">

            <!-- Sidebar Left -->
            <div class="col-span-12 md:col-span-3">
                <div class="border-r border-gray-200 pr-4">
                    <h3 class="text-sm uppercase text-gray-400 mb-4 font-medium tracking-wider">
                        Menu Admin
                    </h3>

                    <ul class="space-y-2 text-gray-700">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="block rounded-l-md px-3 py-2 text-sm transition hover:text-red-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.users.index') }}" class="block rounded-l-md px-3 py-2 text-sm transition hover:text-red-500">
                                Kelola Staf
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.categories.index') }}" class="block rounded-l-md px-3 py-2 text-sm transition hover:text-red-500">
                                Kelola Kategori
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.articles.index') }}" class="block rounded-l-md px-3 py-2 text-sm transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-span-12 md:col-span-9">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Daftar Artikel</h2>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        @if (session('success'))
                            <x-alert type="success" class="mb-6">
                                {{ session('success') }}
                            </x-alert>
                        @endif

                        @if (session('error'))
                            <x-alert type="error" class="mb-6">
                                {{ session('error') }}
                            </x-alert>
                        @endif

                        <div id="ajaxNotification" class="fixed top-6 right-6 z-50 space-y-3"></div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="table-header text-left">Judul</th>
                                        <th class="table-header text-left">Penulis</th>
                                        <th class="table-header text-center">Views</th>
                                        <th class="table-header text-center">Status</th>
                                        <th class="table-header text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @forelse($articles as $article)
                                        <tr data-article-id="{{ $article->id }}" class="hover:bg-gray-50 transition">
                                            <td class="table-cell">
                                                <div class="text-gray-900 font-medium">
                                                    {{ Str::limit($article->title, 40) }}
                                                </div>
                                            </td>

                                            <td class="table-cell">
                                                <span class="text-gray-600 text-sm">
                                                    {{ $article->staff?->name ?? 'Tidak ada' }}
                                                </span>
                                            </td>

                                            <td class="table-cell text-center">
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">
                                                    {{ $article->views }}
                                                </span>
                                            </td>

                                            <td class="table-cell text-center">
                                                @if($article->publish_status === 'pending')
                                                    <span class="status-badge status-pending">
                                                        Menunggu
                                                    </span>
                                                @elseif($article->publish_status === 'approved')
                                                    <span class="status-badge status-approved">
                                                        Disetujui
                                                    </span>
                                                @else
                                                    <span class="status-badge status-rejected">
                                                        Ditolak
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="table-cell text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="{{ route('admin.articles.show', $article) }}">
                                                        <x-secondary-button class="text-xs px-3 py-1.5">
                                                            Lihat
                                                        </x-secondary-button>
                                                    </a>

                                                    @if($article->publish_status === 'pending' || $article->publish_status === 'rejected')
                                                        <form action="{{ route('admin.articles.approve', $article) }}" method="POST" class="inline">
                                                            @csrf
                                                            <x-primary-button type="submit" class="text-xs px-3 py-1.5" data-ajax data-confirm="Apakah Anda yakin ingin menyetujui artikel ini?">
                                                                Setujui
                                                            </x-primary-button>
                                                        </form>
                                                    @endif

                                                    @if($article->publish_status === 'pending' || $article->publish_status === 'approved')
                                                        <button type="button" onclick="openRejectModal('{{ route('admin.articles.reject', $article) }}')" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition">
                                                            Tolak
                                                        </button>
                                                    @endif

                                                    <form action="{{ route('admin.articles.toggle-hide', $article) }}" method="POST" class="inline">
                                                        @csrf
                                                        <x-secondary-button type="submit" class="text-xs px-3 py-1.5 {{ $article->is_hidden ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 'bg-gray-50 text-gray-600 border-gray-200' }}" data-ajax>
                                                            {{ $article->is_hidden ? 'Tampilkan' : 'Sembunyikan' }}
                                                        </x-secondary-button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">
                                                <x-empty-state 
                                                    icon="document"
                                                    title="Belum ada artikel"
                                                    subtitle="Belum ada artikel yang dibuat oleh staf."
                                                    size="md"
                                                />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($articles->hasPages())
                            <div class="mt-6">
                                <x-pagination :paginator="$articles" :elements="$articles->links()->elements" />
                            </div>
                        @endif
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
                    <textarea name="rejection_note" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none" rows="4" placeholder="Jelaskan alasan penolakan..." required></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <x-secondary-button type="button" data-close-modal class="px-4 py-2 text-sm rounded-xl">Batal</x-secondary-button>
                    <x-danger-button type="submit" class="px-4 py-2 text-sm rounded-xl">Tolak</x-danger-button>
                </div>
            </form>
        </div>
    </div>

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
    </script>
</x-app-layout>
