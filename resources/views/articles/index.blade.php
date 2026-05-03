@extends('layouts.app')

@section('title', 'Artikel Bantuan')

@section('content')

<!-- Header Section -->
<div class="bg-gray-100 py-10 border-b">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
            Artikel Bantuan
        </h1>

        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mt-2 flex items-center">
            <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
            <span class="mx-2 text-gray-400">/</span>
            <span class="text-gray-700">Artikel Bantuan</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 py-10">
    <div class="grid grid-cols-12 gap-8">

        <!-- Sidebar -->
        <div class="col-span-12 md:col-span-3">
            <div class="border-r border-gray-200 pr-4">
                <h3 class="text-sm uppercase text-gray-400 mb-4 font-medium tracking-wider">
                    Kategori Artikel
                </h3>

                <ul class="space-y-3 text-gray-700">
                    <li>
                        <a href="{{ route('articles.index') }}" class="block rounded-l-md px-3 py-2 transition {{ empty($selectedCategoryId) ? 'text-red-500 font-semibold border-l-4 border-red-500 bg-red-50' : 'hover:text-red-500' }}">
                            Semua Artikel
                        </a>
                    </li>
                    @foreach($categories as $category)
                        <li>
                            <a href="{{ route('articles.index', ['category' => $category->id]) }}" class="block rounded-l-md px-3 py-2 transition {{ $selectedCategoryId == $category->id ? 'text-red-500 font-semibold border-l-4 border-red-500 bg-red-50' : 'hover:text-red-500' }}">
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                

                <!-- Contact Info -->
                <div class="mt-8 p-4 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-700 mb-2">Butuh Bantuan?</h4>
                    <p class="text-sm text-gray-600 leading-relaxed mb-3">
                        Tidak menemukan artikel yang dicari?
                    </p>
                    <button onclick="openReportModal()" class="w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        Buat Laporan
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="col-span-12 md:col-span-9">

            @php
                $selectedCategory = $categories->firstWhere('id', $selectedCategoryId);
            @endphp

            <div class="mb-6">
                <p class="text-gray-600">
                    Menampilkan {{ $articles->count() }} dari {{ $articles->total() }} artikel
                    @if($selectedCategory)
                        untuk kategori <span class="font-medium">{{ $selectedCategory->name }}</span>
                    @endif
                </p>
            </div>

            @if($articles->count() > 0)
                <div class="space-y-6">
                    @foreach($articles as $article)
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden">
                            <div class="p-6">
                                <!-- Article Header -->
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 hover:text-red-600 transition">
                                            <a href="{{ route('articles.show', ['slug' => $article->slug] + ($selectedCategoryId ? ['category' => $selectedCategoryId] : [])) }}" class="block">
                                                {{ $article->title }}
                                            </a>
                                        </h3>
                                        <div class="flex items-center gap-4 mt-2 text-sm text-gray-600">
                                            <span>Kategori: <span class="font-medium">{{ $article->category?->name ?? 'Umum' }}</span></span>
                                            <span>•</span>
                                            <span>Dibuat oleh: <span class="font-medium">{{ $article->staff?->name ?? 'Admin' }}</span></span>
                                            <span>•</span>
                                            <span>{{ $article->views }} dilihat</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Article Excerpt -->
                                <div class="text-gray-700 leading-relaxed mb-4">
                                    {!! Str::limit(strip_tags($article->content), 200) !!}
                                </div>

                                <!-- Read More -->
                                <div class="flex items-center justify-between">
                                    <a href="{{ route('articles.show', ['slug' => $article->slug] + ($selectedCategoryId ? ['category' => $selectedCategoryId] : [])) }}" class="text-red-500 hover:text-red-600 font-medium transition">
                                        Baca selengkapnya →
                                    </a>
                                    <div class="text-sm text-gray-500">
                                        Tanggal Pembuatan: {{ $article->created_at->format('d M Y') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8 flex justify-center">
                    {{ $articles->links() }}
                </div>
            @else
                <div class="text-center py-16">
                    <div class="inline-block">
                        <svg class="w-16 h-16 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500 dark:text-gray-400 font-medium mb-2">Belum ada artikel</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Artikel bantuan akan segera ditambahkan</p>
                    </div>
                </div>
            @endif

        </div>

    </div>
</div>

@endsection

@include('components.articles-chat-bubble', ['categories' => $categories])
@include('reports.create')

<style>
    @keyframes fadeInSlide {
        from {
            opacity: 0;
            transform: translateX(400px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes fadeOutSlide {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(400px);
        }
    }

    .animate-fade-in {
        animation: fadeInSlide 0.3s ease-out;
    }

    .animate-fade-out {
        animation: fadeOutSlide 0.3s ease-out forwards;
    }
</style>

<script>
function openReportModal() {
    document.getElementById('reportModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('reportForm').reset();
}

function showSuccessToast(message = 'Laporan berhasil dibuat!') {
    const toast = document.getElementById('successToast');
    const toastMessage = document.getElementById('toastMessage');
    
    toastMessage.textContent = message;
    toast.classList.remove('hidden', 'animate-fade-out');
    toast.classList.add('animate-fade-in');
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        closeSuccessToast();
    }, 5000);
}

function closeSuccessToast() {
    const toast = document.getElementById('successToast');
    toast.classList.remove('animate-fade-in');
    toast.classList.add('animate-fade-out');
    
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 300);
}

// Close modal ketika klik di luar
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reportModal');
    const reportForm = document.getElementById('reportForm');
    
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeReportModal();
            }
        });

        // Show modal if form has errors
        @if ($errors->any())
            openReportModal();
        @endif
    }

    // Handle form submission
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            // Let the form submit normally, but show success after redirect
            @if(session('success'))
                showSuccessToast("{{ session('success') }}");
                // Close modal after showing success
                setTimeout(() => {
                    closeReportModal();
                }, 300);
            @endif
        });
    }

    // Show success notification if coming from redirect
    @if(session('success'))
        setTimeout(() => {
            showSuccessToast("{{ session('success') }}");
            closeReportModal();
        }, 100);
    @endif
});
</script>