<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-8 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-2xl font-bold text-gray-900">
                Kelola Kategori
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Mengelola daftar kategori artikel
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
    <div class="max-w-7xl mx-auto px-4 py-8">
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
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Daftar Kategori</h2>
                    </div>
                    <a href="{{ route('admin.categories.create') }}">
                        <x-primary-button class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Tambah Kategori</span>
                        </x-primary-button>
                    </a>
                </div>

                @if(session('success'))
                    <x-alert type="success" class="mb-6">
                        {{ session('success') }}
                    </x-alert>
                @endif

                <div class="panel-card">
                    <div class="p-6">
                        @if($categories->count())
                            <div class="table-wrapper overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="table-header text-left">Nama</th>
                                            <th class="table-header text-left">Deskripsi</th>
                                            <th class="table-header text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        @foreach($categories as $category)
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="table-cell whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center mr-3">
                                                            <span class="text-orange-600 font-semibold text-sm">{{ strtoupper(substr($category->name, 0, 1)) }}</span>
                                                        </div>
                                                        <span class="text-gray-900 font-medium">{{ $category->name }}</span>
                                                    </div>
                                                </td>
                                                <td class="table-cell">
                                                    <span class="text-gray-600 text-sm">{{ Str::limit($category->description, 50) }}</span>
                                                </td>
                                                <td class="table-cell text-right">
                                                    <div class="action-group">
                                                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn-action-sm btn-action-secondary">
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                            Edit
                                                        </a>
                                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline-block">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn-action-sm btn-action-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                </svg>
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-empty-state 
                                icon="folder"
                                title="Belum Ada Kategori"
                                subtitle="Tambahkan kategori pertama untuk mulai mengelola konten."
                                actionText="Tambah Kategori Pertama"
                                actionUrl="{{ route('admin.categories.create') }}"
                                actionIcon="plus"
                                size="md"
                            />
                        @endif

                        @if($categories->hasPages())
                            <div class="mt-6">
                                <x-pagination :paginator="$categories" :elements="$categories->links()->elements" />
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    
</x-app-layout>
