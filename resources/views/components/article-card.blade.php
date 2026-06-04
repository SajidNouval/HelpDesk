@props([
    'article',
    'selectedCategoryId' => null,
    'showCategory' => true,
    'showAuthor' => true,
    'showViews' => true,
    'showDate' => true,
    'href' => null,
])

@php
    $defaultHref = route('articles.show', ['slug' => $article->slug] + ($selectedCategoryId ? ['category' => $selectedCategoryId] : []));
    $link = $href ?? $defaultHref;
@endphp

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition overflow-hidden']) }}>
    <div class="p-6">
        <!-- Article Header -->
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <h3 class="text-xl font-bold text-gray-900 hover:text-red-600 transition">
                    <a href="{{ $link }}" class="block">
                        {{ $article->title }}
                    </a>
                </h3>
                
                <!-- Meta Information -->
                @if($showCategory || $showAuthor || $showViews)
                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-600 flex-wrap">
                        @if($showCategory && $article->category)
                            <span class="truncate max-w-[200px]">Kategori: <span class="font-medium">{{ $article->category->name }}</span></span>
                        @endif
                        @if($showCategory && !$article->category)
                            <span>Kategori: <span class="font-medium">Umum</span></span>
                        @endif
                        @if($showCategory && ($showAuthor || $showViews))
                            <span>•</span>
                        @endif
                        @if($showAuthor)
                            <span>Dibuat oleh: <span class="font-medium">{{ $article->staff?->name ?? 'Admin' }}</span></span>
                        @endif
                        @if($showViews && ($showCategory || $showAuthor))
                            <span>•</span>
                        @endif
                        @if($showViews)
                            <span>{{ $article->views }} dilihat</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Article Excerpt -->
        <div class="text-gray-700 leading-relaxed mb-4">
            <x-truncate-text :value="strip_tags($article->content)" :limit="200" class="block" />
        </div>

        <!-- Footer: Read More + Date -->
        <div class="flex items-center justify-between">
            <a href="{{ $link }}" class="text-red-500 hover:text-red-600 font-medium transition">
                Baca selengkapnya →
            </a>
            @if($showDate && $article->created_at)
                <div class="text-sm text-gray-500">
                    Tanggal Pembuatan: {{ $article->created_at->format('d M Y') }}
                </div>
            @endif
        </div>
    </div>
</div>