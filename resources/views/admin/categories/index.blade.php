<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Kelola Kategori
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Manajemen kategori untuk artikel Knowledge Base.
            </p>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Kelola Kategori</span>
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
                            <a href="{{ route('admin.categories.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Kategori
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>

                    
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Daftar Kategori</h2>
                        <p class="text-sm text-gray-500">Kelola kategori Knowledge Base.</p>
                    </div>
                    <div class="flex items-center">
                        
                        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Tambah Kategori
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <x-alert type="success" class="mb-6">
                        {{ session('success') }}
                    </x-alert>
                @endif

                <!-- Statistics -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white border border-gray-100 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <div class="text-sm text-gray-500">Total Kategori</div>
                            <div class="text-xl font-semibold text-gray-900">{{ $totalCategories ?? $categories->total() ?? $categories->count() }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-4 overflow-x-auto">
                        @if($categories->count())
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kategori</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Artikel</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terakhir Diperbarui</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($categories as $category)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <x-truncate-text :value="$category->name" class="text-sm font-medium text-gray-900 block" />
                                                @if($category->slug)
                                                    <div class="text-xs text-gray-400">/{{ $category->slug }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                                {{ $category->articles_count ?? $category->articles()->count() }}
                                            </td>
                                            <td class="px-4 py-3 max-w-sm text-sm text-gray-600">
                                                <x-truncate-text :value="$category->description" class="text-sm text-gray-600 block" />
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                @if($category->updated_at)
                                                    <div>{{ $category->updated_at->format('d M Y') }}</div>
                                                    <div class="text-xs text-gray-400">{{ $category->updated_at->diffForHumans() }}</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('admin.categories.edit', $category) }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-red-500 hover:text-red-600 mr-2" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>
                                                <form id="delete-form-{{ $category->id }}" action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" data-delete-category="delete-form-{{ $category->id }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-red-600 text-red-600 hover:bg-red-50" title="Hapus">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-6">
                                <x-empty-state 
                                    icon="folder"
                                    title="Belum Ada Kategori"
                                    subtitle="Tambahkan kategori pertama untuk mulai mengelola konten."
                                    actionText="Tambah Kategori Pertama"
                                    actionUrl="{{ route('admin.categories.create') }}"
                                    actionIcon="plus"
                                    size="md"
                                />
                            </div>
                        @endif
                    </div>

                    @if($categories->hasPages())
                        <div class="p-4 border-t border-gray-100">
                            <x-pagination :paginator="$categories" :elements="$categories->links()->elements" />
                        </div>
                    @endif
                </div>

                <!-- Delete Confirmation Dialog -->
                <x-confirm-dialog 
                    id="confirm-delete-category"
                    title="Hapus Kategori"
                    message="Kategori yang dihapus tidak dapat dikembalikan."
                    primaryText="Hapus"
                    secondaryText="Batal"
                />

                <script>
                    // Handle delete button clicks
                    document.querySelectorAll('[data-delete-category]').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const formId = this.getAttribute('data-delete-category');
                            window.confirmDialog.open('confirm-delete-category', {
                                onConfirm: function() {
                                    document.getElementById(formId).submit();
                                }
                            });
                        });
                    });
                </script>
            </div>
        </div>
    </div>

    
</x-app-layout>
