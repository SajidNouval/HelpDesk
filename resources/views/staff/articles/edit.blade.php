<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Edit Artikel
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Perbarui konten dan informasi artikel.
            </p>

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

                <!-- Success/Error Alert -->
                @if (session('success'))
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded-lg flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">Form Edit Artikel</h2>
                    <p class="text-sm text-gray-500">Perbarui informasi artikel "{{ $article->title }}".</p>
                </div>

                <!-- Form Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm max-w-4xl">
                    <div class="p-6">

                        <form id="article-form" method="POST" action="{{ route('staff.articles.update', $article) }}" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div class="space-y-4">
                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
                                    <select name="category_id" id="category_id" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" required>
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
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Judul Artikel <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" id="title" value="{{ old('title', $article->title) }}" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" placeholder="Masukkan judul artikel" required>
                                    @error('title')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-2">Ringkasan</label>
                                    <textarea name="excerpt" id="excerpt" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none" placeholder="Ringkasan singkat artikel (opsional)">{{ old('excerpt', $article->excerpt) }}</textarea>
                                    @error('excerpt')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Konten Artikel <span class="text-red-500">*</span></label>
                                    <textarea name="content" id="content" rows="12" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" placeholder="Tulis konten artikel di sini...">{{ old('content', $article->content) }}</textarea>
                                    @error('content')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">Kata Kunci</label>
                                    <input type="text" name="keywords" id="keywords" value="{{ old('keywords', $article->keywords) }}" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" placeholder="Contoh: reset password, lupa password, login">
                                    @error('keywords')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="pt-6 border-t border-gray-200 flex justify-end gap-3">
                                <a href="{{ route('staff.articles.index') }}">
                                    <button type="button" class="h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition">
                                        Batal
                                    </button>
                                </a>
                                <button type="submit" class="h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                                    Update Artikel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- CKEditor 5 Script -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            ClassicEditor
                .create(document.querySelector('#content'), {
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', '|',
                        'bulletedList', 'numberedList', '|',
                        'blockQuote', 'link', '|',
                        'undo', 'redo'
                    ],
                    heading: {
                        options: [
                            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                        ]
                    },
                    contentStyles: `
                        .ck-editor__editable h1 {
                            font-size: 2.25rem;
                            font-weight: 700;
                            line-height: 2.5rem;
                            margin: 1.5rem 0 1rem 0;
                            color: #111827;
                        }
                        .ck-editor__editable h2 {
                            font-size: 1.875rem;
                            font-weight: 600;
                            line-height: 2.25rem;
                            margin: 1.25rem 0 0.75rem 0;
                            color: #111827;
                        }
                        .ck-editor__editable h3 {
                            font-size: 1.5rem;
                            font-weight: 600;
                            line-height: 2rem;
                            margin: 1rem 0 0.5rem 0;
                            color: #111827;
                        }
                        .ck-editor__editable ul {
                            list-style-type: disc;
                            padding-left: 2.5rem;
                            margin: 1rem 0;
                        }
                        .ck-editor__editable ol {
                            list-style-type: decimal;
                            padding-left: 2.5rem;
                            margin: 1rem 0;
                        }
                        .ck-editor__editable li {
                            display: list-item;
                            margin: 0.5rem 0;
                        }
                        .ck-editor__editable ul ul {
                            list-style-type: circle;
                            padding-left: 2rem;
                        }
                        .ck-editor__editable ul ul ul {
                            list-style-type: square;
                            padding-left: 2rem;
                        }
                        .ck-editor__editable ol ol {
                            padding-left: 2rem;
                        }
                    `
                })
                .then(editor => {
                    // Add validation on form submit
                    const form = document.getElementById('article-form');
                    form.addEventListener('submit', function(e) {
                        const content = editor.getData();
                        if (!content || content.trim() === '') {
                            e.preventDefault();
                            alert('Konten Artikel wajib diisi');
                        }
                    });
                })
                .catch(error => {
                    console.error(error);
                });
        });
    </script>

</x-app-layout>