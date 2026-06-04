@extends('layouts.app')

@section('title', $article->title . ' - Artikel Bantuan')

@section('content')

<!-- Header Section -->
<div class="bg-gray-100 py-6 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-3xl font-semibold text-gray-900 leading-snug">
            {{ $article->title }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $article->category->name ?? 'Artikel Bantuan' }}
        </p>

        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mt-2 flex items-center flex-wrap gap-x-1">
            <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
            <span class="text-gray-400">/</span>
            <a href="{{ route('articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Artikel Bantuan</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-700 truncate max-w-xs">{{ $article->title }}</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-12 gap-8">

        <!-- Sidebar -->
        <div class="col-span-12 md:col-span-3 min-w-0">
            <div class="border-r border-gray-200 pr-4">
                <h3 class="text-xs uppercase text-gray-400 mb-4 font-semibold tracking-wider">
                    Kategori Artikel
                </h3>

                <ul class="space-y-1 text-gray-700">
                    <li>
                        <a href="{{ route('articles.index') }}"
                           title="Semua Artikel"
                           class="flex items-center rounded-l-md px-3 py-2 transition hover:text-red-500">
                            <span class="truncate block whitespace-nowrap overflow-hidden text-sm">Semua Artikel</span>
                        </a>
                    </li>
                    @foreach($categories as $category)
                        <li>
                            <a href="{{ route('articles.index', ['category' => $category->id]) }}"
                               title="{{ $category->name }}"
                               class="flex items-center rounded-l-md px-3 py-2 transition overflow-hidden {{ $article->category_id == $category->id ? 'text-red-500 font-semibold border-l-4 border-red-500 bg-red-50' : 'hover:text-red-500' }}">
                                <span class="truncate block whitespace-nowrap overflow-hidden text-sm">{{ $category->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <!-- Contact Info -->
                <div class="mt-8 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <h4 class="font-semibold text-gray-700 mb-1 text-sm">Butuh Bantuan?</h4>
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        Tidak menemukan artikel yang dicari?
                    </p>
                    <x-primary-button type="button" data-open-modal="#reportModal" class="w-full h-10 rounded-xl text-sm font-medium flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Buat Laporan
                    </x-primary-button>
                </div>
            </div>
        </div>

        <!-- Article Content -->
        <div class="col-span-12 md:col-span-9">

            <!-- Article Meta -->
            <div class="mb-6 pb-5 border-b border-gray-200">
                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span>Ditulis oleh <span class="font-medium text-gray-700">{{ $article->staff->name }}</span></span>
                    </span>
                    <span class="text-gray-300">·</span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>{{ $article->created_at->format('d M Y') }}</span>
                    </span>
                    <span class="text-gray-300">·</span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span>{{ $article->views }} kali dilihat</span>
                    </span>
                    @if($article->category)
                        <span class="text-gray-300">·</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-700 rounded-full border border-red-100 font-medium">
                            {{ $article->category->name }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Article Body -->
            <div class="prose prose-lg max-w-none
                        prose-headings:font-semibold prose-headings:text-gray-900
                        prose-p:text-gray-700 prose-p:leading-relaxed prose-p:mb-4
                        prose-a:text-red-600 prose-a:no-underline hover:prose-a:underline
                        prose-strong:text-gray-900
                        prose-li:text-gray-700 prose-li:leading-relaxed
                        prose-ul:my-4 prose-ol:my-4
                        prose-pre:bg-gray-50 prose-pre:border prose-pre:border-gray-200 prose-pre:rounded-xl
                        prose-code:text-red-700 prose-code:bg-red-50 prose-code:px-1 prose-code:rounded
                        prose-blockquote:border-l-red-500 prose-blockquote:text-gray-600">
                {!! $article->content !!}
            </div>

            @if(session('success'))
                <x-alert type="success" class="mt-8">
                    {{ session('success') }}
                </x-alert>
            @endif

            <!-- Feedback Section -->
            @if(!session('article_feedback_' . $article->id))
                <div class="mt-10 pt-8 border-t border-gray-200">
                    <p class="text-sm font-semibold text-gray-700 mb-4">Apakah artikel ini membantu Anda?</p>
                    <div class="flex flex-wrap gap-3">
                        <form action="{{ route('articles.feedback', $article) }}" method="POST">
                            @csrf
                            <input type="hidden" name="is_helpful" value="1">
                            <button type="submit"
                                class="inline-flex items-center gap-2 h-10 px-5 rounded-xl border border-green-200 bg-green-50 text-green-700 text-sm font-medium hover:bg-green-100 hover:border-green-300 transition">
                                <span>👍</span>
                                Ya, Membantu
                            </button>
                        </form>
                        <form action="{{ route('articles.feedback', $article) }}" method="POST">
                            @csrf
                            <input type="hidden" name="is_helpful" value="0">
                            <button type="submit"
                                class="inline-flex items-center gap-2 h-10 px-5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100 hover:border-red-300 transition">
                                <span>👎</span>
                                Tidak Membantu
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="mt-10 pt-8 border-t border-gray-200">
                    <p class="text-sm text-gray-500">✅ Terima kasih atas masukan Anda!</p>
                </div>
            @endif

            <!-- Back Link -->
            <div class="mt-10 pt-6 border-t border-gray-100">
                <a href="{{ request('category') ? route('articles.index', ['category' => request('category')]) : route('articles.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Kembali ke Artikel Bantuan
                </a>
            </div>

        </div>
    </div>
</div>

@endsection

<!-- Chatbot Widget -->
<x-chatbot-widget />
@include('reports.create')
