<style>
  @keyframes catalog-card-enter {
    from {
      opacity: 0;
      transform: translate3d(0, 12px, 0);
    }

    to {
      opacity: 1;
      transform: translate3d(0, 0, 0);
    }
  }

  @keyframes catalog-results-enter {
    from {
      opacity: 0;
      transform: translate3d(0, 8px, 0) scale(.995);
    }

    to {
      opacity: 1;
      transform: translate3d(0, 0, 0) scale(1);
    }
  }

  @keyframes catalog-results-exit {
    to {
      opacity: 0;
      transform: translate3d(0, -4px, 0) scale(.995);
    }
  }

  @keyframes catalog-image-shimmer {
    to {
      background-position: -200% 0;
    }
  }

  html.catalog-reveal-enabled .catalog-scroll-reveal:not(.catalog-revealed):not(.is-visible) {
    animation: none !important;
    opacity: 0;
    transform: translate3d(0, 12px, 0);
  }

  .catalog-scroll-reveal.is-visible {
    animation: catalog-card-enter 420ms both cubic-bezier(.16, 1, .3, 1);
    animation-delay: var(--product-card-delay, var(--catalog-card-delay, 0ms));
  }

  .catalog-scroll-reveal.catalog-revealed {
    opacity: 1;
    transform: none;
  }

  .catalog-image-skeleton {
    background: linear-gradient(90deg, #dcefe9 25%, #f7fffc 50%, #dcefe9 75%);
    background-size: 200% 100%;
    animation: catalog-image-shimmer 1.2s ease-in-out infinite;
  }

  html.catalog-reveal-enabled .catalog-scroll-reveal.catalog-revealed {
    animation: none !important;
  }

  @supports (view-transition-name: none) {
    #catalog-content [aria-label="Daftar barang"] {
      view-transition-name: catalog-results;
    }

    ::view-transition-old(catalog-results) {
      animation: catalog-results-exit 160ms ease-in both;
    }

    ::view-transition-new(catalog-results) {
      animation: catalog-results-enter 420ms cubic-bezier(.16, 1, .3, 1) both;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .catalog-scroll-reveal,
    .catalog-scroll-reveal.is-visible,
    .catalog-scroll-reveal.catalog-revealed {
      animation: none;
      opacity: 1;
      filter: none;
      transform: none;
    }

    .catalog-image-skeleton {
      animation: none;
    }

    ::view-transition-old(catalog-results),
    ::view-transition-new(catalog-results) {
      animation: none;
    }
  }
</style>

<script data-user-page-script="catalog-reveals">
  (() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!reducedMotion.matches) {
      document.documentElement.classList.add('catalog-reveal-enabled');
    }

    window.initCatalogReveals = (root) => {
      const cards = root.querySelectorAll('.catalog-scroll-reveal');

      if (!('IntersectionObserver' in window) || reducedMotion.matches) {
        cards.forEach((card) => card.classList.add('catalog-revealed'));
        return;
      }

      const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          currentObserver.unobserve(entry.target);
          entry.target.classList.add('is-visible');
          entry.target.addEventListener('animationend', () => {
            entry.target.classList.remove('is-visible');
            entry.target.classList.add('catalog-revealed');
          }, { once: true });
        });
      }, { threshold: 0.04, rootMargin: '0px 0px 72px 0px' });

      cards.forEach((card) => observer.observe(card));
    };

    window.addEventListener('catalog:updated', (event) => {
      if (event.detail && event.detail.root) {
        window.initCatalogReveals(event.detail.root);
      }
    });

    const initCurrentCatalogReveals = () => {
      const catalog = document.getElementById('catalog-content');
      if (catalog) {
        window.initCatalogReveals(catalog);
      }
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initCurrentCatalogReveals, { once: true });
    }
  })();
</script>

    <main id="catalog-content" class="min-h-[calc(100vh-5rem)] w-full pb-12">
      <x-user.hero-section
        title="Katalog Barang"
        description="Pilih barang yang kamu butuhkan, lalu masukkan ke keranjang."
      />
      <section class="w-full px-5 pt-6 pb-8 sm:px-10 lg:px-16" aria-label="Filter katalog">
        <form method="GET" action="{{ route('user.catalog') }}" data-catalog-filter class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center sm:gap-8">
          <div x-data="{ categoryOverflow: false, categoryCanScrollBackward: false, categoryCanScrollForward: false, checkCategoryOverflow() { this.$nextTick(() => { this.categoryOverflow = this.$refs.catalogCategoryStrip.scrollWidth > this.$refs.catalogCategoryStrip.clientWidth + 1; this.updateCategoryControls() }) }, updateCategoryControls() { const strip = this.$refs.catalogCategoryStrip; this.categoryCanScrollBackward = strip.scrollLeft > 1; this.categoryCanScrollForward = strip.scrollLeft + strip.clientWidth < strip.scrollWidth - 1 } }" x-init="checkCategoryOverflow()" @resize.window="checkCategoryOverflow()" class="flex min-w-0 flex-1 items-center gap-1.5">
            <button type="button" x-cloak x-show="categoryCanScrollBackward" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-75" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-75" @click="$refs.catalogCategoryStrip.scrollBy({ left: -180, behavior: 'smooth' })" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#00a88f] bg-white text-[#007f70] transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2" aria-label="Tampilkan kategori sebelumnya">
              <iconify-icon icon="mdi:chevron-left" aria-hidden="true"></iconify-icon>
            </button>
            <nav x-ref="catalogCategoryStrip" @scroll.passive="updateCategoryControls()" class="flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Kategori barang">
              <a data-catalog-ajax href="{{ route('user.catalog', ['q' => $search]) }}" @if ($selectedCategory === '') aria-current="page" @endif class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 {{ $selectedCategory === '' ? 'border-[#00a88f] bg-[#00a88f] text-white shadow-sm' : 'border-[#00a88f] bg-white text-[#007f70] hover:bg-emerald-50' }}">Semua</a>
              @foreach ($categories as $category)
                <a data-catalog-ajax href="{{ route('user.catalog', ['category' => $category->kategori_id, 'q' => $search]) }}" @if ($selectedCategory === $category->kategori_id) aria-current="page" @endif class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 {{ $selectedCategory === $category->kategori_id ? 'border-[#00a88f] bg-[#00a88f] text-white shadow-sm' : 'border-[#00a88f] bg-white text-[#007f70] hover:bg-emerald-50' }}">{{ $category->nama_kategori }}</a>
              @endforeach
            </nav>
            <button type="button" x-cloak x-show="categoryCanScrollForward" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-75" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-75" @click="$refs.catalogCategoryStrip.scrollBy({ left: 180, behavior: 'smooth' })" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#00a88f] bg-white text-[#007f70] transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2" aria-label="Tampilkan kategori berikutnya">
              <iconify-icon icon="mdi:chevron-right" aria-hidden="true"></iconify-icon>
            </button>
          </div>
          <input type="hidden" name="category" value="{{ $selectedCategory }}">
          <label class="relative block w-full min-w-0 sm:ml-auto sm:w-72 sm:shrink-0">
            <span class="sr-only">Cari barang</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari barang..." class="w-full rounded-full border border-[#92d9cd] bg-white py-2 pl-4 pr-10 text-xs outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20 sm:text-sm">
            <button type="submit" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500" aria-label="Cari"><iconify-icon icon="mdi:magnify" aria-hidden="true"></iconify-icon></button>
          </label>
        </form>
      </section>
      <section class="w-full px-5 sm:px-10 lg:px-16" aria-label="Daftar barang">
        @if ($errors->any())
          <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
            @foreach ($errors->all() as $error)
              <p>{{ $error }}</p>
            @endforeach
          </div>
        @endif
        @if ($catalogItems->isNotEmpty())
          <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($catalogItems as $item)
              <article
                class="catalog-scroll-reveal group relative flex min-h-44 flex-col overflow-hidden rounded-xl border border-[#c7e4dc] border-t-[5px] border-t-[#00b19c] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.14)]"
                style="--catalog-card-delay: {{ min($loop->index * 40, 160) }}ms">
                <a
                  data-user-navigation-link
                  href="{{ route('user.barang.detail', ['barang' => $item->barang_id]) }}"
                  class="absolute inset-0 z-20 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#008b7a]"
                  aria-label="Lihat detail {{ $item->nama_barang }}"
                ></a>
                <div
                  class="pointer-events-none relative z-10 aspect-[16/10] w-full overflow-hidden bg-gradient-to-br from-[#dff7ef] to-[#f2fbf8]"
                  @if ($item->image_url)
                    x-data="{ imageLoaded: false, imageFailed: false }"
                    x-init="$nextTick(() => { if ($refs.catalogImage.complete) { imageLoaded = $refs.catalogImage.naturalWidth > 0; imageFailed = !imageLoaded; } })"
                  @endif
                >
                  @if ($item->image_url)
                    <div x-cloak x-show="!imageLoaded && !imageFailed"
                      class="catalog-image-skeleton absolute inset-0" aria-hidden="true"></div>
                    <img x-ref="catalogImage" src="{{ $item->image_url }}" alt="Foto {{ $item->nama_barang }}"
                      loading="lazy" decoding="async" :class="imageLoaded ? 'opacity-100' : 'opacity-0'"
                      x-on:load="imageLoaded = true" x-on:error="imageFailed = true"
                      class="h-full w-full object-cover object-center transition-[opacity,transform] duration-300 group-hover:scale-[1.035] motion-reduce:transition-none">
                    <div x-cloak x-show="imageFailed"
                      class="flex h-full items-center justify-center text-[#538b7d]" role="img"
                      aria-label="Foto {{ $item->nama_barang }} belum tersedia">
                      <iconify-icon icon="mdi:image-outline" class="text-4xl" aria-hidden="true"></iconify-icon>
                    </div>
                  @else
                    <div class="flex h-full items-center justify-center text-[#538b7d]" role="img"
                      aria-label="Foto {{ $item->nama_barang }} belum tersedia">
                      <iconify-icon icon="mdi:image-outline" class="text-4xl" aria-hidden="true"></iconify-icon>
                    </div>
                  @endif
                </div>
                <div class="pointer-events-none relative z-30 flex flex-1 flex-col p-3.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="max-w-[65%] truncate rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-700">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                    <span class="rounded-full bg-[#d5f7ec] px-2.5 py-1 text-[10px] font-semibold text-[#16745e]">Tersedia</span>
                  </div>
                  <h2 class="mt-2 truncate text-sm font-bold text-slate-900" title="{{ $item->nama_barang }}">{{ $item->nama_barang }}</h2>
                  <p class="mt-2 flex items-baseline gap-1.5"><strong class="text-xl font-extrabold text-[#006f62]">{{ number_format($item->stok_tersedia) }}</strong><span class="text-xs text-slate-500">/ {{ number_format($item->stok_total) }} tersedia</span></p>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Stok tersedia {{ $item->nama_barang }}" aria-valuenow="{{ $item->stok_tersedia }}" aria-valuemin="0" aria-valuemax="{{ max($item->stok_total, 1) }}"><span class="block h-full rounded-full bg-[#00b19c]" style="width: {{ min(100, (int) round($item->stok_tersedia / max($item->stok_total, 1) * 100)) }}%"></span></div>
                  {{-- ASVS V1.2.3: @js safely serializes database values into Alpine expressions. --}}
                  <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                    <button type="button" @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })" class="pointer-events-auto inline-flex flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-full bg-[#00d69f] px-2.5 py-2 text-xs font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2"><iconify-icon icon="mdi:cart-plus" aria-hidden="true"></iconify-icon>Keranjang</button>
                    <button type="button" @click="openLoanModal({ id: @js($item->barang_id), name: @js($item->nama_barang), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })" class="pointer-events-auto flex-1 whitespace-nowrap rounded-full border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-800 transition hover:border-[#00a88f] hover:text-[#007f70] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">Pinjam Sekarang</button>
                  </div>
                </div>
              </article>
            @endforeach
          </div>
          <x-pagination :paginator="$catalogItems" label="Navigasi halaman katalog" variant="user"
            link-attribute="data-catalog-ajax" nav-class="mt-6 flex flex-wrap justify-center gap-1.5" />
        @else
          <div class="flex min-h-64 flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <iconify-icon icon="mdi:package-variant-remove" class="text-4xl text-[#008b7a]" aria-hidden="true"></iconify-icon>
            <p class="max-w-xl text-base font-semibold leading-relaxed text-slate-700 sm:text-lg">
              @if ($search !== '')
                Tidak ada barang yang tersedia dengan kata kunci <strong class="font-bold text-slate-950">"{{ $search }}"</strong>.
              @else
                Tidak ada barang tersedia pada kategori ini.
              @endif
            </p>
          </div>
        @endif
      </section>
    </main>
