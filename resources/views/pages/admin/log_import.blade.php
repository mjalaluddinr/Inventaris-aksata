<x-layout.admin title="Riwayat Log Import - InvenTrack" header-title="Riwayat Log Import"
  header-description="Lihat riwayat dan detail error dari proses import data.">

  <style>
    .log-import-surface {
      box-sizing: border-box;
      min-height: max(0px, calc(100vh - 10rem));
      margin-top: -2rem;
      background-color: #e8f8f4;
    }

    .log-import-modal-backdrop {
      background: linear-gradient(135deg, rgba(7, 59, 86, .84), rgba(2, 6, 23, .76), rgba(6, 78, 72, .78));
      backdrop-filter: blur(9px);
    }
  </style>

  <div class="log-import-surface relative -mx-8 -mb-8">
    <div class="p-4 sm:p-8">

      <div class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm">
        <div class="theme-scrollbar overflow-x-auto">
          <table class="min-w-full divide-y divide-[#DCE9E6]">
            <thead class="bg-[#F1F8F6]">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Waktu Mulai</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Jenis Input</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Nama File</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Statistik</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E7EFED] bg-white">
              @php
                $logs = \App\Models\LogInput::latest('mulai_at')->paginate(20);
              @endphp
              @forelse($logs as $log)
                <tr class="transition-colors hover:bg-[#F7FBFA]">
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">
                    {{ $log->mulai_at->format('d M Y, H:i:s') }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-800">
                    {{ ucfirst($log->jenis_input) }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                    {{ $log->nama_file }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3">
                    @if ($log->status === 'selesai')
                      <span
                        class="inline-flex rounded-md px-2 py-1 text-xs font-medium bg-emerald-100 text-emerald-800">Selesai</span>
                    @elseif($log->status === 'sebagian')
                      <span
                        class="inline-flex rounded-md px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800">Sebagian</span>
                    @elseif($log->status === 'proses')
                      <span
                        class="inline-flex items-center gap-1 rounded-md bg-sky-100 px-2 py-1 text-xs font-medium text-sky-800">
                        <iconify-icon icon="mdi:loading" class="animate-spin" width="14"></iconify-icon>
                        Dalam antrean / Diproses
                      </span>
                    @else
                      <span
                        class="inline-flex rounded-md px-2 py-1 text-xs font-medium bg-rose-100 text-rose-800">Gagal</span>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                    <span class="text-emerald-600 font-semibold">{{ $log->baris_berhasil }}</span> Sukses /
                    <span class="text-rose-600 font-semibold">{{ $log->baris_gagal }}</span> Gagal
                    <span class="text-slate-400">(Total: {{ $log->total_baris }})</span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    @if ($log->baris_gagal > 0 || $log->status === 'gagal')
                      <button onclick="openLogDetailModal('{{ $log->log_input_id }}')"
                        class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                        <iconify-icon icon="mdi:alert-circle-outline" width="15"></iconify-icon>
                        Lihat Detail
                      </button>
                    @else
                      <span class="text-xs text-slate-400">-</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                    Belum ada riwayat import.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="border-t border-[#E2ECE9] bg-[#F7FBFA] px-4 py-3">
          <x-pagination :paginator="$logs" label="Navigasi halaman log import" />
        </div>
      </div>
    </div>
  </div>

  {{-- Modal Detail Error --}}
  <div id="logDetailModal" class="fixed inset-0 z-[120] hidden overflow-y-auto" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4 text-center sm:p-6">
      <div class="log-import-modal-backdrop fixed inset-0 z-0 transition-opacity" aria-hidden="true"
        onclick="closeLogDetailModal()"></div>
      <div
        class="relative z-10 my-auto inline-block max-h-[calc(100dvh-2rem)] w-full max-w-2xl transform overflow-y-auto rounded-xl border-2 border-[#08A9D6] bg-white text-left shadow-[0_24px_80px_rgba(2,25,41,0.4)] transition-all">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800" id="modal-title">
            <iconify-icon icon="mdi:alert-circle-outline" class="text-rose-600" width="20"></iconify-icon>
            Detail Kegagalan Import
          </h3>
          <button type="button" onclick="closeLogDetailModal()"
            class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600">
            <iconify-icon icon="mdi:close" width="20"></iconify-icon>
          </button>
        </div>
        <div class="px-4 py-5 sm:p-6">
          <div id="modalLoading" class="text-center py-8">
            <iconify-icon icon="mdi:loading" class="animate-spin text-[#08A9D6] mb-3" width="40"></iconify-icon>
            <p class="text-sm text-slate-500">Memuat detail error...</p>
          </div>
          <div id="modalError" class="hidden rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert"></div>
          <div id="modalContent" class="hidden space-y-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <p class="text-xs font-medium text-slate-500">Nama File</p>
                  <p id="detailFileName" class="font-mono font-semibold text-slate-800 truncate"></p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-500">Total Baris</p>
                  <p id="detailTotalRows" class="font-semibold text-slate-800"></p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-500">Berhasil</p>
                  <p id="detailSuccessRows" class="font-semibold text-emerald-600"></p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-500">Gagal</p>
                  <p id="detailFailedRows" class="font-semibold text-rose-600"></p>
                </div>
              </div>
            </div>
            <div id="errorListContainer" class="space-y-3 max-h-64 overflow-y-auto pr-2"></div>
          </div>
        </div>
        <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3">
          <button type="button" onclick="closeLogDetailModal()"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const logDetailModal = document.getElementById('logDetailModal');
    document.body.appendChild(logDetailModal);

    async function openLogDetailModal(logId) {
      const modal = logDetailModal;
      const loading = document.getElementById('modalLoading');
      const modalError = document.getElementById('modalError');
      const content = document.getElementById('modalContent');
      const errorContainer = document.getElementById('errorListContainer');

      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('overflow-hidden');
      loading.classList.remove('hidden');
      modalError.classList.add('hidden');
      content.classList.add('hidden');
      errorContainer.replaceChildren();

      try {
        const response = await fetch(`/admin/akun/log/${encodeURIComponent(logId)}/detail`, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const res = await response.json();

        if (!response.ok || !res.success || !res.data) {
          throw new Error(res.message || 'Detail import tidak berhasil dimuat.');
        }

        const data = res.data;
        const detailError = Array.isArray(data.detail_error) ? data.detail_error : [];
        const fileError = typeof detailError[1] === 'string' ? detailError[1] : '';
        const rowErrors = Array.isArray(detailError[2]) ? detailError[2] : [];

        document.getElementById('detailFileName').textContent = detailError[0] || data.nama_file ||
          'Tidak diketahui';
        document.getElementById('detailTotalRows').textContent = data.total_baris ?? 0;
        document.getElementById('detailSuccessRows').textContent = data.baris_berhasil ?? 0;
        document.getElementById('detailFailedRows').textContent = data.baris_gagal ?? 0;

        if (fileError) {
          const fileErrorDiv = document.createElement('div');
          fileErrorDiv.className = 'rounded border-l-4 border-rose-500 bg-rose-50 p-3';

          const fileErrorTitle = document.createElement('p');
          fileErrorTitle.className = 'mb-1 text-xs font-bold uppercase text-rose-700';
          fileErrorTitle.textContent = 'Kegagalan Sistem / File';

          const fileErrorMessage = document.createElement('p');
          fileErrorMessage.className = 'whitespace-pre-wrap break-words text-sm font-mono text-rose-800';
          fileErrorMessage.textContent = fileError;

          fileErrorDiv.append(fileErrorTitle, fileErrorMessage);
          errorContainer.appendChild(fileErrorDiv);
        }

        if (rowErrors.length > 0) {
          const rowErrorTitle = document.createElement('p');
          rowErrorTitle.className = 'mb-2 mt-4 text-xs font-bold uppercase text-gray-500';
          rowErrorTitle.textContent = `Kegagalan Per Baris (${rowErrors.length} baris):`;
          errorContainer.appendChild(rowErrorTitle);

          rowErrors.forEach(error => {
            const rowDiv = document.createElement('div');
            rowDiv.className = 'flex items-start gap-3 rounded border border-gray-200 bg-gray-50 p-3';

            const indicator = document.createElement('span');
            indicator.className =
              'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-100 text-xs font-bold text-rose-800';
            indicator.textContent = '!';

            const messageContainer = document.createElement('div');
            messageContainer.className = 'min-w-0 flex-1';

            const rowLabel = document.createElement('p');
            rowLabel.className = 'text-sm font-semibold text-gray-800';
            const rowNumber = Array.isArray(error) ? error[0] : error?.row;
            rowLabel.textContent = rowNumber ? `Baris ${rowNumber}` : 'Baris tidak diketahui';

            const rowMessage = document.createElement('p');
            rowMessage.className = 'mt-1 whitespace-pre-wrap break-words text-sm font-mono text-rose-600';
            const errorMessages = {
              inconsistent_group: 'Data barang dengan nama sama harus memiliki kategori, satuan, spesifikasi, kondisi, dan kode yang konsisten.',
              duplicate_code: 'Kode barang sudah digunakan.',
              duplicate_item: 'Data barang dan lokasi ini sudah pernah diimpor.',
              duplicate_location: 'Lokasi barang yang sama tercantum lebih dari sekali.'
            };
            const message = Array.isArray(error) ?
              String(error[1] ?? '') :
              String(error?.message ?? errorMessages[error?.code] ?? error);
            rowMessage.textContent = message.includes('SQLSTATE[') ?
              'Kesalahan database saat memproses baris ini. Periksa data wajib dan format file.' :
              message;
            messageContainer.append(rowLabel, rowMessage);
            rowDiv.append(indicator, messageContainer);
            errorContainer.appendChild(rowDiv);
          });
        } else if (!fileError) {
          const emptyMessage = document.createElement('p');
          emptyMessage.className = 'text-sm italic text-gray-500';
          emptyMessage.textContent = 'Tidak ada detail error spesifik yang tercatat.';
          errorContainer.appendChild(emptyMessage);
        }

        loading.classList.add('hidden');
        content.classList.remove('hidden');
      } catch (error) {
        console.error('Gagal memuat detail log import:', error);
        loading.classList.add('hidden');
        modalError.textContent = 'Detail import gagal dimuat. Silakan coba lagi.';
        modalError.classList.remove('hidden');
      }
    }

    function closeLogDetailModal() {
      logDetailModal.classList.add('hidden');
      logDetailModal.setAttribute('aria-hidden', 'true');
      document.documentElement.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', function(event) {
      if (event.key === "Escape" && !logDetailModal.classList.contains('hidden')) {
        closeLogDetailModal();
      }
    });
  </script>
</x-layout.admin>
