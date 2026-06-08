<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
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

                <!-- Form Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm max-w-4xl">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Form Tambah Kategori</h2>
                                <p class="text-sm text-gray-500">Tambah kategori baru.</p>
                            </div>
                        </div>

                        <form id="category-form" action="{{ route('admin.categories.store') }}" method="POST" class="space-y-5">
                            @csrf

                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nama Kategori <span class="text-red-500">*</span></label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required
                                       class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                       placeholder="Masukkan nama kategori">
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                                <textarea id="description" name="description" rows="5"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none"
                                          placeholder="Jelaskan kategori ini">{{ old('description') }}</textarea>
                            </div>

                            <div class="pt-4 border-t border-gray-200 flex justify-end gap-3">
                                <a href="{{ route('admin.categories.index') }}">
                                    <button type="button" class="h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition">
                                        Batal
                                    </button>
                                </a>
                                <button type="button" id="btn-save-category" class="h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                                    Simpan Kategori
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Confirmation Dialog -->
                <x-confirm-dialog 
                    id="confirm-add-category"
                    title="Tambah Kategori"
                    message="Apakah Anda yakin ingin menambahkan kategori ini?"
                    primaryText="Tambah"
                    secondaryText="Batal"
                />

                <script>
                    document.getElementById('btn-save-category').addEventListener('click', function(e) {
                        e.preventDefault();
                        window.confirmDialog.open('confirm-add-category', {
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
