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
    <main class="mx-auto min-h-[calc(100vh-5rem)] max-w-[1600px] pb-12">
      <section class="bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-5 py-9 text-center text-white sm:py-12">
        <h1 class="text-2xl font-bold sm:text-3xl">Riwayat Peminjaman Saya</h1>
        <p class="mt-2 text-sm text-white/90">Pantau status pengajuan yang sedang aktif dan yang sudah selesai.</p>
      </section>
      <section class="px-4 py-5 sm:px-8 lg:px-10">
        <div class="mb-4 flex gap-2">
          <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'active']) }}" class="rounded-full border border-[#00a88f] px-4 py-2 text-xs font-semibold {{ request('status', 'active') === 'active' ? 'bg-[#00a88f] text-white' : 'bg-white text-[#007f70]' }}">Aktif</a>
          <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'finished']) }}" class="rounded-full border border-[#00a88f] px-4 py-2 text-xs font-semibold {{ request('status') === 'finished' ? 'bg-[#00a88f] text-white' : 'bg-white text-[#007f70]' }}">Selesai</a>
        </div>
        @if (count($historyLoans) > 0)
          <div class="space-y-4">
            @foreach ($historyLoans as $loan)
              @php
                $detailStatuses = $loan->detailPeminjamans->pluck('status')->unique();
                $loanStatus = $detailStatuses->count() === 1 ? $detailStatuses->first() : 'diproses';
                $statusLabels = ['pending' => 'Menunggu konfirmasi', 'dipinjam' => 'Sedang dipinjam', 'dikembalikan' => 'Dikembalikan', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan', 'diproses' => 'Diproses'];
              @endphp
              <article class="user-card-enter rounded-xl border border-slate-200 bg-white p-4 shadow-sm" style="animation-delay: {{ $loop->index * 70 }}ms">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                  <div><h2 class="font-bold text-slate-900">{{ $loan->kode_peminjaman }}</h2><p class="mt-1 text-xs text-slate-500">Diajukan {{ $loan->tanggal_pengajuan?->format('d M Y') ?? '—' }}</p></div>
                  <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">{{ $statusLabels[$loanStatus] ?? ucfirst($loanStatus) }}</span>
                </div>
                <div class="mt-3 space-y-2">
                  @foreach ($loan->detailPeminjamans as $detail)
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm"><span class="font-medium text-slate-800">{{ $detail->barang?->nama_barang ?? 'Barang dihapus' }} × {{ $detail->jumlah_pinjam }}</span><span class="text-xs text-slate-500">{{ $statusLabels[$detail->status] ?? ucfirst($detail->status) }}</span></div>
                  @endforeach
                </div>
                <div class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-slate-500"><span>Keperluan: {{ $loan->keperluan ?: '—' }}</span><span>Rencana kembali: {{ $loan->tanggal_rencana_kembali?->format('d M Y') ?? '—' }}</span></div>
              </article>
            @endforeach
          </div>
          @if ($historyLoans instanceof \Illuminate\Contracts\Pagination\Paginator || $historyLoans instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="mt-5">{{ $historyLoans->links() }}</div>
          @endif
        @else
          <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">{{ $userId ? 'Belum ada riwayat pada kategori ini.' : 'Masuk untuk melihat riwayat peminjaman akun Anda.' }}</p>
        @endif
      </section>
    </main>
