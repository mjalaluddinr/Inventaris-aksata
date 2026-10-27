@php
  $page = request()->query('page', 'home');
  $page = in_array($page, ['home', 'catalog', 'apply', 'history'], true) ? $page : 'home';
  $currentUser = auth()->user();
  $userId = $currentUser?->akun_id;
  $categories = $page === 'home' ? \App\Models\Kategori::query()->withCount('barangs')->orderBy('nama_kategori')->get() : collect();
  $featuredItems = $page === 'home' ? \App\Models\Barang::query()->with('kategori')->withCount('detailPeminjamans')->where('stok_tersedia', '>', 0)->orderByDesc('detail_peminjamans_count')->orderBy('stok_tersedia')->limit(4)->get() : collect();
  $inventoryKinds = $page === 'home' ? \App\Models\Barang::query()->count() : 0;
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
  <main id="beranda" class="pb-14">
    <section class="relative isolate overflow-hidden bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 pb-10 pt-[calc(88px+2.5rem)] text-white sm:px-10 sm:pb-14 sm:pt-[calc(88px+3.5rem)] lg:px-16 lg:pb-16">
      <span class="pointer-events-none absolute -right-10 -top-20 -z-10 h-64 w-64 rounded-full bg-[#00e3aa]/20 sm:right-[10%] sm:h-80 sm:w-80" aria-hidden="true"></span>
      <span class="pointer-events-none absolute right-[8%] top-[56%] -z-10 h-20 w-20 rounded-full bg-[#00e3aa]/20" aria-hidden="true"></span>
      <div class="mx-auto flex w-full max-w-[1600px] flex-col justify-between gap-6 md:flex-row md:items-end">
        <div class="min-w-0 max-w-2xl">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-white/90 sm:text-sm">InvenTrack · Peminjaman Barang Jurusan</p>
          <h1 class="mt-3 max-w-xl text-[clamp(2rem,6vw,3rem)] font-bold leading-tight tracking-tight">Pinjam barang<br class="hidden sm:block"> jurusan, tanpa ribet.</h1>
          <p class="mt-4 max-w-xl text-sm leading-relaxed text-white/90 sm:text-base">Pilih barang dari katalog, masukkan ke keranjang, lalu ajukan. Semua status peminjamanmu bisa dipantau di satu tempat.</p>
          <div class="mt-6 flex flex-wrap gap-3">
            <a href="#katalog" class="rounded-lg bg-[#00e39a] px-4 py-2.5 text-sm font-semibold text-[#004f46] shadow-sm transition hover:bg-[#43efb4]">Jelajahi Katalog</a>
            <a href="#riwayat" class="rounded-lg bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/10 transition hover:bg-white/25">Riwayat Saya</a>
          </div>
        </div>
        <div class="flex flex-wrap gap-3 md:pb-1">
          <div class="min-w-28 rounded-xl border border-[#003d37]/30 bg-white/10 px-4 py-3 text-center backdrop-blur-sm">
            <strong class="block text-2xl font-bold sm:text-3xl">{{ number_format($inventoryKinds) }}</strong>
            <span class="text-xs text-white/90 sm:text-sm">Jenis Barang</span>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto w-full max-w-[1600px] px-4 pt-6 sm:px-8 lg:px-10" aria-labelledby="steps-heading">
      <h2 id="steps-heading" class="mb-3 px-1 text-lg font-bold">Cara meminjam</h2>
      <div class="grid gap-3 md:grid-cols-3">
        @foreach ([
            ['number' => 1, 'title' => 'Pilih Barang', 'description' => 'Cari barang pada katalog dan pastikan stoknya tersedia.'],
            ['number' => 2, 'title' => 'Ajukan peminjaman', 'description' => 'Sampaikan keperluan dan rencana tanggal pengembalian kepada petugas.'],
            ['number' => 3, 'title' => 'Ambil barang', 'description' => 'Tunggu konfirmasi petugas sebelum mengambil barang yang diajukan.'],
        ] as $step)
          <article class="user-card-enter flex min-h-24 items-start gap-3 rounded-xl bg-white p-4 shadow-[0_2px_4px_rgba(20,55,48,0.18)]" style="animation-delay: {{ $loop->index * 80 }}ms">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00d69f] font-bold text-[#004d43]">{{ $step['number'] }}</span>
            <div class="min-w-0">
              <h3 class="font-semibold text-slate-900">{{ $step['title'] }}</h3>
              <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ $step['description'] }}</p>
            </div>
          </article>
        @endforeach
      </div>
    </section>

    <section id="riwayat" class="mx-auto grid w-full max-w-[1600px] gap-4 px-4 py-6 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:px-10" aria-label="Ringkasan peminjaman">
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

    <section class="mx-auto w-full max-w-[1600px] px-4 pb-7 sm:px-8 lg:px-10" aria-labelledby="categories-heading">
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

    <section id="katalog" class="mx-auto w-full max-w-[1600px] px-4 pb-7 sm:px-8 lg:px-10" aria-labelledby="featured-heading">
      <div class="mb-3 flex flex-col gap-1 px-1 sm:flex-row sm:items-center sm:justify-between">
        <h2 id="featured-heading" class="text-lg font-bold">Sering dipinjam</h2>
      </div>
      @if ($featuredItems->isNotEmpty())
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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

  </main>
  @elseif ($page === 'catalog')
    @include('pages.user.katalog')
  @elseif ($page === 'apply')
    <main class="mx-auto min-h-[calc(100vh-5rem)] max-w-[1600px] pb-12">
      <x-user.hero-section
        title="Ajukan Peminjaman"
        description="Periksa barang di keranjang, lalu lengkapi detail pengajuan."
      />
      <section class="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-8 sm:py-6 lg:grid-cols-[1.15fr_0.85fr]">
        <div class="h-fit rounded-xl border border-[#d1e6e1] bg-white p-4 shadow-[0_2px_5px_rgba(20,55,48,0.12)] sm:p-5">
          <h2 class="border-b border-slate-200 pb-3 text-base font-bold text-slate-900">Barang yang dipinjam <span class="font-normal text-slate-500" x-text="`(${selectedCartItems.length} jenis)`"></span></h2>
          <div x-show="selectedCartItems.length === 0" class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Pilih barang dari keranjang untuk diajukan.</div>
          <template x-for="item in selectedCartItems" :key="item.id">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 py-3">
              <div class="min-w-0 flex-1">
                <h3 class="truncate text-sm font-semibold text-slate-800" x-text="item.name"></h3>
                <p class="mt-0.5 text-[10px] text-slate-500"><span x-text="item.code"></span> · <span x-text="item.category"></span> · stok <span x-text="item.stock"></span></p>
              </div>
              <div class="flex items-center gap-1.5">
                <button type="button" @click="updateQuantity(item, item.quantity - 1)" class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 transition hover:bg-slate-50" aria-label="Kurangi jumlah">−</button>
                <input type="number" min="1" :max="item.stock" :value="item.quantity" @change="updateQuantity(item, $event.target.value)" :aria-label="`Jumlah ${item.name}`" class="h-7 w-12 rounded border border-slate-200 px-1 text-center text-xs">
                <button type="button" @click="updateQuantity(item, item.quantity + 1)" :disabled="item.quantity >= item.stock" class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Tambah jumlah">+</button>
                <button type="button" @click="removeFromCart(item.id)" class="inline-flex items-center gap-1 rounded-md bg-rose-600 px-2 py-1.5 text-[10px] font-semibold text-white transition hover:bg-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">
                  <iconify-icon icon="mdi:trash-can-outline" aria-hidden="true"></iconify-icon>
                  Hapus
                </button>
              </div>
            </div>
          </template>
          <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="mt-3 inline-flex rounded-full border border-[#00a88f] px-3 py-1.5 text-[10px] font-semibold text-[#007f70] transition hover:bg-emerald-50">+ Tambah barang lain</a>
        </div>
        <form method="POST" action="{{ route('user.peminjaman.store') }}" class="rounded-xl border border-[#d1e6e1] bg-white p-4 shadow-[0_2px_5px_rgba(20,55,48,0.12)] sm:p-5">
          @csrf
          <input type="hidden" name="preserve_cart" value="1">
          <input type="hidden" name="clear_submitted_items" value="1">
          <h2 class="mb-4 border-b border-slate-200 pb-3 text-base font-bold text-slate-900">Ringkasan Pengajuan</h2>
          @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800" role="alert">
              <p class="font-semibold">Pengajuan belum dapat diproses:</p>
              <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          <template x-for="(item, index) in selectedCartItems" :key="item.id">
            <span>
              <input type="hidden" :name="`items[${index}][barang_id]`" :value="item.id">
              <input type="hidden" :name="`items[${index}][quantity]`" :value="item.quantity">
            </span>
          </template>
          <label class="mb-3 block text-xs font-semibold text-slate-700">Keperluan
            <input name="keperluan" value="{{ old('keperluan') }}" maxlength="255" required placeholder="Jelaskan keperluan peminjaman" class="mt-1 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2 text-sm font-normal outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
          </label>
          <label class="mb-3 block text-xs font-semibold text-slate-700">Jenis Peminjaman
            <select name="jenis_peminjaman" class="mt-1 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2 text-sm font-normal outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
              <option value="harian" @selected(old('jenis_peminjaman', 'harian') === 'harian')>Harian</option>
              <option value="praktik" @selected(old('jenis_peminjaman') === 'praktik')>Praktik</option>
              <option value="lainnya" @selected(old('jenis_peminjaman') === 'lainnya')>Lainnya</option>
            </select>
          </label>
          <label class="mb-4 block text-xs font-semibold text-slate-700">Tanggal Rencana Kembali
            <input type="date" name="tanggal_rencana_kembali" value="{{ old('tanggal_rencana_kembali') }}" min="{{ now()->toDateString() }}" required class="mt-1 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2 text-sm font-normal outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
          </label>
          <div class="mt-5 border-t border-slate-200 pt-3">
            <div class="mb-3 flex items-center justify-between gap-3 font-semibold text-slate-900"><span class="text-sm">Total jenis barang</span><span class="text-sm" x-text="`${selectedCartItems.length} jenis`"></span></div>
            <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[11px] leading-relaxed text-amber-950"><strong>Pengembalian dilakukan sekaligus, bukan sebagian. Jika barang kembali setengah akan ada konsekuensi dari SARPRAS.</strong></p>
            <button type="submit" :disabled="selectedCartItems.length === 0" class="w-full rounded-lg bg-[#00d69f] px-4 py-2.5 text-sm font-bold text-[#004d43] shadow-[0_5px_14px_rgba(0,190,144,0.2)] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">Ajukan Peminjaman</button>
          </div>
        </form>
      </section>
    </main>
  @else
    @include('pages.user.riwayat')
  @endif
</x-layout.user>
