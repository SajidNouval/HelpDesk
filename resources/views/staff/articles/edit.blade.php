<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Edit Artikel
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('staff.articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Artikel</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Edit</span>
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
                            <a href="{{ route('staff.articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
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

                <!-- Form Card -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Edit Artikel</h2>
                            <p class="text-gray-600 mt-1">Perbarui informasi artikel "{{ $article->title }}"</p>
                        </div>

                        <form id="article-form" method="POST" action="{{ route('staff.articles.update', $article) }}" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div class="space-y-4">
                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                                    <select name="category_id" id="category_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                                        <option value="">Pilih Kategori</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ $article->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Judul Artikel</label>
                                    <input type="text" name="title" id="title" value="{{ old('title', $article->title) }}" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Masukkan judul artikel" required>
                                    @error('title')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-2">Ringkasan</label>
                                    <textarea name="excerpt" id="excerpt" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-none" placeholder="Ringkasan singkat artikel (opsional)">{{ old('excerpt', $article->excerpt) }}</textarea>
                                    @error('excerpt')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Konten Artikel</label>
                                    <textarea name="content" id="content" rows="12" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Tulis konten artikel di sini..." required>{{ old('content', $article->content) }}</textarea>
                                    @error('content')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">Kata Kunci</label>
                                    <input type="text" name="keywords" id="keywords" value="{{ old('keywords', $article->keywords) }}" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Contoh: reset password, lupa password, login">
                                    @error('keywords')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published', $article->is_published) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_published" class="ml-3 block text-sm font-semibold text-gray-900">
                                    Publikasikan artikel ini
                                </label>
                            </div>

                            <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                                <a href="{{ route('staff.articles.index') }}">
                                    <x-secondary-button class="px-6 py-3">Batal</x-secondary-button>
                                </a>
                                <x-primary-button type="submit" class="px-6 py-3">Update Artikel</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    
</x-app-layout>