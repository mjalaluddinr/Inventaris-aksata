@props([
    'paginator',
    'label' => 'Navigasi halaman',
    'variant' => 'admin',
    'linkAttribute' => null,
    'navAttribute' => null,
    'navClass' => '',
])

@php
    $isUser = $variant === 'user';
    $hasNumberedPages = method_exists($paginator, 'lastPage') && method_exists($paginator, 'getUrlRange');
    $lastPage = $hasNumberedPages ? $paginator->lastPage() : $paginator->currentPage();
    $paginationStart = max(1, min($paginator->currentPage() - 2, $lastPage - 4));
    $paginationEnd = min($lastPage, $paginationStart + 4);
    $buttonClass = $isUser
        ? 'inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg border border-[#92d9cd] bg-white px-2 text-sm font-semibold text-[#007f70] transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2'
        : 'inline-flex h-10 min-w-10 shrink-0 items-center justify-center rounded-xl border border-[#00BFA5] bg-white px-3 text-sm font-semibold text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2';
    $iconButtonClass = $isUser
        ? 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#92d9cd] bg-white text-[#007f70] transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2'
        : 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2';
    $activeClass = $isUser
        ? 'inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg border border-[#00a88f] bg-[#00c99c] px-2 text-sm font-bold text-[#004e48] shadow-sm'
        : 'inline-flex h-10 min-w-10 shrink-0 items-center justify-center rounded-xl border border-[#00BFA5] bg-[#00CFA8] px-3 text-sm font-semibold text-slate-950 shadow-[0_4px_10px_rgba(0,177,156,0.2)]';
    $disabledClass = $isUser
        ? 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#b8ddd4] bg-white/70 text-slate-400'
        : 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-400';
@endphp

@if ($paginator->hasPages())
    <nav {{ $navAttribute }} aria-label="{{ $label }}"
        class="{{ $navClass !== '' ? $navClass : 'flex flex-wrap items-center justify-end gap-2' }}">
        @if ($hasNumberedPages && !$paginator->onFirstPage())
            <a {{ $linkAttribute }} href="{{ $paginator->url(1) }}" aria-label="Halaman pertama"
                class="{{ $buttonClass }}">&lt;&lt;</a>
        @elseif ($hasNumberedPages)
            <span aria-label="Halaman pertama" aria-disabled="true" class="{{ $disabledClass }} px-3 text-sm font-semibold">&lt;&lt;</span>
        @endif

        @if (!$paginator->onFirstPage())
            <a {{ $linkAttribute }} href="{{ $paginator->previousPageUrl() }}" rel="prev"
                aria-label="Halaman sebelumnya" class="{{ $iconButtonClass }}">
                <iconify-icon icon="mdi:chevron-left" width="22" aria-hidden="true"></iconify-icon>
            </a>
        @else
            <span aria-label="Halaman sebelumnya" aria-disabled="true" class="{{ $disabledClass }}">
                <iconify-icon icon="mdi:chevron-left" width="22" aria-hidden="true"></iconify-icon>
            </span>
        @endif

        @if ($hasNumberedPages)
            @if ($paginationStart > 1)
                <a {{ $linkAttribute }} href="{{ $paginator->url(1) }}" aria-label="Halaman 1"
                    class="{{ $buttonClass }}">1</a>
                @if ($paginationStart > 2)
                    <span aria-hidden="true" class="shrink-0 px-1 text-slate-500">…</span>
                @endif
            @endif

            @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
                @if ($page === $paginator->currentPage())
                    <span aria-label="Halaman {{ $page }}" aria-current="page" class="{{ $activeClass }}">
                        {{ $page }}
                    </span>
                @else
                    <a {{ $linkAttribute }} href="{{ $paginator->url($page) }}" aria-label="Halaman {{ $page }}"
                        class="{{ $buttonClass }}">{{ $page }}</a>
                @endif
            @endfor

            @if ($paginationEnd < $lastPage - 1)
                <span aria-hidden="true" class="shrink-0 px-1 text-slate-500">…</span>
            @endif
            @if ($paginationEnd < $lastPage)
                @if ($paginator->currentPage() === $lastPage)
                    <span aria-label="Halaman {{ $lastPage }}" aria-current="page" class="{{ $activeClass }}">
                        {{ $lastPage }}
                    </span>
                @else
                    <a {{ $linkAttribute }} href="{{ $paginator->url($lastPage) }}" aria-label="Halaman {{ $lastPage }}"
                        class="{{ $buttonClass }}">{{ $lastPage }}</a>
                @endif
            @endif
        @endif

        @if ($paginator->hasMorePages())
            <a {{ $linkAttribute }} href="{{ $paginator->nextPageUrl() }}" rel="next"
                aria-label="Halaman berikutnya" class="{{ $iconButtonClass }}">
                <iconify-icon icon="mdi:chevron-right" width="22" aria-hidden="true"></iconify-icon>
            </a>
        @else
            <span aria-label="Halaman berikutnya" aria-disabled="true" class="{{ $disabledClass }}">
                <iconify-icon icon="mdi:chevron-right" width="22" aria-hidden="true"></iconify-icon>
            </span>
        @endif

        @if ($hasNumberedPages && $paginator->currentPage() < $lastPage)
            <a {{ $linkAttribute }} href="{{ $paginator->url($lastPage) }}" aria-label="Halaman terakhir"
                class="{{ $buttonClass }}">&gt;&gt;</a>
        @elseif ($hasNumberedPages)
            <span aria-label="Halaman terakhir" aria-disabled="true"
                class="{{ $disabledClass }} px-3 text-sm font-semibold">&gt;&gt;</span>
        @endif
    </nav>
@endif
