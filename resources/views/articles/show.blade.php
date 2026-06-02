@extends('layouts.app')

@section('title', $article->title . ' - Artikel Bantuan')

@section('content')

<!-- Header Section -->
<div class="bg-gray-100 py-10 border-b">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
            {{ $article->title }}
        </h1>

        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mt-2 flex items-center">
            <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
            <span class="mx-2 text-gray-400">/</span>
            <a href="{{ route('articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Artikel Bantuan</a>
            <span class="mx-2 text-gray-400">/</span>
            <span class="text-gray-700">{{ $article->title }}</span>
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
                        <a href="{{ route('articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                            Semua Artikel
                        </a>
                    </li>
                    @foreach($categories as $category)
                        <li>
                            <a href="{{ route('articles.index', ['category' => $category->id]) }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
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
                    <x-primary-button type="button" data-open-modal="#reportModal" class="w-full">Buat Laporan</x-primary-button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="col-span-12 md:col-span-9">
            <div class="bg-transparent border border-transparent rounded-lg overflow-hidden">
                <div class="p-8">
                    <!-- Article Meta -->
                    <div class="mb-6 pb-6 border-b border-gray-200">
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                            <!-- <span>Kategori: <span class="font-medium text-gray-900">{{ $article->category->name }}</span></span>
                            <span>•</span> -->
                            <span>Dibuat oleh: <span class="font-medium text-gray-900">{{ $article->staff->name }}</span></span>
                            <span>•</span>
                            <span>{{ $article->views }} dilihat</span>
                            <span>•</span>
                            <span>Dipublikasikan: {{ $article->created_at->format('d M Y') }}</span>
                        </div>
                    </div>

                    <!-- Article Content -->
                    <div class="prose prose-lg max-w-none">
                        {!! $article->content !!}
                    </div>

                    @if(session('success'))
                        <x-alert type="success" class="mt-6">
                            {{ session('success') }}
                        </x-alert>
                    @endif

                    @if(!session('article_feedback_' . $article->id))
                    <div class="mt-6 border-t pt-6">
                        <p class="text-lg font-semibold mb-4">Apakah artikel ini membantu Anda?</p>
                        <form action="{{ route('articles.feedback', $article) }}" method="POST" class="inline-block mr-4">
                            @csrf
                            <input type="hidden" name="is_helpful" value="1">
                            <x-primary-button type="submit" class="border border-red-500 text-red-500 bg-white hover:bg-red-50 font-medium py-2 px-4">Ya, Membantu</x-primary-button>
                        </form>
                        <form action="{{ route('articles.feedback', $article) }}" method="POST" class="inline-block">
                            @csrf
                            <input type="hidden" name="is_helpful" value="0">
                            <x-danger-button type="submit" class="border border-red-500 text-red-500 bg-white hover:bg-red-50 font-medium py-2 px-4">Tidak Membantu</x-danger-button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Back to Articles -->
            <div class="mt-8 text-center">
                <a href="{{ request('category') ? route('articles.index', ['category' => request('category')]) : route('articles.index') }}">
                    <x-secondary-button class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 font-medium"> 
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Kembali ke Artikel Bantuan
                    </x-secondary-button>
                </a>
            </div>
        </div>

    </div>
</div>

@endsection

<!-- Chatbot Widget -->
<x-chatbot-widget />
@include('reports.create')

