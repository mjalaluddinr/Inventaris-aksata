@if ($isPrintPreview)
  <!DOCTYPE html>
  <html lang="id">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
    <title>Pratinjau Laporan Peminjaman {{ $year }}</title>
    @fonts('poppins')
    @vite('resources/css/app.css')
    <style>
      @page {
        size: A4 portrait;
        margin: 0;
      }

      .loan-print-page {
        width: 100%;
        min-height: 297mm;
      }

      @media print {

        html,
        body {
          width: 210mm;
          min-height: 297mm;
          background: #fff !important;
        }

        .loan-print-toolbar {
          display: none !important;
        }

        .loan-print-stage {
          display: block !important;
          min-height: 0 !important;
          padding: 0 !important;
          background: #fff !important;
        }

        .loan-print-page {
          width: 210mm !important;
          min-height: 297mm !important;
          margin: 0 !important;
          padding: 14mm 16mm !important;
          border: 0 !important;
          border-radius: 0 !important;
          box-shadow: none !important;
          break-after: avoid;
        }

        .loan-print-chart,
        .loan-print-chart * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
      }
    </style>
  </head>

  <body class="min-h-screen bg-[#dff4ea] font-sans text-slate-800">
    <header
      class="loan-print-toolbar sticky top-0 z-10 flex min-h-16 items-center justify-between gap-3 border-b border-[#006b5d] bg-[#007f70] px-4 text-white shadow-md sm:px-6">
      <div class="flex min-w-0 items-center gap-3">
        <a href="{{ $backUrl }}"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 transition hover:bg-white/20"
          aria-label="Kembali ke peminjaman">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m15 18-6-6 6-6M9 12h11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
              stroke-linejoin="round"></path>
          </svg>
        </a>
        <div class="min-w-0">
          <h1 class="truncate text-sm font-bold sm:text-base">Pratinjau Laporan</h1>
          <p class="truncate text-xs text-emerald-100">Peminjaman · Tahun {{ $year }}</p>
        </div>
      </div>

      <button type="button" onclick="window.print()"
        class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#007466] shadow-sm transition hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7v-7Z"
            stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"></path>
          <path d="M17 11h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"></path>
        </svg>
        <span>Cetak / Simpan PDF</span>
      </button>
    </header>

    @php
      $yearTotal = $monthlyLoans->sum('total');
      $highestMonthlyTotal = $monthlyLoans->max('total');
      $highestMonth = $monthlyLoans->firstWhere('total', $highestMonthlyTotal);
    @endphp

    <main class="loan-print-stage min-h-[calc(100vh-4rem)] w-full bg-white">
      <article class="loan-print-page mx-auto bg-white px-5 py-7 sm:px-8 sm:py-9 lg:px-12 lg:py-10">
        <header class="border-b-2 border-[#008b7a] pb-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold tracking-[0.2em] text-[#008b7a]">INVENTRACK <span
                  class="font-medium tracking-normal text-slate-400">/ LAPORAN</span></p>
              <h2 class="mt-2 text-2xl font-bold tracking-tight text-[#064e3b] sm:text-3xl">Laporan Peminjaman</h2>
              <p class="mt-1 text-sm text-slate-500">Ringkasan aktivitas peminjaman sepanjang tahun</p>
            </div>
            <div class="shrink-0 rounded-xl border border-[#cce8df] bg-[#f0faf6] px-4 py-3 text-right">
              <p class="text-[11px] font-semibold text-[#52776d]">TAHUN</p>
              <p class="mt-0.5 text-xl font-bold text-[#007f70]">{{ $year }}</p>
            </div>
          </div>
        </header>

        <section class="mt-5 grid grid-cols-3 gap-3" aria-label="Ringkasan tahunan">
          <div class="rounded-xl border border-[#d9ebe5] bg-[#f7fcfa] px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#69847c]">Total peminjaman</p>
            <p class="mt-1 text-2xl font-bold text-[#086b5d]">{{ number_format($yearTotal, 0, ',', '.') }}</p>
            <p class="text-[10px] text-slate-500">pengajuan pada {{ $year }}</p>
          </div>
          <div class="rounded-xl border border-[#d9ebe5] bg-[#f7fcfa] px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#69847c]">Rata-rata bulanan</p>
            <p class="mt-1 text-2xl font-bold text-[#086b5d]">{{ number_format($yearTotal / 12, 1, ',', '.') }}</p>
            <p class="text-[10px] text-slate-500">pengajuan per bulan</p>
          </div>
          <div class="rounded-xl border border-[#d9ebe5] bg-[#f7fcfa] px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#69847c]">Bulan tertinggi</p>
            <p class="mt-1 truncate text-xl font-bold text-[#086b5d]">{{ $highestMonth['label'] ?? '-' }}</p>
            <p class="text-[10px] text-slate-500">{{ number_format($highestMonthlyTotal, 0, ',', '.') }} pengajuan</p>
          </div>
        </section>

        <section class="mt-6" aria-label="Grafik peminjaman bulanan">
          <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
              <h3 class="text-base font-bold text-slate-800">Tren peminjaman bulanan</h3>
              <p class="mt-1 text-xs text-slate-500">Jumlah pengajuan per bulan selama {{ $year }}</p>
            </div>
            <div class="flex items-center gap-2 text-[11px] font-semibold text-[#315d56]">
              <span class="h-3 w-2 rounded-sm bg-[#008b7a]"></span>
              Peminjaman
            </div>
          </div>
          <x-admin.grafik :monthly-loans="$monthlyLoans" :year="$year" chart-id="print-loan-report-chart"
            class="loan-print-chart mt-3 rounded-xl border border-[#d9ebe5] bg-[#f7fcfa] px-2 py-3 sm:px-4" />
        </section>

        <section class="mt-5" aria-label="Rangkuman per bulan">
          <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-800">Rangkuman per bulan</h3>
            <span class="text-[10px] font-medium text-slate-500">Total pengajuan</span>
          </div>
          <div class="grid grid-cols-2 gap-x-6 sm:grid-cols-3">
            @foreach ($monthlyLoans as $month)
              <div class="flex items-center justify-between border-b border-[#e5efeb] py-1.5 text-xs">
                <span class="text-slate-600">{{ $month['label'] }}</span>
                <span class="font-bold text-[#14594e]">{{ number_format($month['total'], 0, ',', '.') }}</span>
              </div>
            @endforeach
          </div>
        </section>

        <footer
          class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-[#dcebe6] pt-3 text-[10px] text-slate-500">
          <span>Laporan dibuat {{ now()->translatedFormat('d F Y') }}.</span>
          <span>Inventrack · Laporan Peminjaman {{ $year }}</span>
        </footer>
      </article>
    </main>
  </body>

  </html>
@else
  <x-layout.admin title="Peminjaman - InvenTrack" header-title="Peminjaman"
    header-description="Konfirmasi, scan QR, dan proses pengembalian.">
    <div class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-10rem)] bg-[#E8F8F4] px-4 pb-8 pt-5 sm:px-6 lg:px-8"
      x-data="loanQrScanner" data-endpoint="{{ route('admin.peminjaman.scan', [], false) }}"
      data-csrf-token="{{ csrf_token() }}" data-feedback-message="{{ session('success', '') }}"
      @click="handleLoanNavigation($event)">
      <div class="mx-auto w-full min-w-0">
        @if ($errors->any())
          <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <p class="font-semibold">Peminjaman belum dapat diproses.</p>
            <ul class="mt-1 list-inside list-disc">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div x-ref="loanList" :aria-busy="loanListLoading" :class="loanListLoading ? 'opacity-60' : ''">
          @fragment('loan-list')
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
              <nav class="flex flex-wrap gap-2" aria-label="Status peminjaman">
                @foreach ([
          'pending' => 'Menunggu Konfirmasi',
          'borrowed' => 'Dipinjam',
          'history' => 'Riwayat Selesai',
      ] as $tab => $label)
                  <a href="{{ route('admin.peminjaman', ['tab' => $tab, 'year' => $year]) }}"
                    @if ($activeTab === $tab) aria-current="page" @endif
                    class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-semibold shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a] sm:text-sm {{ $activeTab === $tab ? 'border-[#00c99c] bg-[#00d69f] text-[#003f38]' : 'border-[#9ccfc4] bg-white text-[#153c36] hover:bg-[#effbf7]' }}">
                    {{ $label }}
                  </a>
                @endforeach
              </nav>

              <button type="button" @click="openScanner()"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#00d69f] px-4 py-2.5 text-sm font-bold text-[#003f38] shadow-sm transition hover:bg-[#36e9b3] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a]">
                <iconify-icon icon="mdi:qrcode-scan" class="text-lg" aria-hidden="true"></iconify-icon>
                Scan QR
              </button>
            </div>

            <section class="overflow-hidden rounded-xl border border-[#b8d4cd] bg-white shadow-sm"
              aria-label="{{ $activeTab === 'pending' ? 'Peminjaman menunggu konfirmasi' : ($activeTab === 'borrowed' ? 'Barang yang sedang dipinjam' : 'Riwayat peminjaman selesai') }}">
              @if ($peminjamans->isNotEmpty())
                <div class="overflow-x-auto">
                  <table class="w-full min-w-[1200px] table-auto divide-y divide-[#DCE9E6] text-left text-xs">
                    <thead class="bg-[#f3f5f4] text-[#124b45]">
                      <tr>
                        <th scope="col" class="w-[13%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Kode</th>
                        <th scope="col" class="w-[14%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Peminjam
                        </th>
                        <th scope="col" class="w-[18%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Barang</th>
                        <th scope="col" class="w-[7%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Jumlah</th>
                        <th scope="col" class="w-[14%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Keperluan
                        </th>
                        @if ($activeTab === 'pending')
                          <th scope="col" class="w-[10%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Tanggal
                            Pengajuan</th>
                          <th scope="col" class="w-[10%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Rencana
                            Kembali</th>
                        @elseif ($activeTab === 'borrowed')
                          <th scope="col" class="w-[10%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Tanggal
                            Peminjaman</th>
                          <th scope="col" class="w-[10%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Rencana
                            Kembali</th>
                        @else
                          <th scope="col" class="w-[10%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Tanggal
                            Peminjaman</th>
                          <th scope="col" class="w-[10%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Tanggal
                            Dikembalikan</th>
                        @endif
                        <th scope="col" class="w-[14%] whitespace-nowrap px-3 py-3 font-semibold sm:px-4">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#dce7e4] text-slate-800">
                      @foreach ($peminjamans as $loan)
                        <tr class="align-top transition hover:bg-[#f8fcfb]">
                          <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                            <span class="font-medium">{{ $loan->kode_peminjaman }}</span>
                            @if ($loan->hasCancellationRequest() && $activeTab === 'pending')
                              <span
                                class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Minta
                                pembatalan</span>
                            @endif
                          </td>
                          <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                            <span
                              class="font-medium">{{ trim(($loan->akun?->first_name ?? '') . ' ' . ($loan->akun?->last_name ?? '')) ?: $loan->akun?->username ?? 'Pengguna' }}</span>
                          </td>
                          <td class="px-3 py-3 sm:px-4">
                            @foreach ($loan->detailPeminjamans as $detail)
                              <div class="mb-2 min-w-0">
                                <span
                                  class="block whitespace-nowrap">{{ $detail->barang?->nama_barang ?? 'Barang tidak tersedia' }}</span>
                              </div>
                            @endforeach
                          </td>
                          <td class="px-3 py-3 sm:px-4">
                            @foreach ($loan->detailPeminjamans as $detail)
                              <span
                                class="mb-1 block whitespace-nowrap">x{{ $activeTab === 'borrowed' ? $detail->jumlah_pinjam - $detail->jumlah_kembali : $detail->jumlah_pinjam }}</span>
                            @endforeach
                          </td>
                          <td class="whitespace-nowrap px-3 py-3 leading-relaxed sm:px-4">{{ $loan->keperluan ?: '—' }}
                          </td>
                          @if ($activeTab === 'pending')
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                              {{ $loan->tanggal_pengajuan?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                              {{ $loan->tanggal_rencana_kembali?->translatedFormat('d M Y') ?? '—' }}
                            </td>
                          @elseif ($activeTab === 'borrowed')
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                              {{ $loan->tanggal_pinjam?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                              {{ $loan->tanggal_rencana_kembali?->translatedFormat('d M Y') ?? '—' }}
                            </td>
                          @else
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                              {{ $loan->tanggal_pinjam?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                              {{ $loan->detailPeminjamans->pluck('tanggal_kembali')->filter()->max()?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>
                          @endif
                          <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                            @if ($activeTab === 'pending')
                              <div x-data="{ confirmOpen: false, rejectOpen: false }" class="flex items-center gap-1.5">
                                <button type="button" @click="confirmOpen = true"
                                  class="inline-flex items-center gap-1 rounded-md bg-[#00d69f] px-2.5 py-1.5 text-[10px] font-semibold text-[#003f38] transition hover:bg-[#36e9b3]">
                                  <iconify-icon icon="mdi:check" aria-hidden="true"></iconify-icon>
                                  Konfirmasi
                                </button>
                                <button type="button" @click="rejectOpen = true"
                                  class="inline-flex items-center gap-1 rounded-md bg-rose-600 px-2.5 py-1.5 text-[10px] font-semibold text-white transition hover:bg-rose-700">
                                  <iconify-icon icon="mdi:close" aria-hidden="true"></iconify-icon>
                                  Tolak
                                </button>
                                <template x-teleport="body">
                                  <div x-cloak x-show="confirmOpen" x-transition.opacity
                                    class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-slate-950/55 p-4"
                                    role="presentation" @click.self="confirmOpen = false"
                                    @keydown.escape.window="confirmOpen = false">
                                    <section x-show="confirmOpen" x-transition.opacity
                                      class="my-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
                                      role="dialog" aria-modal="true"
                                      aria-labelledby="confirm-title-{{ $loan->peminjaman_id }}">
                                      <header
                                        class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                                        <div>
                                          <h2 id="confirm-title-{{ $loan->peminjaman_id }}"
                                            class="font-bold text-slate-900">Konfirmasi Pengambilan Barang</h2>
                                          <p class="mt-1 text-xs text-slate-500">{{ $loan->kode_peminjaman }}</p>
                                        </div>
                                        <button type="button" @click="confirmOpen = false"
                                          class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a]"
                                          aria-label="Tutup konfirmasi">
                                          <iconify-icon icon="mdi:close" class="text-xl"
                                            aria-hidden="true"></iconify-icon>
                                        </button>
                                      </header>
                                      <form method="POST" action="{{ route('admin.peminjaman.confirm', $loan) }}"
                                        @submit.prevent="submitLoanAction($event)">
                                        @csrf
                                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                                        <div class="max-h-[55vh] space-y-3 overflow-y-auto p-5">
                                          @foreach ($loan->detailPeminjamans as $detail)
                                            @php
                                              $locations = $detail->barang?->lokasiBarangs ?? collect();
                                              $reservedByRoom = $detail->logMutasiBarangs
                                                  ->filter(
                                                      static fn($reservation): bool => $reservation->ruangan_id !==
                                                          null,
                                                  )
                                                  ->groupBy('ruangan_id')
                                                  ->map(
                                                      static fn($reservations): int => (int) $reservations->sum(
                                                          'jumlah',
                                                      ),
                                                  );
                                              $roomOptions = $locations
                                                  ->filter(static fn($location): bool => $location->ruangan !== null)
                                                  ->groupBy('ruangan_id')
                                                  ->map(
                                                      static fn($roomLocations, $roomId): array => [
                                                          'room' => $roomLocations->first()->ruangan,
                                                          'available' =>
                                                              (int) $roomLocations->sum('stok_tersedia') +
                                                              (int) ($reservedByRoom[$roomId] ?? 0),
                                                      ],
                                                  )
                                                  ->filter(static fn(array $room): bool => $room['available'] > 0)
                                                  ->sortBy(
                                                      static fn(array $room): string => $room['room']->nama_ruangan,
                                                      SORT_NATURAL | SORT_FLAG_CASE,
                                                  );
                                              $canChooseRoom =
                                                  $locations->isNotEmpty() &&
                                                  $detail->logMutasiBarangs->isNotEmpty() &&
                                                  $detail->logMutasiBarangs->every(
                                                      static fn($reservation): bool => $reservation->ruangan_id !==
                                                          null,
                                                  );
                                            @endphp
                                            <div class="rounded-xl border border-[#dce7e4] bg-[#fbfefd] p-3.5">
                                              <div class="flex items-start justify-between gap-3">
                                                <p class="text-sm font-bold text-slate-900">
                                                  {{ $detail->barang?->nama_barang ?? 'Barang tidak tersedia' }}</p>
                                                <span
                                                  class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">×{{ number_format($detail->jumlah_pinjam, 0, ',', '.') }}</span>
                                              </div>
                                              @if ($canChooseRoom && $roomOptions->isNotEmpty())
                                                <p class="mt-3 text-xs text-slate-600">Bagi jumlah pinjaman ke satu atau
                                                  beberapa ruangan.</p>
                                                <div class="mt-2 space-y-2">
                                                  @foreach ($roomOptions as $roomId => $roomOption)
                                                    @php
                                                      $allocationInputId =
                                                          'pickup-quantity-' .
                                                          $detail->detail_peminjaman_id .
                                                          '-' .
                                                          $roomId;
                                                      $allocationErrorKey =
                                                          'allocations.' .
                                                          $detail->detail_peminjaman_id .
                                                          '.' .
                                                          $roomId;
                                                      $selectedQuantity = old(
                                                          'allocations.' .
                                                              $detail->detail_peminjaman_id .
                                                              '.' .
                                                              $roomId,
                                                          $reservedByRoom[$roomId] ?? 0,
                                                      );
                                                    @endphp
                                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                                      <div class="flex items-center justify-between gap-3">
                                                        <label for="{{ $allocationInputId }}"
                                                          class="min-w-0 text-xs font-semibold text-slate-800">{{ $roomOption['room']->nama_ruangan }}</label>
                                                        <span class="shrink-0 text-[11px] text-slate-500">Maks. untuk
                                                          pinjaman
                                                          {{ number_format($roomOption['available'], 0, ',', '.') }}</span>
                                                      </div>
                                                      <input id="{{ $allocationInputId }}"
                                                        name="allocations[{{ $detail->detail_peminjaman_id }}][{{ $roomId }}]"
                                                        type="number" inputmode="numeric" min="0"
                                                        max="{{ $roomOption['available'] }}"
                                                        value="{{ $selectedQuantity }}" required
                                                        class="mt-2 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/25">
                                                      @if ($errors->has($allocationErrorKey))
                                                        <p class="mt-1 text-xs font-medium text-rose-600">
                                                          {{ $errors->first($allocationErrorKey) }}</p>
                                                      @endif
                                                    </div>
                                                  @endforeach
                                                </div>
                                                @if ($errors->has('allocations.' . $detail->detail_peminjaman_id))
                                                  <p class="mt-2 text-xs font-medium text-rose-600">
                                                    {{ $errors->first('allocations.' . $detail->detail_peminjaman_id) }}
                                                  </p>
                                                @endif
                                              @elseif ($canChooseRoom)
                                                <p class="mt-2 text-xs text-amber-700">Stok ruangan tidak dapat dipilih;
                                                  lokasi reservasi saat ini akan dipertahankan.</p>
                                                @foreach ($detail->logMutasiBarangs as $reservation)
                                                  <div
                                                    class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2.5 text-xs">
                                                    <p class="min-w-0 font-semibold text-slate-800">
                                                      {{ $reservation->ruangan?->nama_ruangan ?? 'Lokasi tidak tersedia' }}
                                                    </p>
                                                    <span
                                                      class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 font-bold text-emerald-800">×{{ number_format($reservation->jumlah, 0, ',', '.') }}</span>
                                                  </div>
                                                @endforeach
                                              @else
                                                @forelse ($detail->logMutasiBarangs as $reservation)
                                                  <div
                                                    class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2.5 text-xs">
                                                    <p class="min-w-0 font-semibold text-slate-800">
                                                      {{ $reservation->ruangan?->nama_ruangan ?? 'Lokasi tidak tersedia' }}
                                                      @if ($reservation->lemariAsal)
                                                        · {{ $reservation->lemariAsal->nama_lemari }}
                                                      @elseif ($reservation->reservationToolboxId())
                                                        · Toolbox {{ $reservation->reservationToolboxId() }}
                                                      @endif
                                                    </p>
                                                    <span
                                                      class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 font-bold text-emerald-800">×{{ number_format($reservation->jumlah, 0, ',', '.') }}</span>
                                                  </div>
                                                @empty
                                                  <p
                                                    class="mt-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-500">
                                                    Lokasi reservasi belum tercatat.</p>
                                                @endforelse
                                              @endif
                                            </div>
                                          @endforeach
                                        </div>
                                        <footer class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                                          <button type="button" @click="confirmOpen = false"
                                            class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a]">Batal</button>
                                          <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#00d69f] px-4 py-2 text-xs font-bold text-[#003f38] transition hover:bg-[#36e9b3] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a] disabled:cursor-wait disabled:opacity-70">
                                            <iconify-icon data-loan-action-spinner icon="mdi:loading"
                                              class="hidden animate-spin text-lg" aria-hidden="true"></iconify-icon>
                                            <iconify-icon data-loan-action-icon icon="mdi:check-circle-outline"
                                              aria-hidden="true"></iconify-icon>
                                            <span data-loan-action-label>Konfirmasi Peminjaman</span>
                                          </button>
                                        </footer>
                                      </form>
                                    </section>
                                  </div>
                                </template>
                                <template x-teleport="body">
                                  <div x-cloak x-show="rejectOpen" x-transition.opacity
                                    class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-slate-950/55 p-4"
                                    role="presentation" @click.self="rejectOpen = false"
                                    @keydown.escape.window="rejectOpen = false">
                                    <section x-show="rejectOpen" x-transition.opacity
                                      class="my-auto w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
                                      role="dialog" aria-modal="true"
                                      aria-labelledby="reject-title-{{ $loan->peminjaman_id }}">
                                      <header
                                        class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                                        <div>
                                          <h2 id="reject-title-{{ $loan->peminjaman_id }}"
                                            class="font-bold text-slate-900">Tolak Peminjaman</h2>
                                          <p class="mt-1 text-xs text-slate-500">{{ $loan->kode_peminjaman }}</p>
                                        </div>
                                        <button type="button" @click="rejectOpen = false"
                                          class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                                          aria-label="Tutup penolakan">
                                          <iconify-icon icon="mdi:close" class="text-xl"
                                            aria-hidden="true"></iconify-icon>
                                        </button>
                                      </header>
                                      <form method="POST" action="{{ route('admin.peminjaman.reject', $loan) }}"
                                        class="p-5" @submit.prevent="submitLoanAction($event)">
                                        @csrf
                                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                                        <label for="reject-reason-{{ $loan->peminjaman_id }}"
                                          class="block text-xs font-semibold text-slate-700">Alasan penolakan</label>
                                        <textarea id="reject-reason-{{ $loan->peminjaman_id }}" name="reason" required maxlength="255" rows="3"
                                          placeholder="Tuliskan alasan penolakan..."
                                          class="mt-2 block w-full resize-y rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 placeholder:text-slate-400 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"></textarea>
                                        <p class="mt-2 text-xs text-slate-500">Stok yang sudah dipesan akan dikembalikan.
                                        </p>
                                        <footer class="mt-5 flex justify-end gap-2">
                                          <button type="button" @click="rejectOpen = false"
                                            class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                                          <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700 disabled:cursor-wait disabled:opacity-70">
                                            <iconify-icon data-loan-action-spinner icon="mdi:loading"
                                              class="hidden animate-spin text-lg" aria-hidden="true"></iconify-icon>
                                            <iconify-icon data-loan-action-icon icon="mdi:close-circle-outline"
                                              aria-hidden="true"></iconify-icon>
                                            <span data-loan-action-label>Tolak Pengajuan</span>
                                          </button>
                                        </footer>
                                      </form>
                                    </section>
                                  </div>
                                </template>
                              </div>
                            @elseif ($activeTab === 'borrowed')
                              <div x-data="{ returnOpen: false }">
                                <button type="button" @click="returnOpen = true"
                                  class="whitespace-nowrap rounded-md border border-[#00a88f] px-2.5 py-1.5 text-left text-[10px] font-semibold text-[#007f70] transition hover:bg-[#e6f8f3]">Proses
                                  Pengembalian</button>
                                <template x-teleport="body">
                                  <div x-cloak x-show="returnOpen" x-transition.opacity
                                    class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-slate-950/55 p-4"
                                    role="presentation" @click.self="returnOpen = false"
                                    @keydown.escape.window="returnOpen = false">
                                    <section x-show="returnOpen" x-transition.opacity
                                      class="my-auto max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
                                      role="dialog" aria-modal="true"
                                      aria-labelledby="return-title-{{ $loan->peminjaman_id }}">
                                      <header
                                        class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                        <div>
                                          <h2 id="return-title-{{ $loan->peminjaman_id }}"
                                            class="font-bold text-slate-900">Proses Pengembalian</h2>
                                          <p class="mt-1 text-xs text-slate-500">{{ $loan->kode_peminjaman }}</p>
                                        </div>
                                        <button type="button" @click="returnOpen = false"
                                          class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                                          aria-label="Tutup pengembalian">
                                          <iconify-icon icon="mdi:close" class="text-xl"
                                            aria-hidden="true"></iconify-icon>
                                        </button>
                                      </header>
                                      <form method="POST" action="{{ route('admin.peminjaman.return', $loan) }}"
                                        class="p-5" @submit.prevent="submitLoanAction($event)">
                                        @csrf
                                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                                        <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
                                          Seluruh sisa barang pada pengajuan ini wajib dikembalikan sekaligus.
                                        </p>
                                        <div class="space-y-3">
                                          @foreach ($loan->detailPeminjamans as $detail)
                                            @php
                                              $remainingQuantity = $detail->jumlah_pinjam - $detail->jumlah_kembali;
                                            @endphp
                                            <div
                                              class="grid gap-3 border-b border-slate-100 pb-4 text-xs sm:grid-cols-[minmax(0,1fr)_7rem_10rem] sm:items-end">
                                              <div class="min-w-0">
                                                <p class="font-semibold text-slate-800">
                                                  {{ $detail->barang?->nama_barang ?? 'Barang tidak tersedia' }}</p>
                                                <p class="mt-1 text-[11px] text-slate-500">Sisa {{ $remainingQuantity }}
                                                  dari {{ $detail->jumlah_pinjam }} barang</p>
                                              </div>
                                              <div>
                                                <span class="block font-semibold text-slate-600">Jumlah kembali</span>
                                                <p
                                                  class="mt-1.5 rounded-lg bg-slate-50 px-2.5 py-2 text-xs font-bold text-slate-800">
                                                  {{ $remainingQuantity }}</p>
                                                <input type="hidden"
                                                  name="quantities[{{ $detail->detail_peminjaman_id }}]"
                                                  value="{{ $remainingQuantity }}">
                                              </div>
                                              <label class="block font-semibold text-slate-600">
                                                Kondisi
                                                <select name="conditions[{{ $detail->detail_peminjaman_id }}]" required
                                                  class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-normal text-slate-800 focus:border-[#00a88f] focus:outline-none focus:ring-2 focus:ring-[#00a88f]/20">
                                                  <option value="baik">Baik</option>
                                                  <option value="rusak_ringan">Rusak ringan</option>
                                                  <option value="rusak_berat">Rusak berat</option>
                                                  <option value="hilang">Hilang</option>
                                                </select>
                                              </label>
                                            </div>
                                          @endforeach
                                        </div>
                                        <label class="mt-4 block text-xs font-semibold text-slate-700">
                                          Catatan pengembalian
                                          <textarea name="note" maxlength="255" rows="2"
                                            class="mt-1.5 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-normal focus:border-[#00a88f] focus:outline-none focus:ring-2 focus:ring-[#00a88f]/20"></textarea>
                                        </label>
                                        <footer class="mt-5 flex justify-end gap-2">
                                          <button type="button" @click="returnOpen = false"
                                            class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                                          <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#00d69f] px-4 py-2 text-xs font-bold text-[#003f38] transition hover:bg-[#36e9b3] disabled:cursor-wait disabled:opacity-70">
                                            <iconify-icon data-loan-action-spinner icon="mdi:loading"
                                              class="hidden animate-spin text-lg" aria-hidden="true"></iconify-icon>
                                            <iconify-icon data-loan-action-icon icon="mdi:check-circle-outline"
                                              aria-hidden="true"></iconify-icon>
                                            <span data-loan-action-label>Simpan Pengembalian</span>
                                          </button>
                                        </footer>
                                      </form>
                                    </section>
                                  </div>
                                </template>
                              </div>
                            @else
                              @php
                                $loanStatuses = $loan->detailPeminjamans->pluck('status')->unique();
                                $statusLabel = $loanStatuses->count() === 1 ? $loanStatuses->first() : 'selesai';
                                $statusText =
                                    $statusLabel === 'dibatalkan' ? 'Peminjaman Dibatalkan' : ucfirst($statusLabel);
                              @endphp
                              <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $statusLabel === 'dikembalikan' ? 'bg-emerald-100 text-emerald-800' : (in_array($statusLabel, ['ditolak', 'dibatalkan'], true) ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ $statusText }}
                              </span>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @if ($peminjamans->hasPages())
                  <div class="border-t border-[#dce7e4] px-4 py-3">
                    <x-pagination :paginator="$peminjamans" label="Navigasi halaman peminjaman" />
                  </div>
                @endif
              @else
                <div class="px-6 py-12 text-center">
                  <iconify-icon icon="mdi:clipboard-text-clock-outline" class="text-4xl text-[#76a99e]"
                    aria-hidden="true"></iconify-icon>
                  @php
                    $emptyTabLabel = match ($activeTab) {
                        'pending' => 'Menunggu Konfirmasi',
                        'borrowed' => 'Dipinjam',
                        default => 'Riwayat Selesai',
                    };
                  @endphp
                  <p class="mt-2 text-sm font-bold text-slate-800">Belum ada data pada tab {{ $emptyTabLabel }} ini.</p>
                  <p class="mt-1 text-xs text-slate-500">Peminjaman akan muncul di sini saat statusnya berubah.</p>
                </div>
              @endif
            </section>
          @endfragment
        </div>
        <p x-cloak x-show="loanListError" x-text="loanListError"
          class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
        </p>

        <section id="loan-report" class="mt-7 rounded-xl border border-[#b8d4cd] bg-white p-4 shadow-sm sm:p-5"
          aria-labelledby="loan-report-title">
          <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 id="loan-report-title" class="text-base font-bold text-[#064e3b]">Laporan Peminjaman per Periode
              </h2>
              <p class="loan-report-print-year hidden">Periode laporan: {{ $year }}</p>
            </div>
            <form method="GET" action="{{ route('admin.peminjaman') }}" class="flex items-center gap-3">
              <input type="hidden" name="tab" value="{{ $activeTab }}">
              <label for="loan-report-year" class="sr-only">Pilih tahun laporan</label>
              <div class="relative">
                <select id="loan-report-year" name="year" onchange="this.form.submit()"
                  class="w-20 appearance-none [-webkit-appearance:none] bg-none rounded-lg border border-[#8cbeb3] bg-white px-2 py-1.5 pr-7 text-xs font-semibold text-slate-700 transition hover:border-[#008b7a] focus:border-[#008b7a] focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a]"
                  @disabled($reportYears === [])>
                  @foreach ($reportYears as $reportYear)
                    <option value="{{ $reportYear }}" @selected($year === $reportYear)>{{ $reportYear }}</option>
                  @endforeach
                </select>
                <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-600"
                  viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round"></path>
                </svg>
              </div>
            </form>
          </header>

          <x-admin.grafik :monthly-loans="$monthlyLoans" :year="$year" id="loan-report-chart"
            chart-id="admin-loan-report-chart"
            class="mt-5 rounded-xl border border-[#d9ebe5] bg-[#f7fcfa] px-2 py-4 sm:px-4" />
          <footer class="mt-4 flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.peminjaman.export', ['year' => $year]) }}"
              class="rounded-lg border border-[#00a88f] px-3 py-2 text-xs font-semibold text-[#007f70] transition hover:bg-[#e6f8f3]">Export
              Excel</a>
            <a href="{{ route('admin.peminjaman.print', ['year' => $year, 'tab' => $activeTab]) }}"
              class="rounded-lg border border-[#00a88f] px-3 py-2 text-xs font-semibold text-[#007f70] transition hover:bg-[#e6f8f3]">
              Export PDF
            </a>
          </footer>
        </section>
      </div>

      <template x-teleport="body">
        <div x-cloak x-show="scannerOpen" x-transition.opacity
          class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-4" role="presentation"
          @click.self="closeScanner()" @keydown.escape.window="closeScanner()">
          <section x-show="scannerOpen" x-transition.opacity
            class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true"
            aria-labelledby="loan-scanner-title">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <div>
                <h2 id="loan-scanner-title" class="text-lg font-bold text-slate-900">Scan QR Peminjaman</h2>
              </div>
              <button type="button" @click="closeScanner()"
                class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                aria-label="Tutup pemindai">
                <iconify-icon icon="mdi:close" class="text-xl" aria-hidden="true"></iconify-icon>
              </button>
            </header>
            <div class="space-y-4 p-5">
              <div class="relative mx-auto w-full max-w-[32rem] max-h-[55vh] overflow-hidden rounded-xl bg-slate-950"
                :style="`aspect-ratio: ${cameraAspectRatio}`">
                <video x-ref="cameraVideo" x-show="cameraReady" autoplay muted playsinline
                  class="h-full w-full object-contain" aria-label="Kamera untuk memindai QR"></video>
                <canvas x-ref="scanCanvas" class="hidden" aria-hidden="true"></canvas>
                <div x-show="!cameraReady"
                  class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-5 text-center text-white">
                  <iconify-icon icon="mdi:qrcode-scan" class="text-4xl text-emerald-300"
                    aria-hidden="true"></iconify-icon>
                  <p class="text-xs text-white/80"
                    x-text="scannerMessage || 'Kamera akan aktif setelah izin diberikan.'"></p>
                </div>
                <svg x-cloak x-show="qrFrame" class="pointer-events-none absolute inset-0 h-full w-full"
                  :viewBox="`0 0 ${qrFrame?.width ?? 1} ${qrFrame?.height ?? 1}`" aria-hidden="true">
                  <polygon :points="qrFrame?.points" fill="rgba(16, 185, 129, 0.12)" stroke="#6ee7b7"
                    stroke-width="8" stroke-linejoin="round" stroke-dasharray="24 12">
                    <animate attributeName="stroke-dashoffset" from="0" to="-36" dur="0.8s"
                      repeatCount="indefinite"></animate>
                  </polygon>
                </svg>
              </div>
              <p x-show="isScanning"
                class="rounded-lg bg-white px-4 py-2 text-center text-sm font-bold text-rose-600 shadow-sm">Arahkan
                Kamera Ke Kode QR</p>
              <p x-show="scannerMessage" x-text="scannerMessage"
                :class="scannerMessage === 'Masukkan kode dari QR peminjaman.' ?
                    'rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 font-bold text-rose-700' : 'text-slate-700'"
                class="text-sm" role="status"></p>
              <button x-show="cameraSupported && !isScanning && !isSubmitting" type="button" @click="startCamera()"
                class="w-full rounded-lg border border-[#00a88f] px-4 py-2.5 text-sm font-semibold text-[#007f70] hover:bg-[#e6f8f3]">Aktifkan
                kamera</button>
              <form @submit.prevent="submitCode()" class="border-t border-slate-100 pt-4">
                <label for="manual-loan-code" class="block text-xs font-semibold text-slate-700">Masukkan kode QR
                  secara manual</label>
                <div class="mt-2 flex gap-2">
                  <input id="manual-loan-code" x-model="manualCode" type="text" maxlength="30"
                    autocomplete="off" spellcheck="false"
                    class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase tracking-wide focus:border-[#00a88f] focus:outline-none focus:ring-2 focus:ring-[#00a88f]/20"
                    placeholder="PJM-YYYYMMDD-0001">
                  <button type="submit" :disabled="isSubmitting" :aria-busy="isSubmitting.toString()"
                    class="admin-submit-button inline-flex min-w-24 items-center justify-center rounded-lg bg-[#00d69f] px-4 py-2 text-xs font-bold text-[#003f38] hover:bg-[#36e9b3]">
                    <iconify-icon x-show="isSubmitting" icon="mdi:loading" class="animate-spin text-lg"
                      aria-hidden="true"></iconify-icon>
                    <span x-show="!isSubmitting">Konfirmasi</span>
                    <span x-show="isSubmitting" class="sr-only">Sedang memproses...</span>
                  </button>
                </div>
              </form>
            </div>
          </section>
        </div>
      </template>

    </div>
  </x-layout.admin>
@endif
