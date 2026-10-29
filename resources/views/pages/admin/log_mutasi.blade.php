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

  <div x-data="{
      resultsUrl: @js(route('admin.log_mutasi')),
      filterRequest: null,
      searchTimer: null,
      filterLoading: false,
      filterError: '',
      async filterMutations(url = null) {
          window.clearTimeout(this.searchTimer);
          if (this.filterRequest) this.filterRequest.abort();

          const request = new AbortController();
          this.filterRequest = request;
          this.filterLoading = true;
          this.filterError = '';

          const requestUrl = new URL(url || this.resultsUrl, window.location.href);
          if (!url) {
              requestUrl.search = new URLSearchParams(new FormData(this.$refs.filterForm)).toString();
          }

          try {
              const response = await fetch(requestUrl, {
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                  signal: request.signal,
              });
              if (!response.ok || response.redirected) throw new Error('Log mutasi tidak dapat dimuat.');

              this.$refs.mutationResults.innerHTML = await response.text();
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.filterError = error.message || 'Log mutasi tidak dapat dimuat.';
              }
          } finally {
              if (this.filterRequest === request) {
                  this.filterLoading = false;
                  this.filterRequest = null;
              }
          }
      },
      scheduleMutationSearch() {
          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => this.filterMutations(), 250);
      },
      handleMutationPagination(event) {
          const target = event.target instanceof Element ? event.target : null;
          const link = target?.closest('a');
          if (!link || !this.$refs.mutationResults.contains(link)) return;
          event.preventDefault();
          this.filterMutations(link.href);
      },
  }" class="log-mutasi-surface relative -mx-8 -mb-8">
    <div class="space-y-4 p-4 sm:p-8">
      <form method="GET" action="{{ route('admin.log_mutasi') }}"
        x-ref="filterForm" @submit.prevent="filterMutations()"
        class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
        <div class="sm:w-52">
          <label for="mutation-type" class="sr-only">Filter jenis mutasi</label>
          <select id="mutation-type" name="tipe" @change="filterMutations()"
            class="w-full rounded-lg border border-[#B9DED4] bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
            <option value="">Semua jenis</option>
            @foreach ($mutationLabels as $type => $label)
              <option value="{{ $type }}" @selected(($filters['tipe'] ?? '') === $type)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="w-full sm:max-w-xs">
          <label for="mutation-search" class="sr-only">Cari mutasi</label>
          <input id="mutation-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
            @input="scheduleMutationSearch()"
            maxlength="100" placeholder="Cari Mutasi..."
            class="w-full rounded-lg border border-[#B9DED4] bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-500 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
        </div>
        <span x-cloak x-show="filterLoading" role="status" class="sr-only">Memuat log mutasi...</span>
      </form>

      <div x-cloak x-show="filterError"
        class="flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800"
        role="alert">
        <span x-text="filterError"></span>
        <button type="button" @click="filterMutations()"
          class="font-semibold focus:outline-none focus:ring-2 focus:ring-rose-400">Coba lagi</button>
      </div>

      <div id="mutation-results" x-ref="mutationResults" @click="handleMutationPagination($event)"
        :aria-busy="filterLoading.toString()">
        @fragment('mutation-results')
      <div class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm">
        <div class="theme-scrollbar overflow-x-auto">
          <table class="min-w-[1180px] divide-y divide-[#DCE9E6]">
            <thead class="bg-[#F1F8F6]">
              <tr>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">Waktu</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">Jenis Mutasi</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">Barang</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-slate-600">Jumlah</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">Lokasi</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">Perubahan Stok</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">User / Petugas</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">Keterangan</th>
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
                    @if ($log->tipe === 'dipindahkan')
                      @php
                        $unallocatedSource = str_contains($log->keterangan ?? '', 'belum memiliki lokasi');
                      @endphp
                      <p>
                        {{ $unallocatedSource ? 'Belum ditempatkan' : ($log->ruangan?->nama_ruangan ?? 'Lokasi asal tidak tersedia') }}
                        @if ($log->lemariAsal)
                          · {{ $log->lemariAsal->nama_lemari }}
                        @endif
                      </p>
                      <p class="mt-0.5 text-slate-500">
                        → {{ $log->lemariTujuan?->ruangan?->nama_ruangan ?? 'Lokasi tujuan' }}
                        @if ($log->lemariTujuan)
                          · {{ $log->lemariTujuan->nama_lemari }}
                        @endif
                      </p>
                    @else
                      <p>{{ $log->ruangan?->nama_ruangan ?? 'Ruangan tidak tersedia' }}</p>
                      @if ($log->lemariAsal || $log->lemariTujuan)
                        <p class="mt-0.5 text-slate-500">
                          {{ $log->lemariAsal?->nama_lemari ?? ($log->lemariTujuan?->nama_lemari ?? '—') }}
                        </p>
                      @endif
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                    <span class="text-slate-500">{{ number_format($log->stok_sebelum, 0, ',', '.') }}</span>
                    <iconify-icon icon="mdi:arrow-right" class="mx-1 text-slate-400" aria-hidden="true"></iconify-icon>
                    <span class="font-semibold">{{ number_format($log->stok_sesudah, 0, ',', '.') }}</span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-700">
                    @php
                      $accountName = $log->akun
                          ? trim(($log->akun->first_name ?? '').' '.($log->akun->last_name ?? '')) ?: $log->akun->username
                          : 'Akun tidak tersedia';
                      $operatorName = $log->olehAkun
                          ? trim(($log->olehAkun->first_name ?? '').' '.($log->olehAkun->last_name ?? '')) ?: $log->olehAkun->username
                          : null;
                    @endphp
                    @if ($log->akun)
                      <p class="whitespace-nowrap">User: {{ $accountName }}</p>
                    @endif
                    @if ($operatorName)
                      <p @class([
                          'mt-0.5' => $log->akun,
                          'whitespace-nowrap',
                          'text-slate-500',
                      ])>
                        Petugas ({{ ucfirst($log->olehAkun->role) }}): {{ $operatorName }}
                      </p>
                    @endif
                    @if (! $log->akun && ! $log->olehAkun)
                      <p>—</p>
                    @endif
                  </td>
                  <td class="max-w-xs px-4 py-3 text-xs font-medium leading-5 text-slate-800">
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
        @endfragment
      </div>
    </div>
  </div>
</x-layout.admin>
