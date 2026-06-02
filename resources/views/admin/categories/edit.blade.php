<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Edit Kategori
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.categories.index') }}" class="text-red-500 hover:text-red-600 font-medium">Kelola Kategori</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Edit Kategori</span>
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
                <div class="mb-6">
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 text-red-600">
                            <div class="flex items-center justify-center w-11 h-11 rounded-2xl bg-red-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Edit Kategori</h2>
                                <p class="text-sm text-gray-500">Perbarui informasi kategori {{ $category->name }}.</p>
                            </div>
                        </div>
                    </div>
                </div>

                @if($errors->any())
                    <div class="rounded-2xl bg-red-50 p-4 border border-red-200 mb-6">
                        <div class="flex gap-3">
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-red-800">Ada kesalahan dalam input:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm max-w-2xl">
                    <div class="p-6">
                        <form id="category-form" action="{{ route('admin.categories.update', $category) }}" method="POST" class="space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nama Kategori <span class="text-red-500">*</span></label>
                                <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-2xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                       placeholder="Masukkan nama kategori">
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                                <textarea id="description" name="description" rows="5"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-2xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none"
                                          placeholder="Jelaskan kategori ini">{{ old('description', $category->description) }}</textarea>
                            </div>

                            <div class="pt-4 border-t border-gray-200 flex flex-col sm:flex-row sm:justify-end gap-3">
                                <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:border-red-500 hover:text-red-600 transition">
                                    Batal
                                </a>
                                <button type="button" id="btn-save-category" class="inline-flex items-center justify-center rounded-2xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Confirmation Dialog -->
                <x-confirm-dialog 
                    id="confirm-edit-category"
                    title="Simpan Perubahan"
                    message="Apakah Anda yakin ingin menyimpan perubahan kategori ini?"
                    primaryText="Simpan"
                    secondaryText="Batal"
                />

                <script>
                    document.getElementById('btn-save-category').addEventListener('click', function(e) {
                        e.preventDefault();
                        window.confirmDialog.open('confirm-edit-category', {
                            onConfirm: function() {
                                document.getElementById('category-form').submit();
                            }
                        });
                    });
                </script>
            </div>
        </div>
    </div>

    
</x-app-layout>
