@php
  $search = mb_substr(trim((string) request()->query('q', '')), 0, 100);
  $selectedCategory = (string) request()->query('category', '');
  $categories = \App\Models\Kategori::query()->withCount('barangs')->orderBy('nama_kategori')->get();
  $catalogItems = \App\Models\Barang::query()
      ->with('kategori')
      ->withCount('detailPeminjamans')
      ->where('stok_tersedia', '>', 0)
      ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
          $query->where('nama_barang', 'like', '%'.$search.'%')
              ->orWhere('kode_barang', 'like', '%'.$search.'%');
      }))
      ->when($selectedCategory !== '', fn ($query) => $query->where('kategori_id', $selectedCategory))
      ->orderBy('nama_barang')
      ->paginate(12, ['*'], 'catalog_page')
      ->withQueryString();
@endphp

    <main id="catalog-content" class="min-h-[calc(100vh-5rem)] w-full pb-12">
      <x-user.hero-section
        title="Katalog Barang"
        description="Pilih barang yang kamu butuhkan, lalu masukkan ke keranjang."
      />
      <section class="w-full px-5 pt-6 pb-8 sm:px-10 lg:px-16" aria-label="Filter katalog">
        <form method="GET" action="{{ route('user.beranda') }}" data-catalog-filter class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center sm:gap-8">
          <input type="hidden" name="page" value="catalog">
          <div x-data="{ categoryOverflow: false, categoryCanScrollBackward: false, categoryCanScrollForward: false, checkCategoryOverflow() { this.$nextTick(() => { this.categoryOverflow = this.$refs.catalogCategoryStrip.scrollWidth > this.$refs.catalogCategoryStrip.clientWidth + 1; this.updateCategoryControls() }) }, updateCategoryControls() { const strip = this.$refs.catalogCategoryStrip; this.categoryCanScrollBackward = strip.scrollLeft > 1; this.categoryCanScrollForward = strip.scrollLeft + strip.clientWidth < strip.scrollWidth - 1 } }" x-init="checkCategoryOverflow()" @resize.window="checkCategoryOverflow()" class="flex min-w-0 flex-1 items-center gap-1.5">
            <button type="button" x-cloak x-show="categoryCanScrollBackward" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-75" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-75" @click="$refs.catalogCategoryStrip.scrollBy({ left: -180, behavior: 'smooth' })" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#00a88f] bg-white text-[#007f70] transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2" aria-label="Tampilkan kategori sebelumnya">
              <iconify-icon icon="mdi:chevron-left" aria-hidden="true"></iconify-icon>
            </button>
            <nav x-ref="catalogCategoryStrip" @scroll.passive="updateCategoryControls()" class="flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Kategori barang">
              <a data-catalog-ajax href="{{ route('user.beranda', ['page' => 'catalog', 'q' => $search]) }}" @if ($selectedCategory === '') aria-current="page" @endif class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 {{ $selectedCategory === '' ? 'border-[#00a88f] bg-[#00a88f] text-white shadow-sm' : 'border-[#00a88f] bg-white text-[#007f70] hover:bg-emerald-50' }}">Semua</a>
              @foreach ($categories as $category)
                <a data-catalog-ajax href="{{ route('user.beranda', ['page' => 'catalog', 'category' => $category->kategori_id, 'q' => $search]) }}" @if ($selectedCategory === $category->kategori_id) aria-current="page" @endif class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 {{ $selectedCategory === $category->kategori_id ? 'border-[#00a88f] bg-[#00a88f] text-white shadow-sm' : 'border-[#00a88f] bg-white text-[#007f70] hover:bg-emerald-50' }}">{{ $category->nama_kategori }}</a>
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
              <article class="flex min-h-44 flex-col overflow-hidden rounded-xl border border-[#c7e4dc] border-t-[5px] border-t-[#00b19c] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.14)]">
                <div class="aspect-[16/10] w-full overflow-hidden bg-gradient-to-br from-[#dff7ef] to-[#f2fbf8]">
                  @if ($item->image_url)
                    <img src="{{ $item->image_url }}" alt="Foto {{ $item->nama_barang }}" loading="lazy"
                      decoding="async" class="h-full w-full object-cover">
                  @else
                    <div class="flex h-full flex-col items-center justify-center gap-2 text-[#538b7d]" role="img"
                      aria-label="Foto {{ $item->nama_barang }} belum tersedia">
                      <iconify-icon icon="mdi:image-outline" class="text-4xl" aria-hidden="true"></iconify-icon>
                      <span class="text-xs font-medium">Foto belum tersedia</span>
                    </div>
                  @endif
                </div>
                <div class="flex flex-1 flex-col p-3.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="max-w-[65%] truncate rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-700">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                    <span class="rounded-full bg-[#d5f7ec] px-2.5 py-1 text-[10px] font-semibold text-[#16745e]">Tersedia</span>
                  </div>
                  <h2 class="mt-2 truncate text-sm font-bold text-slate-900" title="{{ $item->nama_barang }}">{{ $item->nama_barang }}</h2>
                  <p class="mt-1 truncate text-[10px] text-slate-500">{{ $item->kode_barang }} · {{ $item->satuan }}</p>
                  <p class="mt-2 text-xs"><strong>{{ number_format($item->stok_tersedia) }}</strong><span class="text-slate-500"> / {{ number_format($item->stok_total) }} tersedia</span></p>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Stok tersedia {{ $item->nama_barang }}" aria-valuenow="{{ $item->stok_tersedia }}" aria-valuemin="0" aria-valuemax="{{ max($item->stok_total, 1) }}"><span class="block h-full rounded-full bg-[#00b19c]" style="width: {{ min(100, (int) round($item->stok_tersedia / max($item->stok_total, 1) * 100)) }}%"></span></div>
                  <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                    <button type="button" @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), code: @js($item->kode_barang), unit: @js($item->satuan), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })" class="rounded-full bg-[#00d69f] px-3 py-1.5 text-[10px] font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">+ Keranjang</button>
                    <button type="button" @click="openLoanModal({ id: @js($item->barang_id), name: @js($item->nama_barang), code: @js($item->kode_barang), unit: @js($item->satuan), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })" class="rounded-full border border-slate-300 px-3 py-1.5 text-[10px] font-semibold text-slate-800 transition hover:border-[#00a88f] hover:text-[#007f70] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">Pinjam Sekarang</button>
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
