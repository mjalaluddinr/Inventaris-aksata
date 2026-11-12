<x-layout.admin title="Dashboard - InvenTrack" header-title="Dashboard" header-description="Selamat datang di InvenTrack.">
  <div class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-10rem)] bg-[#E8F8F4]">
    <div class="space-y-6 p-4 sm:p-8">
      <section class="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan inventaris">
        @foreach ($statistics as $statistic)
          <a href="{{ $statistic['url'] }}"
            class="relative flex h-24 min-w-0 items-center gap-4 rounded-xl border border-[#d6ebe5] bg-white px-4 py-4 shadow-[0_2px_5px_rgba(15,69,59,0.16)] transition-shadow hover:shadow-[0_3px_8px_rgba(15,69,59,0.2)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008f7e]">
            <span class="absolute right-3 top-2 rounded-full bg-[#b8f5e9] px-2 py-1 text-[10px] font-semibold leading-none text-[#008f7e]">
              {{ $statistic['month_label'] }}
            </span>
            <span @class([
                'flex h-14 w-14 shrink-0 items-center justify-center rounded-lg',
                'bg-[#bdf3e8] text-[#009c86]' => $statistic['tone'] === 'teal',
                'bg-[#cceafa] text-[#2484b8]' => $statistic['tone'] === 'blue',
                'bg-[#f6f2cc] text-[#a88b00]' => $statistic['tone'] === 'amber',
                'bg-[#ffe1cb] text-[#d87135]' => $statistic['tone'] === 'orange',
            ])>
              <iconify-icon icon="{{ $statistic['icon'] }}" class="text-3xl" aria-hidden="true"></iconify-icon>
            </span>
            <span class="min-w-0 pt-3">
              <span class="block text-2xl font-bold leading-7 text-[#064E3B]">{{ number_format($statistic['value'], 0, ',', '.') }}</span>
              <span class="mt-0.5 block text-xs leading-4 text-slate-500">{{ $statistic['label'] }}</span>
            </span>
          </a>
        @endforeach
      </section>

      <div @class([
          'grid grid-cols-1 items-stretch gap-5',
          'xl:grid-cols-12' => $canProcessLoans,
      ])>
        @if ($canProcessLoans)
          <section class="flex min-w-0 flex-col xl:col-span-7" aria-label="Peminjaman menunggu konfirmasi">
            <h2 id="pending-loans-title" class="mb-3 text-base font-semibold leading-6 text-[#064E3B]">
              Peminjaman menunggu konfirmasi
            </h2>
            <a href="{{ route('admin.peminjaman') }}"
              class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-[#c9e2da] bg-white shadow-[0_2px_5px_rgba(15,69,59,0.08)] transition hover:border-[#8dcfc0] hover:shadow-[0_4px_12px_rgba(15,69,59,0.12)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008f7e]">
              @if ($pendingLoans->isNotEmpty())
                <div class="overflow-x-auto">
                  <table class="w-full min-w-[560px] border-collapse text-left text-xs">
                    <thead class="bg-[#f3f5f4] text-[#164b67]">
                      <tr>
                        <th scope="col" class="rounded-tl-xl px-4 py-3 font-semibold sm:px-5">Peminjam</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Barang</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Keperluan</th>
                        <th scope="col" class="rounded-tr-xl px-4 py-3 font-semibold sm:px-5">Status</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e9e8] text-slate-700">
                      @foreach ($pendingLoans as $loan)
                        @php
                          $borrowerName = trim(($loan->akun?->first_name ?? '') . ' ' . ($loan->akun?->last_name ?? ''));
                          $borrowerName = $borrowerName !== '' ? $borrowerName : ($loan->akun?->username ?? 'Akun tidak tersedia');
                        @endphp
                        <tr class="align-top transition-colors last:[&>td:first-child]:rounded-bl-xl last:[&>td:last-child]:rounded-br-xl hover:bg-[#f8fcfb]">
                          <td class="px-4 py-3.5 font-medium sm:px-5">{{ $borrowerName }}</td>
                          <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-2">
                              @foreach ($loan->detailPeminjamans as $loanItem)
                                <span class="inline-flex max-w-full items-center gap-2 rounded-lg border border-[#d6ebe5] bg-[#f5fbf9] px-2.5 py-1.5 text-xs text-slate-700">
                                  <span class="min-w-0 break-words font-medium">{{ $loanItem->barang?->nama_barang ?? 'Barang tidak tersedia' }}</span>
                                  <span class="shrink-0 rounded-full bg-white px-2 py-0.5 font-bold text-[#007f70]">× {{ $loanItem->jumlah_pinjam }}</span>
                                </span>
                              @endforeach
                            </div>
                          </td>
                          <td class="px-4 py-3.5">{{ filled($loan->keperluan) ? $loan->keperluan : '—' }}</td>
                          <td class="px-4 py-3.5 sm:px-5">
                            <div class="flex flex-col items-start gap-1.5">
                              <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#fff4a8] px-2.5 py-1 text-[11px] font-semibold leading-none text-[#725d00]">
                                <iconify-icon icon="mdi:clock-outline" aria-hidden="true"></iconify-icon>
                                Menunggu
                              </span>
                              <span class="whitespace-nowrap text-[11px] text-slate-500">
                                Pengajuan {{ $loan->tanggal_pengajuan?->locale('id')->diffForHumans() ?? '—' }}
                              </span>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="flex flex-1 flex-col justify-center py-8 text-center">
                  <p class="text-sm font-medium text-slate-600">Tidak ada peminjaman yang menunggu konfirmasi.</p>
                  <p class="mt-1 text-xs text-slate-500">Pengajuan baru akan muncul di sini.</p>
                </div>
              @endif
            </a>
          </section>
        @endif

        <section @class([
            'flex min-w-0 flex-col',
            'xl:col-span-5' => $canProcessLoans,
        ]) aria-labelledby="recent-activities-title">
          <h2 id="recent-activities-title" class="mb-3 text-base font-semibold leading-6 text-[#064E3B]">
            Aktivitas terbaru
          </h2>

          @if ($activities->isNotEmpty())
            <ol class="flex-1 overflow-hidden rounded-xl border border-[#c9e2da] bg-white">
              @foreach ($activities as $activity)
                @php
                  $activityTitle = strtolower($activity['title']);
                  $activityColor = match (true) {
                      str_contains($activityTitle, 'ditambahkan'),
                      str_contains($activityTitle, 'stok masuk'),
                      str_contains($activityTitle, 'dikembalikan'),
                      str_contains($activityTitle, 'dikonfirmasi') => 'bg-[#03bda4]',
                      str_contains($activityTitle, 'dihapus'),
                      str_contains($activityTitle, 'ditarik') => 'bg-[#d8212d]',
                      str_contains($activityTitle, 'diubah'),
                      str_contains($activityTitle, 'disesuaikan'),
                      str_contains($activityTitle, 'dipindahkan') => 'bg-[#4b75d1]',
                      $activity['type'] === 'loan' => 'bg-[#d6a80c]',
                      $activity['type'] === 'import' => 'bg-[#2484b8]',
                      default => 'bg-slate-400',
                  };
                @endphp
                <li class="flex min-h-[58px] items-start gap-2.5 border-b border-[#e5e9e8] px-3 py-2.5 last:border-b-0">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $activityColor }}" aria-hidden="true"></span>
                  <div class="min-w-0 flex-1">
                    <time datetime="{{ $activity['occurred_at']->toIso8601String() }}"
                      class="block text-[10px] font-semibold leading-3 text-slate-600">
                      {{ $activity['occurred_at']->locale('id')->diffForHumans() }}
                    </time>
                    <p class="mt-1 line-clamp-2 text-xs leading-4 text-slate-700">
                      <span class="font-normal text-slate-700">{{ $activity['title'] }}</span>
                      @if (filled($activity['description']))
                        <span class="font-bold text-black"> {{ $activity['description'] }}</span>
                      @endif
                    </p>
                  </div>
                </li>
              @endforeach
            </ol>
          @else
            <div class="flex flex-1 flex-col justify-center rounded-xl border border-[#c9e2da] bg-white px-4 py-10 text-center">
              <p class="text-sm font-medium text-slate-600">Belum ada aktivitas terbaru.</p>
              <p class="mt-1 text-xs text-slate-500">Aktivitas inventaris akan tampil di sini.</p>
            </div>
          @endif
        </section>
      </div>
    </div>
  </div>
</x-layout.admin>
