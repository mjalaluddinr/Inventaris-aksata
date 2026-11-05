<x-layout.user
  title="{{ match ($page) {'catalog' => 'Katalog Barang','apply' => 'Ajukan Peminjaman','history' => 'Riwayat Peminjaman',default => 'Beranda'} }} - InvenTrack">
  @if ($page === 'home')
    <x-auth.login-welcome />
    <main id="beranda" class="pb-14">
      <section
        class="relative isolate overflow-hidden bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 pb-10 pt-[calc(88px+2.5rem)] text-white sm:px-10 sm:pb-14 sm:pt-[calc(88px+3.5rem)] lg:px-16 lg:pb-16">
        <span
          class="pointer-events-none absolute -right-10 -top-20 -z-10 h-64 w-64 rounded-full bg-[#00e3aa]/20 sm:right-[10%] sm:h-80 sm:w-80"
          aria-hidden="true"></span>
        <span class="pointer-events-none absolute right-[8%] top-[56%] -z-10 h-20 w-20 rounded-full bg-[#00e3aa]/20"
          aria-hidden="true"></span>
        <div class="flex w-full flex-col justify-between gap-6 md:flex-row md:items-end">
          <div class="min-w-0 max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-white/90 sm:text-sm">InvenTrack · Peminjaman
              Barang Jurusan</p>
            <h1 class="mt-3 max-w-xl text-[clamp(2rem,6vw,3rem)] font-bold leading-tight tracking-tight">Pinjam
              barang<br class="hidden sm:block"> jurusan, tanpa ribet.</h1>
            <p class="mt-4 max-w-xl text-sm leading-relaxed text-white/90 sm:text-base">Pilih barang dari katalog,
              masukkan ke keranjang, lalu ajukan. Semua status peminjamanmu bisa dipantau di satu tempat.</p>
            <div class="mt-6 flex flex-wrap gap-3">
              <a href="{{ route('user.beranda', ['page' => 'catalog']) }}"
                class="rounded-lg bg-[#00e39a] px-4 py-2.5 text-sm font-semibold text-[#004f46] shadow-sm transition hover:bg-[#43efb4]">Jelajahi
                Katalog</a>
              <a href="{{ route('user.beranda', ['page' => 'history']) }}"
                class="rounded-lg bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/10 transition hover:bg-white/25">Riwayat
                Saya</a>
            </div>
          </div>
          <div class="flex flex-wrap gap-3 md:pb-1">
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
              class="user-card-enter flex min-h-24 items-start gap-3 rounded-xl bg-white p-4 shadow-[0_2px_4px_rgba(20,55,48,0.18)]">
              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00d69f] font-bold text-[#004d43]">{{ $step['number'] }}</span>
              <div class="min-w-0">
                <h3 class="font-semibold text-slate-900">{{ $step['title'] }}</h3>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ $step['description'] }}</p>
              </div>
            </article>
          @endforeach
        </div>
      </section>

      <section id="riwayat"
        class="grid w-full grid-cols-2 gap-3 px-5 py-6 sm:grid-cols-4 sm:gap-4 sm:px-10 lg:px-16"
        aria-label="Ringkasan peminjaman">
        @foreach ([['count' => $loanCounts['dipinjam'], 'label' => 'Sedang dipinjam', 'color' => '#0bb6a6'], ['count' => $loanCounts['pending'], 'label' => 'Menunggu konfirmasi', 'color' => '#087f65'], ['count' => $loanCounts['dikembalikan'], 'label' => 'Riwayat selesai', 'color' => '#5975d5'], ['count' => $loanCounts['closed'], 'label' => 'Ditolak / dibatalkan', 'color' => '#66716f']] as $summary)
          <article
            class="user-card-enter flex min-h-24 min-w-0 flex-col justify-center rounded-2xl border-l-4 bg-white px-3 py-4 shadow-[0_3px_5px_rgba(20,55,48,0.18)] sm:border-l-[7px] sm:px-6"
            style="border-color: {{ $summary['color'] }}">
            <strong class="text-2xl font-bold leading-none sm:text-3xl">{{ number_format($summary['count']) }}</strong>
            <span class="mt-1.5 break-words text-xs leading-4 text-slate-500 sm:text-sm">{{ $summary['label'] }}</span>
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
                    <a href="#katalog" tabindex="{{ $copy === 1 ? '-1' : '0' }}"
                      class="user-card-enter w-[min(78vw,18rem)] shrink-0 snap-start rounded-2xl bg-white px-6 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)] transition hover:-translate-y-0.5 hover:shadow-lg">
                      <strong
                        class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                      <span
                        class="mt-2 block truncate text-2xl font-bold text-slate-950">{{ $category->nama_kategori }}</span>
                      <span class="text-sm text-slate-500">Lihat semua</span>
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
                        <a href="#katalog" tabindex="{{ $copy === 1 ? '-1' : '0' }}"
                          class="user-card-enter w-64 shrink-0 rounded-2xl bg-white px-8 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)] transition hover:-translate-y-0.5 hover:shadow-lg sm:w-72">
                          <strong
                            class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                          <span
                            class="mt-2 block truncate text-2xl font-bold text-slate-950">{{ $category->nama_kategori }}</span>
                          <span class="text-sm text-slate-500">Lihat semua</span>
                        </a>
                      @endforeach
                    </div>
                  @endforeach
                </div>
              </div>
            @else
              <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,18rem),18rem))] gap-4 lg:gap-6">
                @foreach ($categories as $category)
                  <a href="#katalog"
                    class="user-card-enter w-[min(78vw,18rem)] rounded-2xl bg-white px-6 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)] transition hover:-translate-y-0.5 hover:shadow-lg">
                    <strong
                      class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                    <span
                      class="mt-2 block truncate text-2xl font-bold text-slate-950">{{ $category->nama_kategori }}</span>
                    <span class="text-sm text-slate-500">Lihat semua</span>
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
                class="user-card-enter flex min-h-44 flex-col overflow-hidden rounded-xl border border-[#c7e4dc] border-t-[5px] border-t-[#00b19c] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.14)]"
              >
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
                    <span
                      class="max-w-[65%] truncate rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-700">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                    <span
                      class="rounded-full bg-[#d5f7ec] px-2.5 py-1 text-[10px] font-semibold text-[#16745e]">Tersedia</span>
                  </div>
                  <h3 class="mt-2 truncate text-sm font-bold text-slate-900" title="{{ $item->nama_barang }}">
                    {{ $item->nama_barang }}</h3>
                  <p class="mt-1 truncate text-[10px] text-slate-500">{{ $item->kode_barang }} · {{ $item->satuan }}</p>
                  <p class="mt-2 text-xs"><strong>{{ number_format($item->stok_tersedia) }}</strong><span
                      class="text-slate-500"> / {{ number_format($item->stok_total) }} tersedia</span></p>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar"
                    aria-label="Stok tersedia {{ $item->nama_barang }}" aria-valuenow="{{ $item->stok_tersedia }}"
                    aria-valuemin="0" aria-valuemax="{{ max($item->stok_total, 1) }}">
                    <span class="block h-full rounded-full bg-[#00b19c]"
                      style="width: {{ min(100, (int) round(($item->stok_tersedia / max($item->stok_total, 1)) * 100)) }}%"></span>
                  </div>
                  <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                    <button type="button"
                      @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), code: @js($item->kode_barang), unit: @js($item->satuan), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })"
                      class="rounded-full bg-[#00d69f] px-3 py-1.5 text-[10px] font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">+
                      Keranjang</button>
                    <button type="button"
                      @click="openLoanModal({ id: @js($item->barang_id), name: @js($item->nama_barang), code: @js($item->kode_barang), unit: @js($item->satuan), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })"
                      class="rounded-full border border-slate-300 px-3 py-1.5 text-[10px] font-semibold text-slate-800 transition hover:border-[#00a88f] hover:text-[#007f70] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">Pinjam
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
