<x-layout.admin title="Log Mutasi - InvenTrack" header-title="Log Mutasi"
  header-description="Lihat riwayat perubahan stok barang inventaris.">
  <style>
    .log-mutasi-surface {
      box-sizing: border-box;
      min-height: max(0px, calc(100vh - 10rem));
      margin-top: -2rem;
      background-color: #e8f8f4;
    }
  </style>

  <div class="log-mutasi-surface relative -mx-8 -mb-8">
    <div class="space-y-4 p-4 sm:p-8">
      <form method="GET" action="{{ route('admin.log_mutasi') }}"
        class="flex flex-col gap-3 rounded-xl border border-[#D3E3DF] bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
          <label for="mutation-search" class="mb-1.5 block text-xs font-semibold text-slate-700">Cari mutasi</label>
          <input id="mutation-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
            maxlength="100" placeholder="Kode/nama barang, ruangan, akun, atau keterangan"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
        </div>
        <div class="sm:w-52">
          <label for="mutation-type" class="mb-1.5 block text-xs font-semibold text-slate-700">Jenis mutasi</label>
          <select id="mutation-type" name="tipe"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
            <option value="">Semua jenis</option>
            @foreach ($mutationLabels as $type => $label)
              <option value="{{ $type }}" @selected(($filters['tipe'] ?? '') === $type)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#008b7a] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#006f62] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a]">
            <iconify-icon icon="mdi:magnify" aria-hidden="true"></iconify-icon>
            Cari
          </button>
          @if (($filters['search'] ?? '') !== '' || ($filters['tipe'] ?? '') !== '')
            <a href="{{ route('admin.log_mutasi') }}"
              class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
              Reset
            </a>
          @endif
        </div>
      </form>

      <div class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm">
        <div class="theme-scrollbar overflow-x-auto">
          <table class="min-w-[1180px] divide-y divide-[#DCE9E6]">
            <thead class="bg-[#F1F8F6]">
              <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Waktu</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Jenis Mutasi</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Barang</th>
                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-slate-600">Jumlah</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Lokasi</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Perubahan Stok</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Akun / Petugas</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E7EFED] bg-white">
              @forelse ($logs as $log)
                <tr class="transition-colors hover:bg-[#F7FBFA]">
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">
                    {{ $log->created_at?->translatedFormat('d M Y, H:i:s') ?? '—' }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <span class="inline-flex rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">
                      {{ $mutationLabels[$log->tipe] ?? ucfirst($log->tipe) }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    @if ($log->barang)
                      <p class="text-xs font-semibold text-slate-800">{{ $log->barang->nama_barang }}</p>
                      <p class="mt-0.5 text-xs text-slate-500">{{ $log->barang->kode_barang }}</p>
                    @else
                      <span class="text-xs text-slate-500">Barang tidak tersedia</span>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-slate-700">
                    {{ number_format($log->jumlah, 0, ',', '.') }}
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-700">
                    <p>{{ $log->ruangan?->nama_ruangan ?? 'Ruangan tidak tersedia' }}</p>
                    @if ($log->lemariAsal || $log->lemariTujuan)
                      <p class="mt-0.5 text-slate-500">
                        {{ $log->lemariAsal?->nama_lemari ?? '—' }}
                        @if ($log->lemariTujuan)
                          → {{ $log->lemariTujuan->nama_lemari }}
                        @endif
                      </p>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                    <span class="text-slate-500">{{ number_format($log->stok_sebelum, 0, ',', '.') }}</span>
                    <iconify-icon icon="mdi:arrow-right" class="mx-1 text-slate-400" aria-hidden="true"></iconify-icon>
                    <span class="font-semibold">{{ number_format($log->stok_sesudah, 0, ',', '.') }}</span>
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-700">
                    @php
                      $accountName = $log->akun
                          ? trim(($log->akun->first_name ?? '').' '.($log->akun->last_name ?? '')) ?: $log->akun->username
                          : 'Akun tidak tersedia';
                      $operatorName = $log->olehAkun
                          ? trim(($log->olehAkun->first_name ?? '').' '.($log->olehAkun->last_name ?? '')) ?: $log->olehAkun->username
                          : null;
                    @endphp
                    <p>{{ $accountName }}</p>
                    @if ($operatorName)
                      <p class="mt-0.5 text-slate-500">Petugas: {{ $operatorName }}</p>
                    @endif
                  </td>
                  <td class="max-w-xs px-4 py-3 text-xs leading-5 text-slate-600">
                    {{ $log->keterangan ?: '—' }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-500">
                    @if (($filters['search'] ?? '') !== '' || ($filters['tipe'] ?? '') !== '')
                      Tidak ada mutasi yang sesuai dengan pencarian.
                    @else
                      Belum ada riwayat mutasi barang.
                    @endif
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($logs->hasPages())
          <div class="border-t border-[#E2ECE9] bg-[#F7FBFA] px-4 py-3">
            {{ $logs->links() }}
          </div>
        @endif
      </div>
    </div>
  </div>
</x-layout.admin>
