<x-layout.admin
  title="Ruangan - InvenTrack"
  headerTitle="Ruangan"
  headerDescription="Daftar ruangan sebagai acuan lokasi baku.">
  <div x-data="{ selectedCabinetId: @js($ruangan->lemaris->first()?->lemari_id ?? 'lainnya') }"
    class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-8rem)] bg-[#E4FAF4] px-4 py-6 sm:px-8 sm:py-8">
    <div class="mx-auto w-full max-w-[1500px]">
      <div class="mb-6">
        <a href="{{ route('admin.ruangan.index') }}"
          class="mb-4 inline-flex items-center gap-1.5 rounded-lg px-2 py-2 text-sm font-semibold text-[#00785F] transition hover:bg-white/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
          <iconify-icon icon="mdi:arrow-left" width="18" aria-hidden="true"></iconify-icon>
          Kembali
        </a>
        <div>
          <h2 class="text-lg font-bold text-[#064E3B]">{{ $ruangan->nama_ruangan }}</h2>
          <p class="mt-1 text-sm text-slate-500">{{ $ruangan->kode_ruangan }}</p>
        </div>
      </div>

      <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($ruangan->lemaris as $lemari)
          <button type="button" @click="selectedCabinetId = @js($lemari->lemari_id)"
            :aria-pressed="(selectedCabinetId === @js($lemari->lemari_id)).toString()"
            :class="selectedCabinetId === @js($lemari->lemari_id)
                ? 'border-[#00bfa5] ring-1 ring-[#00bfa5]'
                : 'border-slate-200 hover:border-[#00bfa5]'"
            class="flex min-h-20 items-center gap-3 rounded-2xl border bg-white p-3 text-left shadow-[0_2px_5px_rgba(15,55,48,0.12)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[#baf4e8] text-[#00785F]">
              <iconify-icon icon="mdi:wardrobe-outline" width="30" aria-hidden="true"></iconify-icon>
            </span>
            <span class="min-w-0">
              <span class="block truncate text-base font-semibold text-[#064E3B]">{{ $lemari->nama_lemari }}</span>
              <span class="mt-0.5 block text-xs text-slate-500">
                {{ $lemari->lokasiBarangs->sum('stok_total') }} Barang
              </span>
            </span>
          </button>
        @endforeach

        <button type="button" @click="selectedCabinetId = 'lainnya'"
          :aria-pressed="(selectedCabinetId === 'lainnya').toString()"
          :class="selectedCabinetId === 'lainnya'
              ? 'border-[#00bfa5] ring-1 ring-[#00bfa5]'
              : 'border-slate-200 hover:border-[#00bfa5]'"
          class="flex min-h-20 items-center gap-3 rounded-2xl border bg-white p-3 text-left shadow-[0_2px_5px_rgba(15,55,48,0.12)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[#baf4e8] text-[#00785F]">
            <iconify-icon icon="mdi:package-variant-closed" width="25" aria-hidden="true"></iconify-icon>
          </span>
          <span class="min-w-0">
            <span class="block truncate text-base font-semibold text-[#064E3B]">Lain-Lain</span>
            <span class="mt-0.5 block text-xs text-slate-500">
              {{ $ruangan->lokasiBarangs->sum('stok_total') }} Barang
            </span>
          </span>
        </button>
      </div>

      <div class="overflow-x-auto rounded-xl border border-[#c7d8d3] bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
          <thead class="bg-[#f5f8f7] font-semibold text-[#164b43]">
            <tr>
              <th scope="col" class="px-3 py-2.5">Barang</th>
              <th scope="col" class="px-3 py-2.5">Kode</th>
              <th scope="col" class="px-3 py-2.5 text-right">Jumlah</th>
              <th scope="col" class="px-3 py-2.5 text-right">Tersedia</th>
            </tr>
          </thead>
          @foreach ($ruangan->lemaris as $lemari)
            <tbody x-cloak x-show="selectedCabinetId === @js($lemari->lemari_id)" class="divide-y divide-slate-100">
              @forelse ($lemari->lokasiBarangs as $lokasiBarang)
                <tr class="bg-white text-slate-700">
                  <td class="px-3 py-2.5 font-medium">{{ $lokasiBarang->barang->nama_barang }}</td>
                  <td class="whitespace-nowrap px-3 py-2.5">{{ $lokasiBarang->barang->kode_barang }}</td>
                  <td class="whitespace-nowrap px-3 py-2.5 text-right">
                    {{ $lokasiBarang->stok_total }} {{ $lokasiBarang->barang->satuan ?: 'unit' }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-2.5 text-right">
                    {{ $lokasiBarang->stok_tersedia }} {{ $lokasiBarang->barang->satuan ?: 'unit' }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="px-3 py-7 text-center text-slate-500">Belum ada barang di lemari ini.</td>
                </tr>
              @endforelse
            </tbody>
          @endforeach
          <tbody x-cloak x-show="selectedCabinetId === 'lainnya'" class="divide-y divide-slate-100">
            @forelse ($ruangan->lokasiBarangs as $lokasiBarang)
              <tr class="bg-white text-slate-700">
                <td class="px-3 py-2.5 font-medium">{{ $lokasiBarang->barang->nama_barang }}</td>
                <td class="whitespace-nowrap px-3 py-2.5">{{ $lokasiBarang->barang->kode_barang }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right">
                  {{ $lokasiBarang->stok_total }} {{ $lokasiBarang->barang->satuan ?: 'unit' }}
                </td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right">
                  {{ $lokasiBarang->stok_tersedia }} {{ $lokasiBarang->barang->satuan ?: 'unit' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="px-3 py-7 text-center text-slate-500">Belum ada barang di luar lemari.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</x-layout.admin>
