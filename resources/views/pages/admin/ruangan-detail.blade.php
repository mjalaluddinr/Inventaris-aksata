<x-layout.admin
  title="Ruangan - InvenTrack"
  headerTitle="Ruangan"
  headerDescription="Daftar ruangan sebagai acuan lokasi baku.">
  <div x-data="{
      selectedCabinetId: @js($ruangan->lemaris->first()?->lemari_id ?? 'lainnya'),
      hasOtherRows: @js($otherBarangCount > 0),
      otherBarangCount: @js($otherBarangCount),
      async withdrawUsage(event) {
          const form = event.currentTarget;
          if (form.dataset.submitting === 'true') return;

          form.dataset.submitting = 'true';
          const submitButton = form.querySelector('button[type=submit]');
          const originalLabel = submitButton?.textContent?.trim() ?? '';
          if (submitButton) {
              submitButton.disabled = true;
              submitButton.setAttribute('aria-busy', 'true');
              submitButton.textContent = 'Menarik...';
          }

          try {
              const response = await fetch(form.action, {
                  method: 'POST',
                  headers: {
                      'X-Requested-With': 'XMLHttpRequest',
                      'Accept': 'application/json',
                  },
                  body: new FormData(form),
              });
              const data = await response.json().catch(() => null);
              const validationMessage = data?.errors
                  ? Object.values(data.errors).flat().find(Boolean)
                  : null;

              if (!response.ok || data?.success !== true) {
                  throw new Error(validationMessage || data?.message || 'Gagal menarik kembali barang.');
              }

              const row = form.closest('tr');
              const body = row?.parentElement;
              row?.remove();

              const remainingRows = body?.querySelectorAll('[data-other-row]') ?? [];
              this.hasOtherRows = remainingRows.length > 0;
              this.otherBarangCount = new Set(
                  Array.from(remainingRows, (remainingRow) => remainingRow.dataset.barangId)
              ).size;

              window.showToast(data.message || 'Barang berhasil ditarik kembali.', 'success');
          } catch (error) {
              window.showToast(error?.message || 'Terjadi kesalahan sistem.', 'error');
          } finally {
              form.dataset.submitting = 'false';
              if (submitButton) {
                  submitButton.disabled = false;
                  submitButton.removeAttribute('aria-busy');
                  submitButton.textContent = originalLabel;
              }
          }
      }
    }"
    class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-8rem)] bg-[#E4FAF4] px-4 py-6 sm:px-8 sm:py-8">
    <div class="mx-auto w-full max-w-[1500px]">
      <div class="mb-6">
        <h2 class="text-lg font-bold text-[#064E3B]">
          <a href="{{ route('admin.ruangan.index') }}"
            aria-label="Kembali ke daftar ruangan"
            class="inline-flex items-center gap-2 rounded-md py-1 transition hover:text-[#00785F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
            <iconify-icon icon="mdi:arrow-left" width="18" aria-hidden="true"></iconify-icon>
            {{ $ruangan->nama_ruangan }}
          </a>
        </h2>
        <p class="mt-1 text-sm text-slate-600">{{ $ruangan->kode_ruangan }}</p>
      </div>

      <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($ruangan->lemaris as $lemari)
          <button type="button" @click="selectedCabinetId = @js($lemari->lemari_id)"
            :aria-pressed="(selectedCabinetId === @js($lemari->lemari_id)).toString()"
            :class="selectedCabinetId === @js($lemari->lemari_id)
                ? 'border-[#00bfa5] ring-1 ring-[#00bfa5]'
                : 'border-slate-200 hover:border-[#00bfa5]'"
            class="flex min-h-24 items-center gap-4 rounded-2xl border bg-white p-4 text-left shadow-[0_2px_5px_rgba(15,55,48,0.12)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#baf4e8] text-[#00785F]">
              <iconify-icon icon="mdi:wardrobe-outline" width="34" aria-hidden="true"></iconify-icon>
            </span>
            <span class="min-w-0">
              <span class="block truncate text-lg font-semibold text-[#064E3B]">{{ $lemari->nama_lemari }}</span>
              <span class="mt-1 block text-sm text-slate-600">
                {{ $lemari->lokasiBarangs->unique('barang_id')->count() }} Jenis Barang
              </span>
            </span>
          </button>
        @endforeach

        <button type="button" @click="selectedCabinetId = 'lainnya'"
          :aria-pressed="(selectedCabinetId === 'lainnya').toString()"
          :class="selectedCabinetId === 'lainnya'
              ? 'border-[#00bfa5] ring-1 ring-[#00bfa5]'
              : 'border-slate-200 hover:border-[#00bfa5]'"
          class="flex min-h-24 items-center gap-4 rounded-2xl border bg-white p-4 text-left shadow-[0_2px_5px_rgba(15,55,48,0.12)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
          <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#baf4e8] text-[#00785F]">
            <iconify-icon icon="mdi:package-variant-closed" width="30" aria-hidden="true"></iconify-icon>
          </span>
          <span class="min-w-0">
            <span class="block truncate text-lg font-semibold text-[#064E3B]">Lain-Lain</span>
            <span class="mt-1 block text-sm text-slate-600">
              <span x-text="otherBarangCount">{{ $otherBarangCount }}</span> Jenis Barang
            </span>
          </span>
        </button>
      </div>

      <div x-cloak class="overflow-x-auto rounded-xl border border-[#c7d8d3] bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead class="bg-[#f5f8f7] text-xs font-semibold text-[#164b43]">
            <tr x-show="selectedCabinetId !== 'lainnya'">
              <th scope="col" class="whitespace-nowrap px-4 py-3">Kode Barang</th>
              <th scope="col" class="px-4 py-3">Nama Barang</th>
              <th scope="col" class="px-4 py-3">Spesifikasi</th>
              <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">Stok Total</th>
              <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">Stok Tersedia</th>
            </tr>
            <tr x-show="selectedCabinetId === 'lainnya'">
              <th scope="col" class="whitespace-nowrap px-4 py-3">Kode Barang</th>
              <th scope="col" class="px-4 py-3">Nama Barang</th>
              <th scope="col" class="px-4 py-3">Spesifikasi</th>
              <th scope="col" class="whitespace-nowrap px-4 py-3">Dipasang Sejak</th>
              <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">Jumlah Terpasang</th>
              <th scope="col" class="whitespace-nowrap px-4 py-3">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @foreach ($ruangan->lemaris as $lemari)
              @forelse ($lemari->lokasiBarangs as $lokasiBarang)
                <tr x-show="selectedCabinetId === @js($lemari->lemari_id)" class="bg-white text-slate-700">
                  <td class="whitespace-nowrap px-4 py-3">{{ $lokasiBarang->barang->kode_barang }}</td>
                  <td class="px-4 py-3 font-medium">{{ $lokasiBarang->barang->nama_barang }}</td>
                  <td class="px-4 py-3">{{ $lokasiBarang->barang->spesifikasi ?: '—' }}</td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    {{ $lokasiBarang->stok_total }} {{ $lokasiBarang->barang->satuan ?: 'unit' }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    {{ $lokasiBarang->stok_tersedia }} {{ $lokasiBarang->barang->satuan ?: 'unit' }}
                  </td>
                </tr>
              @empty
                <tr x-show="selectedCabinetId === @js($lemari->lemari_id)">
                  <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                    Belum ada barang di lemari ini.
                  </td>
                </tr>
              @endforelse
            @endforeach

            @foreach ($ruangan->barangDipakais as $activeUsage)
              <tr x-show="selectedCabinetId === 'lainnya'" data-other-row data-barang-id="{{ $activeUsage->barang_id }}"
                class="bg-white text-slate-700">
                <td class="whitespace-nowrap px-4 py-3">{{ $activeUsage->barang->kode_barang }}</td>
                <td class="px-4 py-3 font-medium">{{ $activeUsage->barang->nama_barang }}</td>
                <td class="px-4 py-3">{{ $activeUsage->barang->spesifikasi ?: '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">
                  {{ $activeUsage->tanggal_pasang?->translatedFormat('d M Y') ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                  {{ number_format($activeUsage->jumlah, 0, ',', '.') }} {{ $activeUsage->barang->satuan ?: 'unit' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                  @if ($canManageInventory)
                    {{-- ASVS 8.3.1: The server route enforces inventory-management permission for this action. --}}
                    <form action="{{ route('admin.barang.pemakaian.tarik', [$activeUsage->barang, $activeUsage]) }}"
                      method="POST" @submit.prevent="withdrawUsage($event)">
                      @csrf
                      <button type="submit"
                        class="inline-flex min-h-9 items-center rounded-md border border-[#00bfa5] bg-white px-3 py-1.5 text-sm font-medium text-[#00785F] transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00785F]">
                        Tarik kembali
                      </button>
                    </form>
                  @else
                    <span aria-hidden="true">—</span>
                  @endif
                </td>
              </tr>
            @endforeach

            @foreach ($ruangan->lokasiBarangs as $lokasiBarang)
              <tr x-show="selectedCabinetId === 'lainnya'" data-other-row data-barang-id="{{ $lokasiBarang->barang_id }}"
                class="bg-white text-slate-700">
                <td class="whitespace-nowrap px-4 py-3">{{ $lokasiBarang->barang->kode_barang }}</td>
                <td class="px-4 py-3 font-medium">{{ $lokasiBarang->barang->nama_barang }}</td>
                <td class="px-4 py-3">{{ $lokasiBarang->barang->spesifikasi ?: '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">—</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">—</td>
                <td class="whitespace-nowrap px-4 py-3"><span aria-hidden="true">—</span></td>
              </tr>
            @endforeach

            <tr x-show="selectedCabinetId === 'lainnya' && !hasOtherRows">
              <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                Belum ada barang di Lain-Lain.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</x-layout.admin>
