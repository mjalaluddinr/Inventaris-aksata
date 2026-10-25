@php
  $page = request()->query('page', 'home');
  $page = in_array($page, ['home', 'catalog', 'apply', 'history'], true) ? $page : 'home';
  $currentUser = auth()->user();
  $userId = $currentUser?->akun_id;
  $categories = $page === 'home' ? \App\Models\Kategori::query()->withCount('barangs')->orderBy('nama_kategori')->get() : collect();
  $featuredItems = $page === 'home' ? \App\Models\Barang::query()->with('kategori')->withCount('detailPeminjamans')->where('stok_tersedia', '>', 0)->orderByDesc('detail_peminjamans_count')->limit(8)->get() : collect();
  $inventoryKinds = $page === 'home' ? \App\Models\Barang::query()->count() : 0;
  $availableStock = $page === 'home' ? \App\Models\Barang::query()->sum('stok_tersedia') : 0;
  $userLoans = $page === 'home' && $userId
      ? \App\Models\DetailPeminjaman::query()->whereHas('peminjaman', fn ($query) => $query->where('akun_id', $userId))->get(['status'])
      : collect();
  $loanCounts = [
      'dipinjam' => $userLoans->where('status', 'dipinjam')->count(),
      'pending' => $userLoans->where('status', 'pending')->count(),
      'dikembalikan' => $userLoans->where('status', 'dikembalikan')->count(),
      'closed' => $userLoans->whereIn('status', ['ditolak', 'dibatalkan'])->count(),
  ];
@endphp

<x-layout.user title="{{ match ($page) { 'catalog' => 'Katalog Barang', 'apply' => 'Ajukan Peminjaman', 'history' => 'Riwayat Peminjaman', default => 'Beranda' } }} - InvenTrack">
  @if ($page === 'home')
  <x-auth.login-welcome />
  <main id="beranda" class="mx-auto max-w-[1600px] pb-14">
    <section class="relative isolate overflow-hidden bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 py-10 text-white sm:px-10 sm:py-14 lg:px-16 lg:py-16">
      <span class="pointer-events-none absolute -right-10 -top-20 -z-10 h-64 w-64 rounded-full bg-[#00e3aa]/20 sm:right-[10%] sm:h-80 sm:w-80" aria-hidden="true"></span>
      <span class="pointer-events-none absolute right-[8%] top-[56%] -z-10 h-20 w-20 rounded-full bg-[#00e3aa]/20" aria-hidden="true"></span>
      <div class="flex flex-col justify-between gap-8 sm:flex-row sm:items-end">
        <div class="max-w-2xl">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-white/90 sm:text-sm">InvenTrack · Peminjaman Barang Jurusan</p>
          <h1 class="mt-3 max-w-xl text-3xl font-bold leading-tight tracking-tight sm:text-4xl lg:text-5xl">Pinjam barang<br class="hidden sm:block"> jurusan, tanpa ribet.</h1>
          <p class="mt-4 max-w-xl text-sm leading-relaxed text-white/90 sm:text-base">Pilih barang dari katalog, masukkan ke keranjang, lalu ajukan. Semua status peminjamanmu bisa dipantau di satu tempat.</p>
          <div class="mt-6 flex flex-wrap gap-3">
            <a href="#katalog" class="rounded-lg bg-[#00e39a] px-4 py-2.5 text-sm font-semibold text-[#004f46] shadow-sm transition hover:bg-[#43efb4]">Jelajahi Katalog</a>
            <a href="#riwayat" class="rounded-lg bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/10 transition hover:bg-white/25">Riwayat Saya</a>
          </div>
        </div>
        <div class="flex gap-3 sm:pb-1">
          <div class="min-w-28 rounded-xl border border-[#003d37]/30 bg-white/10 px-4 py-3 text-center backdrop-blur-sm">
            <strong class="block text-2xl font-bold sm:text-3xl">{{ number_format($inventoryKinds) }}</strong>
            <span class="text-xs text-white/90 sm:text-sm">Jenis Barang</span>
          </div>
          <div class="min-w-28 rounded-xl border border-[#003d37]/30 bg-white/10 px-4 py-3 text-center backdrop-blur-sm">
            <strong class="block text-2xl font-bold sm:text-3xl">{{ number_format($availableStock) }}</strong>
            <span class="text-xs text-white/90 sm:text-sm">Siap dipinjam</span>
          </div>
        </div>
      </div>
    </section>

    <section id="riwayat" class="grid gap-4 px-4 py-6 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:px-10" aria-label="Ringkasan peminjaman">
      @foreach ([
          ['count' => $loanCounts['dipinjam'], 'label' => 'Sedang dipinjam', 'color' => '#0bb6a6'],
          ['count' => $loanCounts['pending'], 'label' => 'Menunggu konfirmasi', 'color' => '#087f65'],
          ['count' => $loanCounts['dikembalikan'], 'label' => 'Riwayat selesai', 'color' => '#5975d5'],
          ['count' => $loanCounts['closed'], 'label' => 'Ditolak / dibatalkan', 'color' => '#66716f'],
      ] as $summary)
        <article class="user-card-enter flex min-h-24 flex-col justify-center rounded-2xl border-l-[7px] bg-white px-6 py-4 shadow-[0_3px_5px_rgba(20,55,48,0.18)]" style="border-color: {{ $summary['color'] }}; animation-delay: {{ $loop->index * 80 }}ms">
          <strong class="text-3xl font-bold leading-none">{{ number_format($summary['count']) }}</strong>
          <span class="mt-1.5 text-sm text-slate-500">{{ $summary['label'] }}</span>
        </article>
      @endforeach
    </section>

    <section class="px-4 pb-7 sm:px-8 lg:px-10" aria-labelledby="categories-heading">
      <h2 id="categories-heading" class="mb-3 px-1 text-lg font-bold">Jelajahi kategori</h2>
      @if ($categories->isNotEmpty())
        @if ($categories->count() > 4)
          <div class="category-marquee overflow-hidden rounded-2xl" aria-label="Kategori barang, arahkan kursor atau fokus untuk menghentikan gerakan">
            <div class="category-track">
              @foreach ([0, 1] as $copy)
                <div class="flex gap-4 pr-4" @if ($copy === 1) aria-hidden="true" @endif>
                  @foreach ($categories as $category)
                    <a href="#katalog" tabindex="{{ $copy === 1 ? '-1' : '0' }}" class="user-card-enter w-64 shrink-0 rounded-2xl bg-white px-8 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)] transition hover:-translate-y-0.5 hover:shadow-lg sm:w-72" style="animation-delay: {{ $loop->index * 60 }}ms">
                      <strong class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                      <span class="mt-2 block truncate text-2xl font-bold text-slate-950">{{ $category->nama_kategori }}</span>
                      <span class="text-sm text-slate-500">Lihat semua</span>
                    </a>
                  @endforeach
                </div>
              @endforeach
            </div>
          </div>
        @else
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
            @foreach ($categories as $category)
              <a href="#katalog" class="user-card-enter min-h-36 rounded-2xl bg-white px-8 py-5 shadow-[0_2px_4px_rgba(20,55,48,0.2)] transition hover:-translate-y-0.5 hover:shadow-lg" style="animation-delay: {{ $loop->index * 70 }}ms">
                <strong class="block text-4xl font-bold leading-none text-[#00866f]">{{ number_format($category->barangs_count) }}</strong>
                <span class="mt-2 block truncate text-2xl font-bold text-slate-950">{{ $category->nama_kategori }}</span>
                <span class="text-sm text-slate-500">Lihat semua</span>
              </a>
            @endforeach
          </div>
        @endif
      @else
        <p class="rounded-xl bg-white p-6 text-sm text-slate-500">Belum ada kategori barang.</p>
      @endif
    </section>

    <section id="katalog" class="px-4 pb-7 sm:px-8 lg:px-10" aria-labelledby="featured-heading">
      <div class="mb-3 flex items-center justify-between px-1">
        <h2 id="featured-heading" class="text-lg font-bold">Sering dipinjam</h2>
        <span class="text-xs text-slate-500">Diurutkan dari riwayat peminjaman</span>
      </div>
      @if ($featuredItems->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          @foreach ($featuredItems as $item)
            <article class="user-card-enter overflow-hidden rounded-xl border border-slate-200 border-t-[7px] border-t-[#00b19c] bg-white shadow-[0_2px_4px_rgba(20,55,48,0.2)]" style="animation-delay: {{ $loop->index * 70 }}ms">
              <div class="p-4">
                <div class="flex items-center justify-between gap-2">
                  <span class="max-w-[65%] truncate rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700">{{ $item->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                  <span class="rounded-full bg-[#d5f7ec] px-2.5 py-1 text-[11px] font-semibold text-[#16745e]">Tersedia</span>
                </div>
                <h3 class="mt-2 truncate font-semibold text-slate-900" title="{{ $item->nama_barang }}">{{ $item->nama_barang }}</h3>
                <p class="mt-2 truncate text-xs text-slate-500">{{ $item->kode_barang }} · {{ $item->satuan }}</p>
                <p class="mt-2 text-sm"><strong>{{ number_format($item->stok_tersedia) }}</strong><span class="text-slate-500"> / {{ number_format($item->stok_total) }} {{ $item->satuan }} tersedia</span></p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Persediaan {{ $item->nama_barang }}" aria-valuenow="{{ $item->stok_tersedia }}" aria-valuemin="0" aria-valuemax="{{ max($item->stok_total, 1) }}">
                  <span class="block h-full rounded-full bg-[#00b19c]" style="width: {{ min(100, (int) round($item->stok_tersedia / max($item->stok_total, 1) * 100)) }}%"></span>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2">
                  <span class="text-xs text-slate-500">{{ number_format($item->detail_peminjamans_count) }} kali dipinjam</span>
                  <button type="button" @click="addToCart({ id: @js($item->barang_id), name: @js($item->nama_barang), code: @js($item->kode_barang), unit: @js($item->satuan), stock: {{ (int) $item->stok_tersedia }}, category: @js($item->kategori?->nama_kategori ?? 'Tanpa kategori') })" class="rounded-lg bg-[#00c99a] px-3 py-2 text-xs font-semibold text-[#003f38] transition hover:bg-[#36e9b3]">+ Keranjang</button>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <p class="rounded-xl bg-white p-6 text-sm text-slate-500">Belum ada barang yang tersedia untuk dipinjam.</p>
      @endif
    </section>

    <section class="px-4 pt-1 sm:px-8 lg:px-10" aria-labelledby="steps-heading">
      <h2 id="steps-heading" class="mb-3 px-1 text-lg font-bold">Cara meminjam</h2>
      <div class="grid gap-3 lg:grid-cols-3">
        @foreach ([
            ['number' => 1, 'title' => 'Pilih Barang', 'description' => 'Cari barang pada katalog dan pastikan stoknya tersedia.'],
            ['number' => 2, 'title' => 'Ajukan peminjaman', 'description' => 'Sampaikan keperluan dan rencana tanggal pengembalian kepada petugas.'],
            ['number' => 3, 'title' => 'Ambil barang', 'description' => 'Tunggu konfirmasi petugas sebelum mengambil barang yang diajukan.'],
        ] as $step)
          <article class="user-card-enter flex min-h-24 items-start gap-3 rounded-xl bg-white p-4 shadow-[0_2px_4px_rgba(20,55,48,0.18)]" style="animation-delay: {{ $loop->index * 80 }}ms">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00d69f] font-bold text-[#004d43]">{{ $step['number'] }}</span>
            <div>
              <h3 class="font-semibold text-slate-900">{{ $step['title'] }}</h3>
              <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ $step['description'] }}</p>
            </div>
          </article>
        @endforeach
      </div>
    </section>
  </main>
  @elseif ($page === 'catalog')
    @include('pages.user.katalog')
  @elseif ($page === 'apply')
    <main class="mx-auto min-h-[calc(100vh-5rem)] max-w-[1600px] pb-12">
      <section class="bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 py-9 text-center text-white sm:py-12">
        <h1 class="text-2xl font-bold sm:text-3xl">Ajukan Peminjaman</h1>
        <p class="mt-2 text-sm text-white/90">Periksa barang di keranjang, lalu lengkapi detail pengajuan.</p>
      </section>
      <section class="mx-auto grid max-w-6xl gap-5 px-4 py-6 lg:grid-cols-[1.15fr_0.85fr] sm:px-8">
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-4 border-b border-slate-200 pb-3 text-base font-bold">Barang yang dipinjam</h2>
          <div x-show="cart.length === 0" class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Keranjang masih kosong. <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="font-semibold text-[#008f82] underline">Pilih barang dari katalog</a>.</div>
          <template x-for="item in cart" :key="item.id">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3">
              <div class="min-w-0"><h3 class="truncate text-sm font-semibold text-slate-800" x-text="item.name"></h3><p class="text-xs text-slate-500"><span x-text="item.code"></span> · <span x-text="item.category"></span></p></div>
              <div class="flex items-center gap-2"><span class="text-xs text-slate-500" x-text="`× ${item.quantity}`"></span><button type="button" @click="removeFromCart(item.id)" class="text-xs text-rose-600">Hapus</button></div>
            </div>
          </template>
          <div class="mt-4 flex justify-between font-semibold"><span>Total barang</span><span x-text="`${cart.length} item`"></span></div>
          <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="mt-3 inline-flex rounded-lg border border-[#00a88f] px-3 py-2 text-xs font-semibold text-[#007f70]">+ Tambah barang lain</a>
        </div>
        <form class="rounded-xl bg-white p-5 shadow-sm" @submit.prevent="showNotice('Form dan data keranjang siap. Penyimpanan pengajuan belum tersedia karena route pengajuan belum dibuat.')">
          <h2 class="mb-4 border-b border-slate-200 pb-3 text-base font-bold">Ringkasan Pengajuan</h2>
          <label class="mb-3 block text-xs font-semibold text-slate-700">Keperluan
            <input name="keperluan" maxlength="255" required placeholder="Contoh: Praktik kelas XI" class="mt-1 block w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-normal outline-none focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
          </label>
          <label class="mb-3 block text-xs font-semibold text-slate-700">Jenis Peminjaman
            <select name="jenis_peminjaman" class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-[#00a88f]"><option value="harian">Harian</option><option value="praktik">Praktik</option><option value="lainnya">Lainnya</option></select>
          </label>
          <label class="mb-4 block text-xs font-semibold text-slate-700">Tanggal Rencana Kembali
            <input type="date" name="tanggal_rencana_kembali" min="{{ now()->toDateString() }}" required class="mt-1 block w-full rounded-md border border-slate-200 px-3 py-2 text-sm font-normal outline-none focus:border-[#00a88f]">
          </label>
          <p class="mb-3 rounded-md bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">Form ini menyiapkan ringkasan pengajuan. Penyimpanan ke riwayat memerlukan endpoint server yang belum tersedia; data tidak akan dikirim atau disimpan ke database dari halaman ini.</p>
          <button type="submit" :disabled="cart.length === 0" class="w-full rounded-lg bg-[#00d69f] px-4 py-3 text-sm font-bold text-[#004d43] transition hover:bg-[#36e9b3] disabled:cursor-not-allowed disabled:opacity-50">Ajukan Peminjaman</button>
        </form>
      </section>
    </main>
  @else
    @include('pages.user.riwayat')
  @endif
</x-layout.user>
