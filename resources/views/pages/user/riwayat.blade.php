@php
  $currentUser = auth()->user();
  $userId = $currentUser?->akun_id;
  $historyLoans = $userId
      ? \App\Models\Peminjaman::query()
          ->with(['detailPeminjamans.barang', 'diprosesAkun'])
          ->where('akun_id', $userId)
          ->when(request()->query('status') === 'active', fn ($query) => $query->whereHas('detailPeminjamans', fn ($details) => $details->whereIn('status', ['pending', 'dipinjam'])))
          ->when(request()->query('status') === 'finished', fn ($query) => $query->whereHas('detailPeminjamans', fn ($details) => $details->whereIn('status', ['dikembalikan', 'ditolak', 'dibatalkan'])))
          ->latest('tanggal_pengajuan')
          ->paginate(8, ['*'], 'history_page')
          ->withQueryString()
      : collect();
@endphp
    <main id="history-content" class="mx-auto min-h-[calc(100vh-5rem)] max-w-[1600px] pb-12">
      <x-user.hero-section
        title="Riwayat Peminjaman Saya"
        description="Pantau status pengajuan aktif dan transaksi yang sudah selesai."
      />
      @if (session('success'))
        <div class="mx-4 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 sm:mx-8 lg:mx-10" role="status">{{ session('success') }}</div>
      @endif
      @if ($errors->any())
        <div class="mx-4 mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 sm:mx-8 lg:mx-10" role="alert">
          @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif
      <section class="mx-auto max-w-6xl px-4 py-5 sm:px-8 sm:py-6 lg:px-10">
        <div class="mb-5 flex flex-wrap items-center gap-2" aria-label="Filter riwayat peminjaman">
          <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'active']) }}"
            @if (request('status', 'active') === 'active') aria-current="page" @endif
            class="rounded-full border px-4 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 {{ request('status', 'active') === 'active' ? 'border-[#00ae91] bg-[#00ae91] text-white' : 'border-[#00ae91] bg-white text-[#007f70] hover:bg-emerald-50' }}">Aktif</a>
          <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'finished']) }}"
            @if (request('status') === 'finished') aria-current="page" @endif
            class="rounded-full border px-4 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 {{ request('status') === 'finished' ? 'border-[#00ae91] bg-[#00ae91] text-white' : 'border-[#00ae91] bg-white text-[#007f70] hover:bg-emerald-50' }}">Selesai</a>
        </div>
        @if ($historyLoans->count() > 0)
          <div class="space-y-4">
            @foreach ($historyLoans as $loan)
              @php
                $detailStatuses = $loan->detailPeminjamans->pluck('status')->unique();
                $loanStatus = $detailStatuses->count() === 1 ? $detailStatuses->first() : 'diproses';
                $cancellationRequested = $loan->hasCancellationRequest();
                $statusLabels = ['pending' => 'Menunggu konfirmasi', 'dipinjam' => 'Sedang dipinjam', 'dikembalikan' => 'Dikembalikan', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan', 'diproses' => 'Beberapa status'];
                $progressIndex = match ($loanStatus) {
                    'dipinjam' => 2,
                    'dikembalikan' => 3,
                    default => 0,
                };
                $isOverdue = $loanStatus === 'dipinjam'
                    && $loan->tanggal_rencana_kembali?->isBefore(today()) === true;
                $statusStyle = match (true) {
                    $cancellationRequested => 'bg-amber-100 text-amber-800',
                    $isOverdue => 'bg-amber-100 text-amber-800',
                    $loanStatus === 'pending' => 'bg-sky-100 text-sky-800',
                    $loanStatus === 'dipinjam' => 'bg-emerald-100 text-emerald-800',
                    $loanStatus === 'dikembalikan' => 'bg-slate-100 text-slate-700',
                    $loanStatus === 'ditolak', $loanStatus === 'dibatalkan' => 'bg-rose-100 text-rose-800',
                    default => 'bg-slate-100 text-slate-700',
                };
                $progressSteps = ['Diajukan', 'Dikonfirmasi', 'Dipinjam', 'Dikembalikan'];
              @endphp
              <article data-loan-state="{{ $loan->peminjaman_id }}:{{ $loanStatus }}:{{ $loan->detailPeminjamans->pluck('status')->implode(',') }}:{{ $cancellationRequested ? '1' : '0' }}:{{ $isOverdue ? '1' : '0' }}" x-data="{ qrOpen: false, cancellationOpen: false }" @keydown.escape.window="qrOpen = false; cancellationOpen = false" class="user-card-enter overflow-hidden rounded-xl border border-[#d1e6e1] bg-white shadow-[0_2px_5px_rgba(20,55,48,0.12)]" style="animation-delay: {{ $loop->index * 70 }}ms">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3.5 sm:px-5">
                  <div class="min-w-0">
                    <h2 class="text-sm font-bold tracking-wide text-slate-900 sm:text-base">Pesanan peminjaman</h2>
                    <p class="mt-1 text-[11px] text-slate-500">Diajukan {{ $loan->tanggal_pengajuan?->format('d M Y') ?? '—' }}</p>
                  </div>
                  <span class="shrink-0 rounded-full px-3 py-1 text-[10px] font-semibold {{ $statusStyle }}">
                    {{ $cancellationRequested ? 'Menunggu keputusan pembatalan' : ($isOverdue ? 'Jatuh tempo' : ($statusLabels[$loanStatus] ?? ucfirst($loanStatus))) }}
                  </span>
                </div>
                <div class="divide-y divide-slate-100 px-4 sm:px-5">
                  @foreach ($loan->detailPeminjamans as $detail)
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3">
                      <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-800">{{ $detail->barang?->nama_barang ?? 'Barang tidak tersedia' }} <span class="font-normal text-slate-500">× {{ $detail->jumlah_pinjam }}</span></p>
                        <p class="mt-1 text-[10px] text-slate-500">Pengembalian sesuai tanggal transaksi: {{ $loan->tanggal_rencana_kembali?->format('d M Y') ?? '—' }}</p>
                      </div>
                    </div>
                  @endforeach
                </div>
                <div class="px-4 pb-3 sm:px-5">
                  <ol class="grid grid-cols-4" aria-label="Tahapan peminjaman">
                    @foreach ($progressSteps as $stepIndex => $stepLabel)
                      <li class="relative flex min-w-0 flex-col items-center text-center">
                        @if ($stepIndex > 0)
                          <span class="absolute left-0 right-1/2 top-[11px] h-0.5 {{ $stepIndex <= $progressIndex ? 'bg-[#00c99c]' : 'bg-slate-200' }}" aria-hidden="true"></span>
                        @endif
                        @if ($stepIndex < count($progressSteps) - 1)
                          <span class="absolute left-1/2 right-0 top-[11px] h-0.5 {{ $stepIndex < $progressIndex ? 'bg-[#00c99c]' : 'bg-slate-200' }}" aria-hidden="true"></span>
                        @endif
                        <span class="relative z-10 flex h-6 w-6 items-center justify-center rounded-full {{ $stepIndex <= $progressIndex ? 'bg-[#00c99c] text-white' : 'bg-slate-200 text-slate-400' }}">
                          @if ($stepIndex < $progressIndex)
                            <span aria-hidden="true">✓</span>
                            <span class="sr-only">Selesai: </span>
                          @elseif ($stepIndex === $progressIndex)
                            <span class="h-2 w-2 rounded-full bg-white" aria-hidden="true"></span>
                            <span class="sr-only">Tahap saat ini: </span>
                          @else
                            <span class="h-1.5 w-1.5 rounded-full bg-white" aria-hidden="true"></span>
                            <span class="sr-only">Belum: </span>
                          @endif
                        </span>
                        <span class="mt-1.5 max-w-full px-0.5 text-[8px] leading-tight {{ $stepIndex <= $progressIndex ? 'font-semibold text-[#007f70]' : 'text-slate-400' }} sm:text-[10px]">{{ $stepLabel }}</span>
                      </li>
                    @endforeach
                  </ol>
                </div>
                <p class="mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-center text-[10px] leading-relaxed text-amber-950 sm:mx-5"><strong>Pengembalian dilakukan sekaligus, bukan sebagian. Jika barang kembali setengah akan ada konsekuensi dari SARPRAS.</strong></p>
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 bg-[#fbfefd] px-4 py-3 sm:px-5">
                  <p class="min-w-0 text-[10px] text-slate-500"><span class="font-semibold text-slate-600">Keperluan:</span> {{ $loan->keperluan ?: '—' }}</p>
                  @if ($loanStatus === 'pending')
                    <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                      <button type="button" @click="qrOpen = true" :aria-expanded="qrOpen.toString()"
                        class="rounded-md bg-[#00c99c] px-3 py-1.5 text-[10px] font-semibold text-[#004c43] transition hover:bg-[#00b68d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">
                        Tampilkan QR
                      </button>
                      @unless ($cancellationRequested)
                        <button type="button" @click="cancellationOpen = true" class="rounded-md border border-amber-300 px-3 py-1.5 text-[10px] font-semibold text-amber-800 transition hover:bg-amber-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2">Ajukan pembatalan</button>
                      @endunless
                    </div>
                  @endif
                </div>
                @if ($loanStatus === 'pending')
                  <template x-teleport="body">
                    <div x-cloak x-show="qrOpen" x-transition.opacity @click.self="qrOpen = false" class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 sm:p-6" role="presentation">
                      <section x-show="qrOpen" x-transition class="my-auto max-h-[calc(100dvh-2rem)] w-full max-w-sm overflow-y-auto rounded-2xl border border-[#d1e6e1] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.35)]" role="dialog" aria-modal="true" aria-labelledby="loan-qr-title-{{ $loan->peminjaman_id }}">
                        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                          <h2 id="loan-qr-title-{{ $loan->peminjaman_id }}" class="text-sm font-semibold text-slate-900">QR Peminjaman</h2>
                          <button type="button" @click="qrOpen = false" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a]" aria-label="Tutup QR">
                            <iconify-icon icon="mdi:close" class="text-lg" aria-hidden="true"></iconify-icon>
                          </button>
                        </header>
                        <div class="flex flex-col items-center px-5 pb-5 pt-6 text-center">
                          <div class="flex h-44 w-44 items-center justify-center rounded-xl border border-slate-200 bg-white p-2" data-loan-qr="{{ $loan->kode_peminjaman }}" role="img" aria-label="QR untuk pesanan peminjaman">
                            <span class="text-center text-xs text-slate-500">Menyiapkan QR...</span>
                          </div>
                          <p class="mt-2 text-[10px] font-medium tracking-wide text-slate-500">{{ $loan->kode_peminjaman }}</p>
                          <div class="mt-2 space-y-1">
                            @foreach ($loan->detailPeminjamans as $detail)
                              <p class="text-sm font-semibold text-slate-900">{{ $detail->barang?->nama_barang ?? 'Barang tidak tersedia' }} × {{ $detail->jumlah_pinjam }}</p>
                            @endforeach
                          </div>
                          <p class="mt-4 w-full rounded-full bg-sky-100 px-4 py-2.5 text-[11px] font-semibold leading-relaxed text-sky-900">Tunjukkan QR ini ke Admin/Laboran untuk dipindai.</p>
                          <button type="button" @click="qrOpen = false" class="mt-4 self-end rounded-lg border border-[#92d9cd] px-4 py-2 text-xs font-semibold text-[#007f70] transition hover:bg-[#e6f8f3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a]">Tutup</button>
                        </div>
                      </section>
                    </div>
                  </template>
                @endif
                @if ($loanStatus === 'pending' && ! $cancellationRequested)
                  <template x-teleport="body">
                    <div x-cloak x-show="cancellationOpen" x-transition.opacity @click.self="cancellationOpen = false" class="fixed inset-0 z-[120] flex items-center justify-center overflow-y-auto bg-slate-950/55 p-4 sm:p-6" role="presentation">
                      <section x-show="cancellationOpen" x-transition class="my-auto w-full max-w-md overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.35)]" role="alertdialog" aria-modal="true" aria-labelledby="cancellation-title-{{ $loan->peminjaman_id }}" aria-describedby="cancellation-description-{{ $loan->peminjaman_id }}">
                        <div class="h-1.5 bg-gradient-to-r from-amber-400 via-orange-400 to-rose-400" aria-hidden="true"></div>
                        <header class="flex items-start justify-between gap-4 px-5 pt-5 sm:px-6">
                          <div class="flex items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                              <iconify-icon icon="mdi:calendar-remove-outline" class="text-2xl" aria-hidden="true"></iconify-icon>
                            </span>
                            <div>
                              <h2 id="cancellation-title-{{ $loan->peminjaman_id }}" class="text-base font-bold text-slate-900">Ajukan pembatalan?</h2>
                              <p class="mt-1 text-xs leading-relaxed text-slate-500">Permintaan ini akan dikirim ke Admin untuk ditinjau.</p>
                            </div>
                          </div>
                          <button type="button" @click="cancellationOpen = false" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Tutup konfirmasi pembatalan">
                            <iconify-icon icon="mdi:close" class="text-lg" aria-hidden="true"></iconify-icon>
                          </button>
                        </header>
                        <div class="px-5 pb-5 pt-4 sm:px-6">
                          <div id="cancellation-description-{{ $loan->peminjaman_id }}" class="rounded-xl border border-amber-100 bg-amber-50 p-3.5">
                            <p class="text-xs leading-relaxed text-amber-950">Apakah kamu yakin ingin mengajukan pembatalan pesanan ini? Pesanan tidak akan dibatalkan sebelum Admin menyetujui permintaan.</p>
                          </div>
                          <div class="mt-5 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                            <button type="button" @click="cancellationOpen = false" class="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Kembali</button>
                            <form method="POST" action="{{ route('user.peminjaman.cancellation.store', $loan->peminjaman_id) }}" class="w-full sm:w-auto">
                              @csrf
                              <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-amber-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 sm:w-auto">Ya, ajukan pembatalan</button>
                            </form>
                          </div>
                        </div>
                      </section>
                    </div>
                  </template>
                @endif
              </article>
            @endforeach
          </div>
          @if ($historyLoans instanceof \Illuminate\Contracts\Pagination\Paginator || $historyLoans instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <nav class="mt-5" aria-label="Navigasi halaman riwayat">{{ $historyLoans->links() }}</nav>
          @endif
        @else
          <div class="rounded-xl border border-[#d1e6e1] bg-white px-6 py-10 text-center shadow-sm">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#e6f8f3] text-[#008b7a]" aria-hidden="true">
              <iconify-icon icon="mdi:clipboard-text-clock-outline" width="25"></iconify-icon>
            </span>
            <h2 class="mt-3 text-sm font-semibold text-slate-800">Belum ada transaksi di sini</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $userId ? 'Pengajuan pada kategori ini akan muncul di halaman ini.' : 'Masuk untuk melihat riwayat peminjaman akun Anda.' }}</p>
            @if ($userId && request('status', 'active') === 'active')
              <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="mt-4 inline-flex rounded-md bg-[#00c99c] px-4 py-2 text-xs font-semibold text-[#004c43] transition hover:bg-[#00b68d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">Lihat katalog barang</a>
            @endif
          </div>
        @endif
      </section>
    </main>
