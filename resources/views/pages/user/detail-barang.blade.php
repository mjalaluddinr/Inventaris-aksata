@php
  $categoryName = $barang->kategori?->nama_kategori ?? 'Tanpa kategori';
  $unitName = $barang->satuan ?: 'unit';
@endphp

<x-layout.user :title="$barang->nama_barang.' - Detail Barang | InvenTrack'">
  <main id="product-detail-content" class="min-h-[calc(100vh-5rem)] w-full pb-12">
    <x-user.hero-section
      title="Detail Barang"
      description="Lihat kategori, spesifikasi, kondisi, dan ketersediaan barang sebelum menambahkannya ke keranjang atau mengajukan peminjaman."
    />

    <section class="mx-auto w-full max-w-7xl px-5 py-6 sm:px-8 sm:py-8 lg:px-10" aria-label="Detail barang">
      <nav aria-label="Breadcrumb" class="mb-5 text-sm">
        <ol class="flex flex-wrap items-center gap-2">
          <li><a data-user-navigation-link href="{{ route('user.beranda') }}" class="rounded-sm font-medium text-slate-600 transition-colors hover:text-[#006f62] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#008b7a]">Beranda</a></li>
          <li aria-hidden="true" class="text-slate-400">/</li>
          <li><a data-user-navigation-link href="{{ route('user.catalog') }}" class="rounded-sm font-medium text-slate-600 transition-colors hover:text-[#006f62] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#008b7a]">Katalog</a></li>
          <li aria-hidden="true" class="text-slate-400">/</li>
          <li aria-current="page" class="max-w-[16rem] truncate font-medium text-[#006f62] sm:max-w-sm">{{ $barang->nama_barang }}</li>
        </ol>
      </nav>

      <div class="grid overflow-hidden rounded-2xl border border-[#c7e4dc] bg-white shadow-[0_8px_28px_rgba(20,55,48,0.1)] lg:grid-cols-[minmax(0,1.08fr)_minmax(22rem,0.92fr)]">
        <div class="flex items-center justify-center bg-white p-4 sm:p-6 lg:p-8">
          <div class="group relative aspect-[4/3] w-full max-w-[36rem] overflow-hidden rounded-xl bg-[#f2fbf8] p-5 sm:p-8">
            @if ($barang->image_url)
              <img
                src="{{ $barang->image_url }}"
                alt="Foto {{ $barang->nama_barang }}"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                class="h-full w-full object-contain transition-transform duration-500 group-hover:scale-[1.04] motion-reduce:transition-none"
              >
            @else
              <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-[#538b7d]" role="img" aria-label="Foto {{ $barang->nama_barang }} belum tersedia">
                <iconify-icon icon="mdi:image-outline" class="text-6xl" aria-hidden="true"></iconify-icon>
                <span class="text-sm font-medium">Foto belum tersedia</span>
              </div>
            @endif
          </div>
        </div>

        <div class="flex min-w-0 flex-col gap-5 p-5 sm:p-7 lg:p-8">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="max-w-full truncate rounded-full bg-[#e6f8f3] px-3 py-1.5 text-xs font-semibold text-[#007f70]">{{ $categoryName }}</span>
            <span class="inline-flex items-center gap-2 rounded-full bg-[#d5f7ec] px-3 py-1.5 text-xs font-semibold text-[#16745e]">
              <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
              {{ $barang->stok_tersedia > 0 ? 'Tersedia' : 'Stok habis' }}
            </span>
          </div>

          <div>
            <h2 class="text-lg font-bold text-slate-900">Ketersediaan barang</h2>
            <p class="mt-2 flex items-baseline gap-2 text-slate-600">
              <strong class="text-3xl font-extrabold leading-none text-[#006f62]">{{ number_format($barang->stok_tersedia) }}</strong>
              <span class="text-sm">{{ $unitName }} tersedia dari {{ number_format($barang->stok_total) }}</span>
            </p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar"
              aria-label="Stok tersedia {{ $barang->nama_barang }}" aria-valuenow="{{ $barang->stok_tersedia }}"
              aria-valuemin="0" aria-valuemax="{{ max($barang->stok_total, 1) }}">
              <span class="block h-full rounded-full bg-[#00b19c]"
                style="width: {{ min(100, (int) round(($barang->stok_tersedia / max($barang->stok_total, 1)) * 100)) }}%"></span>
            </div>
          </div>

          <dl class="grid gap-4 border-y border-slate-100 py-5 sm:grid-cols-2">
            <div>
              <dt class="text-xs font-semibold text-slate-500">Kondisi</dt>
              <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $barang->kondisi ? ucfirst($barang->kondisi) : 'Belum dicatat' }}</dd>
            </div>
            <div>
              <dt class="text-xs font-semibold text-slate-500">Satuan</dt>
              <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $unitName }}</dd>
            </div>
          </dl>

          <div>
            <h3 class="text-sm font-bold text-slate-900">Spesifikasi</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $barang->spesifikasi ?: 'Spesifikasi barang belum dicatat.' }}</p>
          </div>

          {{-- ASVS V1.2.3: @js safely encodes database values used in Alpine expressions. --}}
          <div class="mt-auto grid gap-2 pt-2 sm:grid-cols-2">
            <button type="button"
              @click="addToCart({ id: @js($barang->barang_id), name: @js($barang->nama_barang), stock: {{ (int) $barang->stok_tersedia }}, category: @js($categoryName) })"
              :disabled="!{{ (int) $barang->stok_tersedia }}"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-[#00d69f] px-4 py-2.5 text-sm font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
              <iconify-icon icon="mdi:cart-plus" aria-hidden="true"></iconify-icon>
              Tambah ke keranjang
            </button>
            <button type="button"
              @click="openLoanModal({ id: @js($barang->barang_id), name: @js($barang->nama_barang), stock: {{ (int) $barang->stok_tersedia }}, category: @js($categoryName) })"
              :disabled="!{{ (int) $barang->stok_tersedia }}"
              class="min-h-11 rounded-full border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-[#00a88f] hover:text-[#007f70] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
              Pinjam sekarang
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto w-full max-w-7xl px-5 pb-8 sm:px-8 lg:px-10" aria-labelledby="related-products-heading">
      <div class="mb-4 flex items-center justify-between gap-4">
        <div>
          <h2 id="related-products-heading" class="text-lg font-bold text-slate-900">Produk lainnya</h2>
        </div>
        <a data-user-navigation-link href="{{ route('user.catalog') }}"
          class="shrink-0 rounded-md text-sm font-semibold text-[#007f70] transition hover:text-[#005d52] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">
          Lihat semua
        </a>
      </div>

      @if ($relatedProducts->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($relatedProducts as $item)
            <article class="user-card-enter overflow-hidden rounded-xl border border-[#c7e4dc] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.12)]">
              <a data-user-navigation-link href="{{ route('user.barang.detail', ['barang' => $item->barang_id]) }}"
                class="group block focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#008b7a]">
                <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-[#dff7ef] to-[#f2fbf8]">
                  @if ($item->image_url)
                    <img src="{{ $item->image_url }}" alt="Foto {{ $item->nama_barang }}" loading="lazy" decoding="async"
                      class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.04] motion-reduce:transition-none">
                  @else
                    <div class="absolute inset-0 flex items-center justify-center text-[#538b7d]" role="img" aria-label="Foto {{ $item->nama_barang }} belum tersedia">
                      <iconify-icon icon="mdi:image-outline" class="text-4xl" aria-hidden="true"></iconify-icon>
                    </div>
                  @endif
                </div>
                <div class="p-4">
                  <span class="inline-block max-w-full truncate rounded-full bg-[#e6f8f3] px-2.5 py-1 text-[10px] font-semibold text-[#007f70]">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                  <h3 class="mt-2 truncate text-sm font-bold text-slate-900">{{ $item->nama_barang }}</h3>
                  <p class="mt-1 text-xs text-slate-500">Stok: {{ number_format($item->stok_tersedia) }} {{ $item->satuan ?: 'unit' }}</p>
                </div>
              </a>
              {{-- ASVS V1.2.3: @js safely encodes database values used in Alpine expressions. --}}
              <button type="button"
                @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })"
                class="mx-4 mb-4 inline-flex min-h-10 w-[calc(100%-2rem)] items-center justify-center gap-2 rounded-lg bg-[#00d69f] px-3 py-2 text-xs font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">
                <iconify-icon icon="mdi:cart-plus" aria-hidden="true"></iconify-icon>
                Tambah ke keranjang
              </button>
            </article>
          @endforeach
        </div>
      @else
        <p class="rounded-xl border border-[#c7e4dc] bg-white p-5 text-sm text-slate-500">Belum ada barang lain yang tersedia.</p>
      @endif
    </section>
  </main>
</x-layout.user>
