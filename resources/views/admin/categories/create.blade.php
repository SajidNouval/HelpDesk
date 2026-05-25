<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Tambah Kategori
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.categories.index') }}" class="text-red-500 hover:text-red-600 font-medium">Kelola Kategori</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Tambah Kategori</span>
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
                        <h2 class="text-2xl font-bold text-gray-900">Tambah Kategori</h2>
                    </div>
                </div>

                @if($errors->any())
                    <div class="rounded-md bg-red-50 p-4 border border-red-200 mb-6">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-red-800">Ada kesalahan dalam input:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-6">
                        <form id="category-form" action="{{ route('admin.categories.store') }}" method="POST" class="space-y-6">
                            @csrf

                            <div class="space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nama Kategori <span class="text-red-500">*</span>
                                    </label>
                                    <input id="name" name="name" type="text" value="{{ old('name') }}" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                           placeholder="Masukkan nama kategori">
                                </div>

                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                        Deskripsi
                                    </label>
                                    <textarea id="description" name="description" rows="4"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-none"
                                              placeholder="Jelaskan kategori ini">{{ old('description') }}</textarea>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-gray-200">
                                <div class="flex space-x-3">
                                    <a href="{{ route('admin.categories.index') }}">
                                        <x-secondary-button>Batal</x-secondary-button>
                                    </a>
                                    <x-primary-button type="submit">
                                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Simpan Kategori
                                    </x-primary-button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
</x-app-layout>
