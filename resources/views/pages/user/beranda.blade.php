<x-layout.user
  title="{{ match ($page) {'catalog' => 'Katalog Barang','apply' => 'Ajukan Peminjaman','history' => 'Riwayat Peminjaman',default => 'Beranda'} }} - InvenTrack">
  @if ($page === 'home')
    <style>
      @keyframes home-card-enter {
        from {
          opacity: 0;
          transform: translate3d(0, 12px, 0);
        }

        to {
          opacity: 1;
          transform: translate3d(0, 0, 0);
        }
      }

      @keyframes home-hero-arrive {
        from {
          opacity: 0;
          transform: translate3d(0, 12px, 0);
        }

        to {
          opacity: 1;
          transform: translate3d(0, 0, 0);
        }
      }

      .home-hero-content {
        animation: home-hero-arrive 560ms both cubic-bezier(.16, 1, .3, 1);
      }

      html.home-reveal-enabled .home-scroll-reveal:not(.home-revealed):not(.is-visible) {
        animation: none !important;
        opacity: 0;
        transform: translate3d(0, 12px, 0);
      }

      .home-scroll-reveal.is-visible {
        animation: home-card-enter 420ms both cubic-bezier(.16, 1, .3, 1);
        animation-delay: var(--product-card-delay, var(--home-card-delay, 0ms));
      }

      .home-scroll-reveal.home-revealed {
        opacity: 1;
        transform: none;
      }

      html.home-reveal-enabled .home-scroll-reveal.home-revealed {
        animation: none !important;
      }

      .home-card-interaction {
        transform: translateZ(0);
        transition: transform 180ms cubic-bezier(.2, .75, .25, 1), box-shadow 180ms ease;
      }

      .home-card-interaction:hover,
      .home-card-interaction:focus-within {
        transform: translateY(-4px);
        box-shadow: 0 14px 28px rgb(20 55 48 / 16%);
      }

      .home-card-interaction:active {
        transform: scale(.985);
      }

      .home-pressable {
        transition: transform 150ms cubic-bezier(.2, .75, .25, 1), box-shadow 150ms ease, filter 150ms ease;
      }

      .home-pressable:hover {
        filter: brightness(1.04);
      }

      .home-pressable:active {
        transform: scale(.96);
      }

      @media (prefers-reduced-motion: reduce) {
        .home-hero-content {
          animation: none;
        }

        .home-scroll-reveal,
        .home-scroll-reveal.is-visible,
        .home-scroll-reveal.home-revealed {
          animation: none;
          opacity: 1;
          filter: none;
          transform: none;
        }

        .home-card-interaction,
        .home-pressable {
          transition: none;
        }
      }
    </style>
    <script data-user-page-script="home-reveals">
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.classList.add('home-reveal-enabled');
      }

      const initHomeReveals = () => {
        const home = document.getElementById('beranda');
        if (!home) {
          return;
        }

        const revealItems = home.querySelectorAll('.home-scroll-reveal');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!('IntersectionObserver' in window) || prefersReducedMotion) {
          revealItems.forEach((item) => item.classList.add('home-revealed'));
          return;
        }

        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) {
              return;
            }

            const item = entry.target;
            observer.unobserve(item);
            item.classList.add('is-visible');
            item.addEventListener('animationend', () => {
              item.classList.remove('is-visible');
              item.classList.add('home-revealed');
            }, {
              once: true
            });
          });
        }, {
          threshold: 0.04,
          rootMargin: '0px 0px 72px 0px'
        });

        revealItems.forEach((item) => {
          if (item.classList.contains('home-revealed') || item.classList.contains('is-visible')) {
            return;
          }

          if (item.closest('[aria-hidden="true"]')) {
            item.classList.add('home-revealed');
            return;
          }

          observer.observe(item);
        });
      };

      window.addEventListener('user:page-updated', initHomeReveals);
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHomeReveals, {
          once: true
        });
      }
    </script>
    <main id="beranda" class="pb-14">
      <section
        class="relative isolate overflow-hidden bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 pb-12 pt-[calc(88px+3rem)] text-white sm:px-10 sm:pb-16 sm:pt-[calc(88px+4rem)] lg:px-16 lg:pb-20">
        <span
          class="pointer-events-none absolute -right-10 -top-20 -z-10 h-64 w-64 rounded-full bg-[#00e3aa]/20 sm:right-[10%] sm:h-80 sm:w-80"
          aria-hidden="true"></span>
        <span class="pointer-events-none absolute right-[8%] top-[56%] -z-10 h-20 w-20 rounded-full bg-[#00e3aa]/20"
          aria-hidden="true"></span>
        <div class="home-hero-content flex w-full flex-col justify-between gap-6 md:flex-row md:items-end">
          <div class="min-w-0 max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-white/90 sm:text-sm">InvenTrack · Peminjaman
              Barang Jurusan</p>
            <h1 class="mt-3 max-w-xl text-[clamp(2rem,6vw,3rem)] font-bold leading-tight tracking-tight">Pinjam
              barang<br class="hidden sm:block"> jurusan, tanpa ribet.</h1>
            <p class="mt-4 max-w-xl text-sm leading-relaxed text-white/90 sm:text-base">Pilih barang dari katalog,
              masukkan ke keranjang, lalu ajukan. Semua status peminjamanmu bisa dipantau di satu tempat.</p>
            <div class="mt-6 flex flex-wrap gap-3">
              <a href="{{ route('user.catalog') }}"
                class="home-pressable rounded-lg bg-[#00e39a] px-4 py-2.5 text-sm font-semibold text-[#004f46] shadow-sm transition hover:bg-[#43efb4]">Jelajahi
                Katalog</a>
              <a href="{{ route('user.history') }}"
                class="home-pressable rounded-lg bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/10 transition hover:bg-white/25">Riwayat
                Saya</a>
            </div>
          </div>
          <div class="flex w-full justify-end gap-3 md:w-auto md:pb-1">
            <div
              class="min-w-28 rounded-xl border border-[#003d37]/30 bg-white/10 px-4 py-3 text-center backdrop-blur-sm">
              <strong class="block text-2xl font-bold sm:text-3xl">{{ number_format($inventoryKinds) }}</strong>
              <span class="text-xs text-white/90 sm:text-sm">Jenis Barang</span>
            </div>
          </div>
        </div>
      </section>

      <section class="w-full px-5 pt-6 sm:px-10 lg:px-16" aria-labelledby="steps-heading">
        <h2 id="steps-heading" class="mb-3 px-1 text-lg font-bold">Cara meminjam</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))] gap-3">
          @foreach ([['number' => 1, 'title' => 'Pilih Barang', 'description' => 'Cari barang pada katalog dan pastikan stoknya tersedia.'], ['number' => 2, 'title' => 'Ajukan peminjaman', 'description' => 'Sampaikan keperluan dan rencana tanggal pengembalian kepada petugas.'], ['number' => 3, 'title' => 'Ambil barang', 'description' => 'Tunggu konfirmasi petugas sebelum mengambil barang yang diajukan.']] as $step)
            <article
              class="user-card-enter home-scroll-reveal group flex min-h-24 items-start gap-3 rounded-xl bg-white p-4 shadow-[0_2px_4px_rgba(20,55,48,0.18)]"
              style="--home-card-delay: {{ min($loop->index * 40, 160) }}ms">
              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00d69f] font-bold text-[#004d43]">{{ $step['number'] }}</span>
              <div class="min-w-0">
                <h3 class="font-semibold text-slate-900 transition-colors duration-200 group-hover:text-[#008b7a]">
                  {{ $step['title'] }}</h3>
                <p
                  class="mt-0.5 text-xs leading-relaxed text-slate-500 transition-colors duration-200 group-hover:text-[#007f70]">
                  {{ $step['description'] }}</p>
              </div>
            </article>
          @endforeach
        </div>
      </section>

      <section id="riwayat" class="grid w-full grid-cols-2 gap-3 px-5 py-6 sm:grid-cols-4 sm:gap-4 sm:px-10 lg:px-16"
        aria-label="Ringkasan peminjaman">
        @foreach ([['count' => $loanCounts['dipinjam'], 'label' => 'Sedang dipinjam', 'color' => '#0bb6a6'], ['count' => $loanCounts['pending'], 'label' => 'Menunggu konfirmasi', 'color' => '#087f65'], ['count' => $loanCounts['dikembalikan'], 'label' => 'Riwayat selesai', 'color' => '#5975d5'], ['count' => $loanCounts['closed'], 'label' => 'Ditolak / dibatalkan', 'color' => '#66716f']] as $summary)
          <article
            class="user-card-enter home-scroll-reveal group flex min-h-24 min-w-0 flex-col justify-center rounded-2xl border-l-4 bg-white px-3 py-4 shadow-[0_3px_5px_rgba(20,55,48,0.18)] sm:border-l-[7px] sm:px-6"
            style="border-color: {{ $summary['color'] }}; --home-card-delay: {{ min($loop->index * 40, 160) }}ms">
            <strong
              class="text-2xl font-bold leading-none transition-colors duration-200 group-hover:text-[#008b7a] sm:text-3xl">{{ number_format($summary['count']) }}</strong>
            <span
              class="mt-1.5 break-words text-xs leading-4 text-slate-500 transition-colors duration-200 group-hover:text-[#007f70] sm:text-sm">{{ $summary['label'] }}</span>
          </article>
        @endforeach
      </section>

      <section class="w-full px-5 pb-7 sm:px-10 lg:px-16" aria-labelledby="categories-heading">
        <h2 id="categories-heading" class="mb-3 px-1 text-lg font-bold">Jelajahi kategori</h2>
        @if ($categories->isNotEmpty())
          <div class="category-marquee -mx-4 mb-4 overflow-x-auto rounded-2xl px-4 sm:hidden" role="region"
            aria-label="Kategori barang. Geser ke samping untuk melihat kategori lainnya.">
            <div class="category-track">
              @foreach ([0, 1] as $copy)
                <div class="flex shrink-0 gap-4 pr-4" @if ($copy === 1) aria-hidden="true" @endif>
                  @foreach ($categories as $category)
                    <a href="{{ route('user.catalog', ['category' => $category->kategori_id]) }}"
                      tabindex="{{ $copy === 1 ? '-1' : '0' }}"
                      class="user-card-enter home-scroll-reveal home-card-interaction group w-[min(78vw,18rem)] shrink-0 snap-start rounded-2xl bg-white px-6 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)]"
                      style="--home-card-delay: {{ min($loop->index * 40, 160) }}ms">
                      <strong
                        class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                      <span
                        class="mt-2 block truncate text-2xl font-bold text-slate-950 transition-colors duration-200 group-hover:text-[#008b7a] group-focus-visible:text-[#008b7a] motion-reduce:transition-none">{{ $category->nama_kategori }}</span>
                      <span
                        class="inline-flex items-center gap-1 text-sm text-slate-500 transition-colors duration-200 group-hover:text-[#007f70] group-focus-visible:text-[#007f70] motion-reduce:transition-none">
                        Lihat semua
                        <iconify-icon icon="mdi:arrow-right"
                          class="-translate-x-1 opacity-0 transition-[transform,opacity] duration-200 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100 motion-reduce:transition-none"
                          aria-hidden="true"></iconify-icon>
                      </span>
                    </a>
                  @endforeach
                </div>
              @endforeach
            </div>
          </div>
          <div class="hidden sm:block">
            @if ($categories->count() > 4)
              <div class="category-marquee overflow-hidden rounded-2xl"
                aria-label="Kategori barang, arahkan kursor atau fokus untuk menghentikan gerakan">
                <div class="category-track">
                  @foreach ([0, 1] as $copy)
                    <div class="flex gap-4 pr-4" @if ($copy === 1) aria-hidden="true" @endif>
                      @foreach ($categories as $category)
                        <a href="{{ route('user.catalog', ['category' => $category->kategori_id]) }}"
                          tabindex="{{ $copy === 1 ? '-1' : '0' }}"
                          class="user-card-enter home-scroll-reveal home-card-interaction group w-64 shrink-0 rounded-2xl bg-white px-8 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)] sm:w-72"
                          style="--home-card-delay: {{ min($loop->index * 40, 160) }}ms">
                          <strong
                            class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                          <span
                            class="mt-2 block truncate text-2xl font-bold text-slate-950 transition-colors duration-200 group-hover:text-[#008b7a] group-focus-visible:text-[#008b7a] motion-reduce:transition-none">{{ $category->nama_kategori }}</span>
                          <span
                            class="inline-flex items-center gap-1 text-sm text-slate-500 transition-colors duration-200 group-hover:text-[#007f70] group-focus-visible:text-[#007f70] motion-reduce:transition-none">
                            Lihat semua
                            <iconify-icon icon="mdi:arrow-right"
                              class="-translate-x-1 opacity-0 transition-[transform,opacity] duration-200 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100 motion-reduce:transition-none"
                              aria-hidden="true"></iconify-icon>
                          </span>
                        </a>
                      @endforeach
                    </div>
                  @endforeach
                </div>
              </div>
            @else
              <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,18rem),18rem))] gap-4 lg:gap-6">
                @foreach ($categories as $category)
                  <a href="{{ route('user.catalog', ['category' => $category->kategori_id]) }}"
                    class="user-card-enter home-scroll-reveal home-card-interaction group w-[min(78vw,18rem)] rounded-2xl bg-white px-6 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)]"
                    style="--home-card-delay: {{ min($loop->index * 40, 160) }}ms">
                    <strong
                      class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                    <span
                      class="mt-2 block truncate text-2xl font-bold text-slate-950 transition-colors duration-200 group-hover:text-[#008b7a] group-focus-visible:text-[#008b7a] motion-reduce:transition-none">{{ $category->nama_kategori }}</span>
                    <span
                      class="inline-flex items-center gap-1 text-sm text-slate-500 transition-colors duration-200 group-hover:text-[#007f70] group-focus-visible:text-[#007f70] motion-reduce:transition-none">
                      Lihat semua
                      <iconify-icon icon="mdi:arrow-right"
                        class="-translate-x-1 opacity-0 transition-[transform,opacity] duration-200 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100 motion-reduce:transition-none"
                        aria-hidden="true"></iconify-icon>
                    </span>
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        @else
          <p class="rounded-xl bg-white p-6 text-sm text-slate-500">Belum ada kategori barang.</p>
        @endif
      </section>

      <section id="katalog" class="w-full px-5 pb-7 sm:px-10 lg:px-16" aria-labelledby="featured-heading">
        <div class="mb-3 flex flex-col gap-1 px-1 sm:flex-row sm:items-center sm:justify-between">
          <h2 id="featured-heading" class="text-lg font-bold">Sering dipinjam</h2>
        </div>
        @if ($featuredItems->isNotEmpty())
          <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($featuredItems as $item)
              <article
                class="user-card-enter home-scroll-reveal home-card-interaction group relative flex min-h-44 flex-col overflow-hidden rounded-xl border border-[#c7e4dc] border-t-[5px] border-t-[#00b19c] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.14)]"
                data-stock-card="{{ $item->barang_id }}" data-stock-available="{{ (int) $item->stok_tersedia }}"
                data-stock-total="{{ (int) $item->stok_total }}"
                style="--home-card-delay: {{ min($loop->index * 40, 160) }}ms">
                <a data-user-navigation-link href="{{ route('user.barang.detail', ['barang' => $item->barang_id]) }}"
                  class="absolute inset-0 z-20 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#008b7a]"
                  aria-label="Lihat detail {{ $item->nama_barang }}"></a>
                <div
                  class="pointer-events-none relative z-10 aspect-[16/10] w-full overflow-hidden bg-gradient-to-br from-[#dff7ef] to-[#f2fbf8]"
                  @if ($item->image_url) x-data="{ imageLoaded: false, imageFailed: false }"
                    x-init="$nextTick(() => { if ($refs.productImage.complete) { imageLoaded = $refs.productImage.naturalWidth > 0; imageFailed = !imageLoaded; } })" @endif>
                  @if ($item->image_url)
                    <div x-cloak x-show="!imageLoaded && !imageFailed" class="home-image-skeleton absolute inset-0"
                      aria-hidden="true"></div>
                    <img x-ref="productImage" src="{{ $item->image_url }}" alt="Foto {{ $item->nama_barang }}"
                      loading="lazy" decoding="async" :class="imageLoaded ? 'opacity-100' : 'opacity-0'"
                      x-on:load="imageLoaded = true" x-on:error="imageFailed = true"
                      class="h-full w-full object-cover object-center transition-[opacity,transform] duration-300 group-hover:scale-[1.035] motion-reduce:transition-none">
                    <div x-cloak x-show="imageFailed" class="flex h-full items-center justify-center text-[#538b7d]"
                      role="img" aria-label="Foto {{ $item->nama_barang }} belum tersedia">
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
                    <span
                      class="max-w-[65%] truncate rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-700 transition-colors duration-200 group-hover:text-[#007f70]">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                    <span data-stock-label
                      class="rounded-full bg-[#d5f7ec] px-2.5 py-1 text-[10px] font-semibold text-[#16745e]">Tersedia</span>
                  </div>
                  <h3
                    class="mt-2 truncate text-sm font-bold text-slate-900 transition-colors duration-200 group-hover:text-[#008b7a]"
                    title="{{ $item->nama_barang }}">
                    {{ $item->nama_barang }}</h3>
                  <p class="mt-2 flex items-baseline gap-1.5"><strong data-stock-count
                      class="text-xl font-extrabold text-[#006f62] transition-colors duration-200 group-hover:text-[#008b7a]">{{ number_format($item->stok_tersedia) }}</strong><span
                      class="text-xs text-slate-500">/ {{ number_format($item->stok_total) }} tersedia</span></p>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar"
                    aria-label="Stok tersedia {{ $item->nama_barang }}" aria-valuenow="{{ $item->stok_tersedia }}"
                    data-stock-progress aria-valuemin="0" aria-valuemax="{{ max($item->stok_total, 1) }}">
                    <span data-stock-fill class="block h-full rounded-full bg-[#00b19c]"
                      style="width: {{ min(100, (int) round(($item->stok_tersedia / max($item->stok_total, 1)) * 100)) }}%"></span>
                  </div>
                  {{-- ASVS V1.2.3: @js safely serializes database values into Alpine expressions. --}}
                  <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                    <button type="button" data-stock-action data-stock-available="{{ (int) $item->stok_tersedia }}"
                      @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), stock: Number($el.dataset.stockAvailable), category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })"
                      class="home-pressable pointer-events-auto inline-flex flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-full bg-[#00d69f] px-2.5 py-2 text-xs font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                      <iconify-icon icon="mdi:cart-plus" aria-hidden="true"></iconify-icon>
                      Keranjang</button>
                    <button type="button" data-stock-action data-stock-available="{{ (int) $item->stok_tersedia }}"
                      @click="openLoanModal({ id: @js($item->barang_id), name: @js($item->nama_barang), stock: Number($el.dataset.stockAvailable), category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })"
                      class="home-pressable pointer-events-auto flex-1 whitespace-nowrap rounded-full border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-800 transition hover:border-[#00a88f] hover:text-[#007f70] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">Pinjam
                      Sekarang</button>
                  </div>
                </div>
              </article>
            @endforeach
          </div>
        @else
          <p class="rounded-xl bg-white p-6 text-sm text-slate-500">Belum ada barang yang tersedia untuk dipinjam.</p>
        @endif
      </section>

    </main>
  @elseif ($page === 'catalog')
    @include('pages.user.katalog')
  @elseif ($page === 'apply')
    @include('pages.user.ajukan-peminjaman')
  @else
    @include('pages.user.riwayat')
  @endif
</x-layout.user>
