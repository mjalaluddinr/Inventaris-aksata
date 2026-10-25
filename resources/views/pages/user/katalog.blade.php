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

    <main class="mx-auto min-h-[calc(100vh-5rem)] max-w-[1600px] pb-12">
      <section class="relative isolate overflow-hidden bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 py-9 text-center text-white sm:py-12">
        <span class="pointer-events-none absolute -right-10 -top-20 -z-10 h-56 w-56 rounded-full bg-[#00e3aa]/20" aria-hidden="true"></span>
        <h1 class="text-2xl font-bold sm:text-3xl">Katalog Barang</h1>
        <p class="mt-2 text-sm text-white/90">Pilih barang yang kamu butuhkan, lalu masukkan ke keranjang.</p>
      </section>
      <section class="px-4 py-5 sm:px-8 lg:px-10" aria-label="Filter katalog">
        <form method="GET" action="{{ route('user.beranda') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
          <input type="hidden" name="page" value="catalog">
          <div class="flex flex-wrap gap-2">
            <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="rounded-full border px-4 py-2 text-xs font-semibold {{ $selectedCategory === '' ? 'border-[#00a88f] bg-[#00a88f] text-white' : 'border-[#00a88f] bg-white text-[#007f70]' }}">Semua</a>
            @foreach ($categories as $category)
              <a href="{{ route('user.beranda', ['page' => 'catalog', 'category' => $category->kategori_id, 'q' => $search]) }}" class="rounded-full border px-4 py-2 text-xs font-semibold {{ $selectedCategory === $category->kategori_id ? 'border-[#00a88f] bg-[#00a88f] text-white' : 'border-[#00a88f] bg-white text-[#007f70]' }}">{{ $category->nama_kategori }}</a>
            @endforeach
          </div>
          <label class="relative ml-auto block w-full sm:max-w-xs">
            <span class="sr-only">Cari barang</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari barang..." class="w-full rounded-full border border-[#92d9cd] bg-white py-2 pl-4 pr-10 text-sm outline-none focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
            <button type="submit" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500" aria-label="Cari"><iconify-icon icon="mdi:magnify" aria-hidden="true"></iconify-icon></button>
          </label>
        </form>
      </section>
      <section class="px-4 sm:px-8 lg:px-10" aria-label="Daftar barang">
        @if ($catalogItems->isNotEmpty())
          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($catalogItems as $item)
              <article class="user-card-enter overflow-hidden rounded-xl border border-slate-200 border-t-[6px] border-t-[#00b19c] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.2)]" style="animation-delay: {{ $loop->index * 45 }}ms">
                <div class="p-4">
                  <div class="flex items-center justify-between gap-2">
                    <span class="max-w-[65%] truncate rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                    <span class="rounded-full bg-[#d5f7ec] px-2.5 py-1 text-[11px] font-semibold text-[#16745e]">Tersedia</span>
                  </div>
                  <h2 class="mt-2 truncate font-semibold text-slate-900" title="{{ $item->nama_barang }}">{{ $item->nama_barang }}</h2>
                  <p class="mt-2 truncate text-xs text-slate-500">{{ $item->kode_barang }} · {{ $item->satuan }}</p>
                  <p class="mt-2 text-sm"><strong>{{ number_format($item->stok_tersedia) }}</strong><span class="text-slate-500"> / {{ number_format($item->stok_total) }} {{ $item->satuan }} tersedia</span></p>
                  <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-[#00b19c]" style="width: {{ min(100, (int) round($item->stok_tersedia / max($item->stok_total, 1) * 100)) }}%"></span></div>
                  <div class="mt-3 flex items-center justify-between gap-2">
                    <span class="text-[11px] text-slate-500">{{ number_format($item->detail_peminjamans_count) }} kali dipinjam</span>
                    <button type="button" @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), code: @js($item->kode_barang), unit: @js($item->satuan), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })" class="rounded-lg bg-[#00d69f] px-3 py-2 text-xs font-semibold text-[#003f38] transition hover:bg-[#36e9b3]">+ Keranjang</button>
                  </div>
                </div>
              </article>
            @endforeach
          </div>
          <div class="mt-6">{{ $catalogItems->links() }}</div>
        @else
          <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Tidak ada barang tersedia yang cocok dengan pencarian ini.</p>
        @endif
      </section>
    </main>
