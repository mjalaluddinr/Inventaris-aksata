<x-layout.admin title="Data Barang - InvenTrack" header-title="Data Barang"
  header-description="Inventaris barang dan informasi stok.">
  <div x-data="{
      searchQuery: @js($filters['search'] ?? ''),
      barangResultsUrl: @js(route('admin.barang')),
      filterRequest: null,
      filterError: '',
      filterLoading: false,
      searchTimer: null,
      createUrl: @js(route('admin.barang.store')),
      updateUrlTemplate: @js(route('admin.barang.update', ['barang' => '__BARANG__'])),
      importUrl: @js(route('admin.barang.import')),
      formAction: '',
      importOpen: false,
      importLoading: false,
      importFileName: '',
      importFileSize: '',
      importError: '',
      importResult: '',
      importErrors: [],
      importErrorsTruncated: false,
      modalOpen: @js($errors->any() && in_array(old('barang_form'), ['create', 'edit'], true)),
      modalMode: @js(old('barang_form') === 'edit' ? 'edit' : 'create'),
      currentItem: {
          barang_id: @js(old('barang_id', '')),
          kategori_id: @js(old('kategori_id', '')),
          ruangan_id: @js(old('ruangan_id', '')),
          lemari_ids: @js(is_array(old('lemari_id')) ? array_values(old('lemari_id')) : []),
          kode_barang: @js(old('kode_barang', '')),
          nama_barang: @js(old('nama_barang', '')),
          satuan: @js(old('satuan', 'unit')),
          stok_total: @js(old('stok_total', 0)),
          stok_tersedia: @js(old('stok_tersedia', 0)),
          kondisi: @js(old('kondisi', 'baru')),
          kelayakan: @js(old('kelayakan', 'layak')),
          spesifikasi: @js(old('spesifikasi', ''))
      },
      lemariOptions: @js($lemaris->map(fn ($lemari) => [
          'lemari_id' => $lemari->lemari_id,
          'ruangan_id' => $lemari->ruangan_id,
          'nama_lemari' => $lemari->nama_lemari
      ])->values()),
      deleteModalOpen: false,
      deleteLoading: false,
      pendingDeleteForm: null,
      pendingDeleteName: '',
      feedbackType: @js(session()->has('error') ? 'error' : 'success'),
      feedbackMessage: @js(session('success') ?? (session('error') ?? ($errors->any() ? $errors->first() : ''))),
      feedbackBarangName: @js(session('barang_feedback_name', '')),
      feedbackBarangAction: @js(session('barang_feedback_action', '')),
      formSubmitting: false,
      init() {
          if (@js($errors->any())) {
              this.feedbackType = 'error';
              this.feedbackMessage = @js($errors->first());
          }
          this.formAction = this.modalMode === 'edit' && this.currentItem.barang_id ?
              this.itemUpdateUrl(this.currentItem.barang_id) :
              this.createUrl;
          if (this.feedbackMessage || this.feedbackBarangName) this.notifyFeedback();
      },
      notifyFeedback() {
          const message = this.feedbackBarangName ?
              [
                  { text: 'Barang ' },
                  { text: this.feedbackBarangName, bold: true },
                  { text: ` berhasil ${this.feedbackBarangAction}.` }
              ] :
              this.feedbackMessage;
          window.showToast(message, this.feedbackType);
      },
      itemUpdateUrl(id) {
          return this.updateUrlTemplate.replace('__BARANG__', encodeURIComponent(id));
      },
      matchesBarang(searchableText) {
          const searchable = searchableText.toLocaleLowerCase();
          const searchTerms = this.searchQuery.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
          return searchTerms.every(term => searchable.includes(term));
      },
      async filterBarang(url = null) {
          window.clearTimeout(this.searchTimer);
          this.searchTimer = null;
          if (this.filterRequest) this.filterRequest.abort();

          const request = new AbortController();
          this.filterRequest = request;
          this.filterLoading = true;
          this.filterError = '';
          const requestUrl = new URL(url || this.barangResultsUrl, window.location.href);

          if (!url) {
              const params = new URLSearchParams();
              for (const [key, value] of new FormData(this.$refs.barangFilterForm)) {
                  if (typeof value === 'string' && value.trim() !== '') params.set(key, value.trim());
              }
              requestUrl.search = params.toString();
          }

          try {
              const response = await fetch(requestUrl, {
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                  signal: request.signal,
              });
              if (!response.ok || response.redirected) throw new Error('Daftar barang tidak dapat dimuat.');

              this.$refs.barangResults.innerHTML = await response.text();
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.filterError = error.message || 'Daftar barang tidak dapat dimuat.';
                  this.$refs.barangResults.querySelectorAll('[data-barang-row]').forEach(row => row.hidden = false);
              }
          } finally {
              if (this.filterRequest === request) {
                  this.filterLoading = false;
                  this.filterRequest = null;
              }
          }
      },
      scheduleBarangSearch() {
          const rows = Array.from(this.$refs.barangResults.querySelectorAll('[data-barang-row]'));
          rows.forEach(row => row.hidden = !this.matchesBarang(row.dataset.searchable || ''));
          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => this.filterBarang(), 100);
      },
      handleBarangPagination(event) {
          const target = event.target instanceof Element ? event.target : null;
          const link = target?.closest('a');
          if (!link || !this.$refs.barangResults.contains(link)) return;
          event.preventDefault();
          this.filterBarang(link.href);
      },
      async toggleBarangStatus(event) {
          const form = event.currentTarget;
          const button = form.querySelector('button[type=submit]');
          const statusInput = form.querySelector('[data-next-status]');
          const status = statusInput.value;
          button.disabled = true;

          try {
              const response = await fetch(form.action, {
                  method: 'POST',
                  headers: { 'Accept': 'application/json' },
                  body: new FormData(form)
              });
              if (!response.ok || !response.redirected) {
                  throw new Error('Status barang gagal diperbarui.');
              }

              const condition = statusInput.name === 'kondisi';
              const statusLabel = button.querySelector('[data-status-label]');
              const statusPing = button.querySelector('[data-status-ping]');
              const statusDot = button.querySelector('[data-status-dot]');
              const isPositive = condition ? status === 'baru' : status === 'layak';
              const statusClasses = isPositive ? ['bg-emerald-100', 'text-emerald-800'] : ['bg-rose-100', 'text-rose-800'];
              const dotClasses = isPositive ? ['bg-emerald-400', 'bg-emerald-500'] : ['bg-rose-400', 'bg-rose-500'];

              statusLabel.textContent = condition ? (status === 'baru' ? 'Baru' : 'Lama') : (status === 'layak' ? 'Layak' : 'Tidak Layak');
              button.classList.remove('bg-emerald-100', 'text-emerald-800', 'bg-rose-100', 'text-rose-800');
              button.classList.add(...statusClasses);
              statusPing.classList.remove('bg-emerald-400', 'bg-rose-400');
              statusPing.classList.add(dotClasses[0]);
              statusDot.classList.remove('bg-emerald-500', 'bg-rose-500');
              statusDot.classList.add(dotClasses[1]);
              statusInput.value = condition ? (status === 'baru' ? 'lama' : 'baru') : (status === 'layak' ? 'tidak_layak' : 'layak');
              this.feedbackType = 'success';
              this.feedbackBarangName = '';
              this.feedbackMessage = 'Status barang berhasil diperbarui.';
              this.notifyFeedback();
          } catch (error) {
              this.feedbackType = 'error';
              this.feedbackBarangName = '';
              this.feedbackMessage = error.message || 'Status barang gagal diperbarui.';
              this.notifyFeedback();
          } finally {
              button.disabled = false;
          }
      },
      createItem() {
          this.modalMode = 'create';
          this.formSubmitting = false;
          this.currentItem = {
              barang_id: '',
              kategori_id: '',
              ruangan_id: '',
              lemari_ids: [],
              kode_barang: '',
              nama_barang: '',
              satuan: 'unit',
              stok_total: 0,
              stok_tersedia: 0,
              kondisi: 'baru',
              kelayakan: 'layak',
              spesifikasi: ''
          };
          this.formAction = this.createUrl;
          this.modalOpen = true;
      },
      setRuangan(ruanganId) {
          this.currentItem.ruangan_id = ruanganId;
          this.currentItem.lemari_ids = [];
      },
      editItem(item) {
          this.modalMode = 'edit';
          this.formSubmitting = false;
          this.currentItem = item;
          this.formAction = this.itemUpdateUrl(item.barang_id);
          this.modalOpen = true;
      },
      requestDelete(form) {
          if (this.deleteLoading) return;
          this.pendingDeleteForm = form;
          this.pendingDeleteName = form.dataset.itemName || '';
          this.deleteModalOpen = true;
      },
      cancelDelete() {
          if (this.deleteLoading) return;
          this.deleteModalOpen = false;
          this.pendingDeleteForm = null;
          this.pendingDeleteName = '';
      },
      async confirmDelete() {
          if (!this.pendingDeleteForm || this.deleteLoading) return;

          this.deleteLoading = true;

          try {
              const response = await fetch(this.pendingDeleteForm.action, {
                  method: 'POST',
                  headers: {
                      'Accept': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest'
                  },
                  body: new FormData(this.pendingDeleteForm)
              });
              let result;
              try {
                  result = await response.json();
              } catch {
                  throw new Error('Respons penghapusan tidak dapat dibaca.');
              }

              if (!response.ok) {
                  window.showToast(
                      typeof result?.message === 'string' ?
                          result.message :
                          'Barang gagal dihapus. Coba lagi nanti.',
                      'error'
                  );
                  return;
              }

              window.location.reload();
          } catch {
              window.showToast('Barang gagal dihapus. Periksa koneksi lalu coba lagi.', 'error');
          } finally {
              this.deleteLoading = false;
              this.cancelDelete();
          }
      },
      closeModal() {
          this.modalOpen = false;
      },
      handleEscape() {
          this.modalOpen = false;
          this.importOpen = false;
          this.cancelDelete();
      },
      selectImportFile(files = this.$refs.barangImportFile.files) {
          const file = files[0];
          this.importFileName = file?.name || '';
          this.importFileSize = file ?
              file.size < 1024 ?
              `${file.size} B` :
              `${(file.size / 1024).toFixed(1)} KB` :
              '';
          this.importError = '';
          this.importResult = '';
          this.importErrors = [];
          this.importErrorsTruncated = false;
      },
      clearImportFile() {
          this.$refs.barangImportFile.value = '';
          this.selectImportFile([]);
      },
      openImportFilePicker() {
          this.$refs.barangImportFile.click();
      },
      handleImportFileKeydown(event) {
          if (event.target !== event.currentTarget) return;
          if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              this.openImportFilePicker();
          }
      },
      handleImportDrop(event) {
          const files = event.dataTransfer.files;
          if (!files.length) return;
          const transfer = new DataTransfer();
          transfer.items.add(files[0]);
          this.$refs.barangImportFile.files = transfer.files;
          this.selectImportFile(transfer.files);
      },
      closeImport() {
          if (this.importLoading) return;
          this.importOpen = false;
      },
      async submitBarangImport() {
          if (this.importLoading) return;

          this.importLoading = true;
          this.importError = '';
          this.importResult = '';
          this.importErrors = [];
          this.importErrorsTruncated = false;

          try {
              const response = await fetch(this.importUrl, {
                  method: 'POST',
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                  body: new FormData(this.$refs.barangImportForm)
              });
              let result;
              try {
                  result = await response.json();
              } catch {
                  throw new Error('Server tidak mengirim hasil impor yang valid.');
              }

              if (!response.ok) {
                  this.importError = result.errors?.file?.[0] ||
                      (response.status === 422 ? result.message : 'Import gagal diproses. Coba lagi nanti.');
                  return;
              }

              this.importResult = result.message;
              this.importErrors = result.errors || [];
              this.importErrorsTruncated = Boolean(result.errors_truncated);
              this.feedbackType = result.failed_rows > 0 ? 'error' : 'success';
              this.feedbackMessage = result.message;
              this.notifyFeedback();
              window.location.assign(@js(route('admin.barang')));
          } catch (error) {
              this.importError = error.message || 'File tidak dapat diimpor. Periksa koneksi lalu coba lagi.';
          } finally {
              this.importLoading = false;
          }
      }
  }" @keydown.escape.window="handleEscape()"
    class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-10rem)] bg-[#E8F8F4]">
    <div class="space-y-4 p-4 sm:p-8">
      <form method="GET" action="{{ route('admin.barang') }}" x-ref="barangFilterForm"
        @submit.prevent="filterBarang()" class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          @if(auth()->user()?->canManageInventory())
          <div class="flex w-full flex-wrap items-center gap-2">
            <button type="button" @click="createItem()"
              class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2">
              <iconify-icon icon="mdi:plus" width="18" aria-hidden="true"></iconify-icon>
              Tambah Barang
            </button>
            <button type="button" @click="importOpen = true"
              class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2">
              <iconify-icon icon="mdi:file-excel" class="text-emerald-600" width="18"
                aria-hidden="true"></iconify-icon>
              Import Barang
            </button>
          </div>
          @endif
          <label class="relative block w-full sm:ml-auto sm:max-w-xs">
            <span class="sr-only">Cari barang</span>
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
              <iconify-icon icon="mdi:magnify" width="17" aria-hidden="true"></iconify-icon>
            </span>
            <input type="search" name="search" x-model="searchQuery"
              @input="scheduleBarangSearch()" @keydown.enter.prevent="filterBarang()" placeholder="Cari barang..."
              class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
          </label>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-6">
          <label>
            <span class="sr-only">Filter kategori</span>
            <select name="kategori_id" @change="filterBarang()"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              <option value="">Semua kategori</option>
              @foreach ($kategoris as $kategori)
                <option value="{{ $kategori->kategori_id }}" @selected(($filters['kategori_id'] ?? '') === $kategori->kategori_id)>
                  {{ $kategori->nama_kategori }}
                </option>
              @endforeach
            </select>
          </label>
          <label>
            <span class="sr-only">Filter kelayakan</span>
            <select name="kelayakan" @change="filterBarang()"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              <option value="">Semua kelayakan</option>
              <option value="layak" @selected(($filters['kelayakan'] ?? '') === 'layak')>Layak</option>
              <option value="tidak_layak" @selected(($filters['kelayakan'] ?? '') === 'tidak_layak')>Tidak layak</option>
            </select>
          </label>
          <label>
            <span class="sr-only">Filter ruangan</span>
            <select name="ruangan_id" @change="filterBarang()"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              <option value="">Semua ruangan</option>
              @foreach ($ruangans as $ruangan)
                <option value="{{ $ruangan->ruangan_id }}" @selected(($filters['ruangan_id'] ?? '') === $ruangan->ruangan_id)>
                  {{ $ruangan->nama_ruangan }}
                </option>
              @endforeach
            </select>
          </label>
          <label>
            <span class="sr-only">Filter lemari</span>
            <select name="lemari_id" @change="filterBarang()"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              <option value="">Semua lemari</option>
              @foreach ($lemaris as $lemari)
                <option value="{{ $lemari->lemari_id }}" @selected(($filters['lemari_id'] ?? '') === $lemari->lemari_id)>
                  {{ $lemari->nama_lemari }}
                </option>
              @endforeach
            </select>
          </label>
          <label>
            <span class="sr-only">Filter kondisi</span>
            <select name="kondisi" @change="filterBarang()"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              <option value="">Semua kondisi</option>
              <option value="baru" @selected(($filters['kondisi'] ?? '') === 'baru')>Baru</option>
              <option value="lama" @selected(($filters['kondisi'] ?? '') === 'lama')>Lama</option>
            </select>
          </label>
        </div>
      </form>

      <div x-cloak x-show="filterError" class="flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800" role="alert">
        <span x-text="filterError"></span>
        <button type="button" @click="filterBarang()" class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">
          Coba lagi
        </button>
      </div>
      <div x-ref="barangResults" @click="handleBarangPagination($event)" :aria-busy="filterLoading.toString()">
        @fragment('barang-results')
        <section class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm">
          <div class="theme-scrollbar overflow-x-auto">
            <table class="min-w-full w-full divide-y divide-[#DCE9E6]">
              <thead class="bg-[#F1F3F4]">
                <tr>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Barang</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Spesifikasi</th>
                  <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-700">
                    Stok tersedia/total</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Kategori</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Kondisi</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Kelayakan</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Lokasi</th>
                  @if(auth()->user()?->canManageInventory())
                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-slate-700">Aksi</th>
                  @endif
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E7EFED] bg-white">
                @forelse ($barangs as $barang)
                  @php
                    $barangCondition = strtolower(trim((string) $barang->kondisi));
                    $barangEligibility = str_replace([' ', '-'], '_', strtolower(trim((string) $barang->kelayakan)));
                  @endphp
                  {{-- ASVS V1.2.1: Escape searchable values in the HTML attribute context. --}}
                  <tr data-barang-row
                    data-searchable="{{ mb_strtolower(trim($barang->kode_barang . ' ' . $barang->nama_barang . ' ' . ($barang->spesifikasi ?? ''))) }}"
                    x-show="matchesBarang($el.dataset.searchable)"
                    class="transition-colors hover:bg-[#F7FBFA]">
                    <td class="max-w-56 px-4 py-3">
                      <p class="truncate text-sm font-semibold text-slate-800">{{ $barang->nama_barang }}</p>
                      <p class="mt-0.5 text-xs text-slate-500">{{ $barang->kode_barang }}</p>
                    </td>
                    <td class="max-w-64 px-4 py-3 text-xs leading-5 text-slate-600">
                      @if ($barang->spesifikasi)
                        <p class="line-clamp-2">{{ $barang->spesifikasi }}</p>
                      @else
                        <span class="text-slate-400">-</span>
                      @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                      <p class="text-sm font-medium text-slate-800">
                        {{ $barang->stok_tersedia }}/{{ $barang->stok_total }} {{ $barang->satuan }}
                      </p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $barang->kategori?->nama_kategori ?? '-' }}</td>
                    <td class="px-4 py-3">
                      @if(auth()->user()?->canManageInventory())
                      <form action="{{ route('admin.barang.update', $barang->barang_id) }}" method="POST"
                        @submit.prevent="toggleBarangStatus($event)">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="barang_form" value="status">
                        <input type="hidden" name="barang_id" value="{{ $barang->barang_id }}">
                        <input type="hidden" name="kategori_id" value="{{ $barang->kategori_id }}">
                        <input type="hidden" name="kode_barang" value="{{ $barang->kode_barang }}">
                        <input type="hidden" name="nama_barang" value="{{ $barang->nama_barang }}">
                        <input type="hidden" name="satuan" value="{{ $barang->satuan }}">
                        <input type="hidden" name="stok_total" value="{{ $barang->stok_total }}">
                        <input type="hidden" name="stok_tersedia" value="{{ $barang->stok_tersedia }}">
                        <input type="hidden" name="kelayakan" value="{{ $barangEligibility }}">
                        <input type="hidden" name="spesifikasi" value="{{ $barang->spesifikasi }}">
                        <input type="hidden" name="kondisi" data-next-status
                          value="{{ $barangCondition === 'baru' ? 'lama' : 'baru' }}">
                        <button type="submit"
                          class="inline-flex w-24 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium transition-colors duration-200 hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-[#08A9D6] disabled:cursor-wait disabled:opacity-60 {{ $barangCondition === 'baru' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                          <span class="relative flex h-2 w-2 shrink-0">
                            <span data-status-ping
                              class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 {{ $barangCondition === 'baru' ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                            <span data-status-dot
                              class="relative inline-flex h-2 w-2 rounded-full {{ $barangCondition === 'baru' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                          </span>
                          <span data-status-label>{{ ucfirst($barangCondition) }}</span>
                        </button>
                      </form>
                      @else
                        <span class="inline-flex w-24 items-center justify-center rounded-md px-2 py-1 text-xs font-medium {{ $barangCondition === 'baru' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                          {{ ucfirst($barangCondition) }}
                        </span>
                      @endif
                    </td>
                    <td class="px-4 py-3">
                      @if(auth()->user()?->canManageInventory())
                      <form action="{{ route('admin.barang.update', $barang->barang_id) }}" method="POST"
                        @submit.prevent="toggleBarangStatus($event)">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="barang_form" value="status">
                        <input type="hidden" name="barang_id" value="{{ $barang->barang_id }}">
                        <input type="hidden" name="kategori_id" value="{{ $barang->kategori_id }}">
                        <input type="hidden" name="kode_barang" value="{{ $barang->kode_barang }}">
                        <input type="hidden" name="nama_barang" value="{{ $barang->nama_barang }}">
                        <input type="hidden" name="satuan" value="{{ $barang->satuan }}">
                        <input type="hidden" name="stok_total" value="{{ $barang->stok_total }}">
                        <input type="hidden" name="stok_tersedia" value="{{ $barang->stok_tersedia }}">
                        <input type="hidden" name="kondisi" value="{{ $barangCondition }}">
                        <input type="hidden" name="spesifikasi" value="{{ $barang->spesifikasi }}">
                        <input type="hidden" name="kelayakan" data-next-status
                          value="{{ $barangEligibility === 'layak' ? 'tidak_layak' : 'layak' }}">
                        <button type="submit"
                          class="ml-1 inline-flex w-28 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-1 text-xs font-medium transition-colors duration-200 hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-[#08A9D6] disabled:cursor-wait disabled:opacity-60 {{ $barangEligibility === 'layak' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                          <span class="relative flex h-2 w-2 shrink-0">
                            <span data-status-ping
                              class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 {{ $barangEligibility === 'layak' ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                            <span data-status-dot
                              class="relative inline-flex h-2 w-2 rounded-full {{ $barangEligibility === 'layak' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                          </span>
                          <span data-status-label>{{ $barangEligibility === 'layak' ? 'Layak' : 'Tidak Layak' }}</span>
                        </button>
                      </form>
                      @else
                        <span class="ml-1 inline-flex w-28 items-center justify-center rounded-md px-3 py-1 text-xs font-medium {{ $barangEligibility === 'layak' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                          {{ $barangEligibility === 'layak' ? 'Layak' : 'Tidak Layak' }}
                        </span>
                      @endif
                    </td>
                    <td class="min-w-max whitespace-nowrap px-4 py-3 text-xs leading-5 text-slate-600">
                      @forelse ($barang->lokasiBarangs as $lokasi)
                        <p class="whitespace-nowrap">
                          {{ $lokasi->ruangan?->nama_ruangan ?? 'Ruangan tidak ditemukan' }}
                          @if ($lokasi->lemari)
                            <span class="px-1 font-semibold text-emerald-600" aria-hidden="true">›</span>
                            {{ $lokasi->lemari->nama_lemari }}
                          @endif
                        </p>
                      @empty
                        <span class="text-slate-400">Belum ditempatkan</span>
                      @endforelse
                    </td>
                    @if(auth()->user()?->canManageInventory())
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                      <div class="flex justify-end gap-2">
                        <button type="button"
                          @click="editItem({
                            barang_id: $el.dataset.barangId,
                            kategori_id: $el.dataset.kategoriId,
                            ruangan_id: $el.dataset.ruanganId,
                            lemari_ids: $el.dataset.lemariIds ? $el.dataset.lemariIds.split(',') : [],
                            kode_barang: $el.dataset.kodeBarang,
                            nama_barang: $el.dataset.namaBarang,
                            satuan: $el.dataset.satuan,
                            stok_total: Number($el.dataset.stokTotal),
                            stok_tersedia: Number($el.dataset.stokTersedia),
                            kondisi: $el.dataset.kondisi,
                            kelayakan: $el.dataset.kelayakan,
                            spesifikasi: $el.dataset.spesifikasi
                        })"
                          data-barang-id="{{ $barang->barang_id }}" data-kategori-id="{{ $barang->kategori_id }}"
                          data-kode-barang="{{ $barang->kode_barang }}"
                          data-nama-barang="{{ $barang->nama_barang }}" data-satuan="{{ $barang->satuan }}"
                          data-stok-total="{{ $barang->stok_total }}"
                          data-stok-tersedia="{{ $barang->stok_tersedia }}" data-kondisi="{{ $barangCondition }}"
                          data-ruangan-id="{{ $barang->lokasiBarangs->first()?->ruangan_id }}"
                          data-lemari-ids="{{ $barang->lokasiBarangs
                              ->where('ruangan_id', $barang->lokasiBarangs->first()?->ruangan_id)
                              ->pluck('lemari_id')->filter()->implode(',') }}"
                          data-kelayakan="{{ $barangEligibility }}" data-spesifikasi="{{ $barang->spesifikasi }}"
                          class="inline-flex min-h-8 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                          <iconify-icon icon="mdi:pencil-outline" width="15" aria-hidden="true"></iconify-icon>
                          Edit
                        </button>
                        <form action="{{ route('admin.barang.destroy', $barang->barang_id) }}" method="POST"
                          data-item-name="{{ $barang->nama_barang }}" @submit.prevent="requestDelete($el)">
                          @csrf
                          @method('DELETE')
                          <button type="submit"
                            class="inline-flex min-h-8 items-center gap-1 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                            <iconify-icon icon="mdi:delete-outline" width="15" aria-hidden="true"></iconify-icon>
                            Hapus
                          </button>
                        </form>
                      </div>
                    </td>
                    @endif
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="px-6 py-14 text-center">
                      @if (filled($filters['search'] ?? null) ||
                              filled($filters['kategori_id'] ?? null) ||
                              filled($filters['ruangan_id'] ?? null) ||
                              filled($filters['lemari_id'] ?? null) ||
                              filled($filters['kondisi'] ?? null) ||
                              filled($filters['kelayakan'] ?? null))
                        @php
                          $activeBarangFilters = array_filter([
                              'kategori' => $kategoris->firstWhere('kategori_id', $filters['kategori_id'] ?? null)
                                  ?->nama_kategori,
                              'ruangan' => $ruangans->firstWhere('ruangan_id', $filters['ruangan_id'] ?? null)
                                  ?->nama_ruangan,
                              'lemari' => $lemaris->firstWhere('lemari_id', $filters['lemari_id'] ?? null)
                                  ?->nama_lemari,
                              'kondisi' => filled($filters['kondisi'] ?? null) ? ucfirst($filters['kondisi']) : null,
                              'kelayakan' => match ($filters['kelayakan'] ?? null) {
                                  'layak' => 'Layak',
                                  'tidak_layak' => 'Tidak layak',
                                  default => null,
                              },
                          ]);
                        @endphp
                        <iconify-icon icon="mdi:package-variant-closed" class="mb-2 text-slate-300" width="42"
                          aria-hidden="true"></iconify-icon>
                        <p class="text-sm font-semibold text-slate-700">
                          Tidak ada barang yang cocok
                          @if (filled($filters['search'] ?? null))
                            dengan kata kunci <strong class="font-bold text-slate-950">"{{ $filters['search'] }}"</strong>
                          @endif
                          @if (count($activeBarangFilters) > 0)
                            @if (filled($filters['search'] ?? null))
                              dan
                            @else
                              dengan
                            @endif
                            @foreach ($activeBarangFilters as $label => $value)
                              @if (!$loop->first)
                                @if ($loop->last)
                                  dan
                                @else
                                  ,
                                @endif
                              @endif
                              {{ $label }} <strong class="font-bold text-slate-950">{{ $value }}</strong>
                            @endforeach
                          @endif.
                        </p>
                      @else
                        <iconify-icon icon="mdi:package-variant-closed" class="mb-2 text-slate-300" width="42"
                          aria-hidden="true"></iconify-icon>
                        <p class="text-sm font-medium text-slate-700">Belum ada barang</p>
                        <p class="mt-1 text-xs text-slate-500">Tambahkan barang untuk mulai mengelola inventaris.</p>
                      @endif
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div
            class="flex flex-col gap-3 border-t border-[#E7EFED] bg-[#FAFCFB] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="text-xs text-slate-500" aria-hidden="true"></span>
            @if ($barangs->hasPages())
              @php
                $paginationStart = max(1, min($barangs->currentPage() - 1, $barangs->lastPage() - 2));
                $paginationEnd = min($barangs->lastPage(), $paginationStart + 2);
              @endphp
              <nav aria-label="Navigasi halaman barang" class="flex items-center justify-end gap-2">
                @if (!$barangs->onFirstPage())
                  <a href="{{ $barangs->previousPageUrl() }}" aria-label="Halaman sebelumnya"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5]">
                    <iconify-icon icon="mdi:chevron-left" width="22"></iconify-icon>
                  </a>
                @endif
                @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
                  @if ($page === $barangs->currentPage())
                    <span aria-current="page"
                      class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-[#00CFA8] px-3 text-sm font-semibold text-slate-950">{{ $page }}</span>
                  @else
                    <a href="{{ $barangs->url($page) }}" aria-label="Halaman {{ $page }}"
                      class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white px-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5]">{{ $page }}</a>
                  @endif
                @endfor
                @if ($barangs->hasMorePages())
                  <a href="{{ $barangs->nextPageUrl() }}" aria-label="Halaman berikutnya"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5]">
                    <iconify-icon icon="mdi:chevron-right" width="22"></iconify-icon>
                  </a>
                @endif
              </nav>
            @endif
          </div>
        </section>
        @endfragment
      </div>
    </div>

    @if(auth()->user()?->canManageInventory())
    <template x-teleport="body">
      <div>
        <div x-cloak x-show="importOpen" x-transition.opacity
          class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/35 p-4 backdrop-blur-md sm:p-6"
          @click.self="closeImport()" role="presentation">
          <section x-show="importOpen" x-transition
            class="my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
            role="dialog" aria-modal="true" aria-labelledby="barang-import-title">
            <form x-ref="barangImportForm" @submit.prevent="submitBarangImport()">
              @csrf
              <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-3">
                  <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                    <iconify-icon icon="mdi:file-excel-outline" width="22" aria-hidden="true"></iconify-icon>
                  </span>
                  <div>
                    <h2 id="barang-import-title" class="text-base font-semibold text-slate-800">Import Data Barang</h2>
                    <p class="mt-1 text-xs text-slate-500">Pilih file untuk menambahkan beberapa barang sekaligus.</p>
                  </div>
                </div>
                <button type="button" @click="closeImport()" :disabled="importLoading"
                  class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Tutup formulir impor">
                  <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
                </button>
              </div>

              <div class="space-y-4 px-5 py-4">
                <div>
                  <label for="barang-import-file" class="mb-1 block text-sm font-medium text-slate-700">File Excel
                    atau CSV</label>
                  <input id="barang-import-file" x-ref="barangImportFile" name="file" type="file"
                    accept=".csv,.xls,.xlsx" required @change="selectImportFile($event.target.files)" class="hidden">
                  <div role="button" tabindex="0" @click="openImportFilePicker()"
                    @keydown="handleImportFileKeydown($event)" @dragover.prevent
                    @drop.prevent="handleImportDrop($event)"
                    class="group cursor-pointer rounded-lg border-2 border-dashed p-5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#08A9D6] focus-visible:ring-offset-2"
                    :class="importFileName ?
                        'border-emerald-300 bg-emerald-50/70 hover:border-emerald-400 hover:bg-emerald-50' :
                        'border-slate-300 bg-slate-50 text-center hover:border-[#08A9D6] hover:bg-[#F0FDFA]'">
                    <template x-if="!importFileName">
                      <div class="flex flex-col items-center">
                        <span
                          class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 transition group-hover:bg-emerald-200">
                          <iconify-icon icon="mdi:file-excel-outline" width="32"
                            aria-hidden="true"></iconify-icon>
                        </span>
                        <p class="text-sm font-semibold text-slate-800">Seret file Excel ke sini</p>
                        <p class="mt-1 text-sm text-slate-600">atau klik area ini untuk memilih file</p>
                        <p
                          class="mt-3 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                          .csv, .xls, .xlsx Â· Maks. 5 MB
                        </p>
                      </div>
                    </template>
                    <template x-if="importFileName">
                      <div class="flex items-center gap-3">
                        <span
                          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-200">
                          <iconify-icon icon="mdi:file-excel" width="26" aria-hidden="true"></iconify-icon>
                        </span>
                        <span class="min-w-0 flex-1">
                          <span class="block text-sm font-semibold text-slate-800">File Excel dipilih</span>
                          <span class="mt-1 block text-xs text-emerald-800">
                            File siap diimpor <span class="text-emerald-700" x-text="importFileSize"></span>
                          </span>
                          <span class="mt-1 block text-xs font-medium text-[#087F70]">Klik untuk mengganti file</span>
                        </span>
                        <button type="button" @click.stop="clearImportFile()"
                          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-400"
                          aria-label="Hapus file yang dipilih">
                          <iconify-icon icon="mdi:close-circle-outline" width="20"
                            aria-hidden="true"></iconify-icon>
                        </button>
                      </div>
                    </template>
                  </div>
                </div>

                <div class="rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
                  <div class="flex gap-2">
                    <iconify-icon icon="mdi:information" class="mt-0.5 shrink-0" width="16"
                      aria-hidden="true"></iconify-icon>
                    <div class="space-y-2">
                      <p class="font-semibold">Contoh Format Untuk Import Barang</p>
                      <a href="{{ asset('import-templates/format-import-barang.xlsx') }}" download
                        class="inline-flex items-center gap-1 font-semibold text-[#087E78] hover:text-[#065f59] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]">
                        <iconify-icon icon="mdi:download" width="16" aria-hidden="true"></iconify-icon>
                        Unduh format-import-barang.xlsx
                      </a>
                    </div>
                  </div>
                </div>

                <div x-cloak x-show="importError"
                  class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
                  <span x-text="importError"></span>
                </div>
                <div x-cloak x-show="importResult" class="rounded-lg border px-3 py-2 text-sm"
                  :class="importErrors.length ? 'border-amber-200 bg-amber-50 text-amber-900' :
                      'border-emerald-200 bg-emerald-50 text-emerald-800'"
                  role="status">
                  <p x-text="importResult"></p>
                  <ul x-show="importErrors.length"
                    class="mt-2 max-h-32 space-y-1 overflow-y-auto border-t border-emerald-200 pt-2 text-xs">
                    <template x-for="error in importErrors" :key="error.row">
                      <li x-text="`Baris ${error.row}: ${error.message}`"></li>
                    </template>
                    <li x-show="importErrorsTruncated">Hanya 20 kesalahan pertama yang ditampilkan.</li>
                  </ul>
                </div>
              </div>

              <div
                class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex justify-end gap-2">
                  <button type="button" @click="closeImport()" :disabled="importLoading"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:cursor-not-allowed disabled:opacity-50">
                    Batal
                  </button>
                  <button type="submit" :disabled="importLoading || !importFileName" :aria-busy="importLoading.toString()"
                    class="inline-flex items-center justify-center rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    <iconify-icon x-show="importLoading" icon="mdi:loading" class="animate-spin text-lg"
                      aria-hidden="true"></iconify-icon>
                    <iconify-icon x-show="!importLoading" icon="mdi:upload" width="17" aria-hidden="true"></iconify-icon>
                    <span x-show="!importLoading" class="ml-2">Mulai impor</span>
                    <span x-show="importLoading" class="sr-only">Sedang mengimpor...</span>
                  </button>
                </div>
              </div>
            </form>
          </section>
        </div>

        <div x-cloak x-show="modalOpen" x-transition.opacity
          class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
          @click.self="closeModal()" role="presentation">
          <div
            class="absolute inset-0 bg-[linear-gradient(135deg,rgba(7,59,86,.84),rgba(2,6,23,.76),rgba(6,78,72,.78))] backdrop-blur-md"
            @click="closeModal()"></div>
          <section x-show="modalOpen" x-transition
            class="relative z-10 my-auto w-full max-w-2xl overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
            role="dialog" aria-modal="true" aria-labelledby="barang-modal-title">
            <form :action="formAction" method="POST" @submit="formSubmitting = true">
              @csrf
              <input type="hidden" name="_method" value="PUT" :disabled="modalMode !== 'edit'">
              <input type="hidden" name="barang_form" :value="modalMode">
              <input type="hidden" name="barang_id" x-model="currentItem.barang_id">
              <input type="hidden" name="kondisi" x-model="currentItem.kondisi"
                :disabled="modalMode !== 'edit'">
              <input type="hidden" name="kelayakan" x-model="currentItem.kelayakan"
                :disabled="modalMode !== 'edit'">

              <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-3">
                  <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                    <iconify-icon
                      :icon="modalMode === 'create' ? 'mdi:package-variant-plus' : 'mdi:package-variant'"
                      width="22" aria-hidden="true"></iconify-icon>
                  </span>
                  <div>
                    <h2 id="barang-modal-title" class="text-base font-semibold text-slate-800"
                      x-text="modalMode === 'create' ? 'Tambah Barang' : 'Edit Barang'"></h2>
                    <p class="mt-1 text-xs text-slate-500">Isi informasi sesuai data barang dan stok saat ini.</p>
                  </div>
                </div>
                <button type="button" @click="closeModal()"
                  class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400"
                  aria-label="Tutup formulir">
                  <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
                </button>
              </div>

              <div class="grid max-h-[calc(100vh-12rem)] grid-cols-1 gap-4 overflow-y-auto px-5 py-4 sm:grid-cols-2">
                <div :class="modalMode === 'create' ? 'sm:col-span-2' : ''">
                  <label for="nama_barang" class="mb-1 block text-xs font-medium text-slate-700">Nama Barang
                    <span x-show="modalMode === 'create' && !String(currentItem.nama_barang ?? '').trim()"
                      class="text-rose-500">*</span></label>
                  <input id="nama_barang" name="nama_barang" type="text" maxlength="150" required
                    x-model="currentItem.nama_barang"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                    placeholder="Nama barang">
                  @error('nama_barang')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div x-show="modalMode === 'edit'">
                  <label for="kode_barang" class="mb-1 block text-xs font-medium text-slate-700">Kode Barang</label>
                  <input id="kode_barang" name="kode_barang" type="text" maxlength="30"
                    x-model="currentItem.kode_barang" autocomplete="off" :required="modalMode === 'edit'"
                    :disabled="modalMode !== 'edit'"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                    placeholder="BRG-001">
                  @error('kode_barang')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div>
                  <label for="kategori_id" class="mb-1 block text-xs font-medium text-slate-700">Kategori
                    <span x-show="modalMode === 'create' && !currentItem.kategori_id"
                      class="text-rose-500">*</span></label>
                  <select id="kategori_id" name="kategori_id" x-model="currentItem.kategori_id" required
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                    <option value="">Pilih kategori</option>
                    @foreach ($kategoris as $kategori)
                      <option value="{{ $kategori->kategori_id }}">{{ $kategori->nama_kategori }}</option>
                    @endforeach
                  </select>
                  @error('kategori_id')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div>
                  <label for="ruangan_id" class="mb-1 block text-xs font-medium text-slate-700">Ruangan
                    <span x-show="modalMode === 'create' && !currentItem.ruangan_id"
                      class="text-rose-500">*</span></label>
                  <select id="ruangan_id" name="ruangan_id" x-model="currentItem.ruangan_id"
                    @change="setRuangan($event.target.value)" required
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                    <option value="">Pilih ruangan</option>
                    @foreach ($ruangans as $ruangan)
                      <option value="{{ $ruangan->ruangan_id }}">{{ $ruangan->nama_ruangan }}</option>
                    @endforeach
                  </select>
                  @error('ruangan_id')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div>
                  <span id="lemari-label" class="mb-1 block text-xs font-medium text-slate-700">Lemari</span>
                  <div role="group" aria-labelledby="lemari-label"
                    class="max-h-44 space-y-2 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-2">
                    <template x-for="lemari in lemariOptions.filter((item) => item.ruangan_id === currentItem.ruangan_id)"
                      :key="lemari.lemari_id">
                      <label class="flex cursor-pointer items-center gap-3 rounded-md border border-transparent bg-white px-3 py-2.5 text-sm text-slate-700 transition hover:border-[#08A9D6]/40 hover:bg-[#F0FDFA] has-[:checked]:border-[#08A9D6] has-[:checked]:bg-[#F0FDFA]">
                        <input type="checkbox" name="lemari_id[]" :value="lemari.lemari_id"
                          x-model="currentItem.lemari_ids" :disabled="!currentItem.ruangan_id"
                          class="h-4 w-4 rounded border-slate-300 text-[#008f7e] focus:ring-[#08A9D6]">
                        <span class="min-w-0 flex-1 truncate" x-text="lemari.nama_lemari"></span>
                        <span x-show="currentItem.lemari_ids.includes(lemari.lemari_id)"
                          class="text-xs font-semibold text-[#087F70]">Dipilih</span>
                      </label>
                    </template>
                    <p x-show="!currentItem.ruangan_id" class="px-2 py-2 text-center text-xs text-slate-500">
                      Pilih ruangan untuk melihat lemari.
                    </p>
                    <p x-show="currentItem.ruangan_id && !lemariOptions.some((item) => item.ruangan_id === currentItem.ruangan_id)"
                      class="px-2 py-2 text-xs text-slate-500">
                      Belum ada lemari di ruangan ini.
                    </p>
                  </div>
                  <p x-show="currentItem.lemari_ids.length > 0"
                    x-text="currentItem.lemari_ids.length + ' lemari dipilih'"
                    class="mt-1 text-xs font-medium text-[#087F70]"></p>
                  @error('lemari_id.*')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div x-show="modalMode === 'create'" class="order-3">
                  <label for="create_kondisi" class="mb-1 block text-xs font-medium text-slate-700">Kondisi
                    <span x-show="modalMode === 'create' && !currentItem.kondisi"
                      class="text-rose-500">*</span></label>
                  <select id="create_kondisi" name="kondisi" x-model="currentItem.kondisi"
                    :disabled="modalMode !== 'create'" required
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                    <option value="baru">Baru</option>
                    <option value="lama">Lama</option>
                  </select>
                  @error('kondisi')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div x-show="modalMode === 'create'" class="order-4">
                  <label for="create_kelayakan" class="mb-1 block text-xs font-medium text-slate-700">Kelayakan
                    <span x-show="modalMode === 'create' && !currentItem.kelayakan"
                      class="text-rose-500">*</span></label>
                  <select id="create_kelayakan" name="kelayakan" x-model="currentItem.kelayakan"
                    :disabled="modalMode !== 'create'" required
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                    <option value="layak">Layak</option>
                    <option value="tidak_layak">Tidak Layak</option>
                  </select>
                  @error('kelayakan')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div class="order-1">
                  <label for="stok_total" class="mb-1 block text-xs font-medium text-slate-700">Jumlah Total
                    <span x-show="modalMode === 'create' && String(currentItem.stok_total ?? '').trim() === ''"
                      class="text-rose-500">*</span></label>
                  <input id="stok_total" name="stok_total" type="number" min="0" step="1" required
                    x-model.number="currentItem.stok_total"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                  @error('stok_total')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div class="order-2">
                  <label for="stok_tersedia" class="mb-1 block text-xs font-medium text-slate-700">Stok Tersedia
                    <span x-show="modalMode === 'create' && String(currentItem.stok_tersedia ?? '').trim() === ''"
                      class="text-rose-500">*</span></label>
                  <input id="stok_tersedia" name="stok_tersedia" type="number" min="0" step="1"
                    required x-model.number="currentItem.stok_tersedia"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                  @error('stok_tersedia')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div class="order-5">
                  <label for="satuan" class="mb-1 block text-xs font-medium text-slate-700">Satuan
                    <span x-show="modalMode === 'create' && !String(currentItem.satuan ?? '').trim()"
                      class="text-rose-500">*</span></label>
                  <input id="satuan" name="satuan" type="text" maxlength="20" required
                    x-model="currentItem.satuan"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                    placeholder="unit">
                  @error('satuan')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
                <div class="sm:col-span-2">
                  <label for="spesifikasi" class="mb-1 block text-xs font-medium text-slate-700">Spesifikasi</label>
                  <textarea id="spesifikasi" name="spesifikasi" maxlength="255" rows="3" x-model="currentItem.spesifikasi"
                    class="w-full resize-y rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                    placeholder="Keterangan teknis barang"></textarea>
                  @error('spesifikasi')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                  @enderror
                </div>
              </div>

              <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                <button type="button" @click="closeModal()"
                  class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">
                  Batal
                </button>
                <button type="submit" :disabled="formSubmitting" :aria-busy="formSubmitting.toString()"
                  class="inline-flex items-center justify-center rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                  <iconify-icon x-show="formSubmitting" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
                  <iconify-icon x-show="!formSubmitting"
                    :icon="modalMode === 'create' ? 'mdi:content-save-plus-outline' : 'mdi:content-save-edit-outline'"
                    width="17" aria-hidden="true"></iconify-icon>
                  <span x-show="!formSubmitting" class="ml-2" x-text="modalMode === 'create' ? 'Simpan Barang' : 'Simpan Perubahan'"></span>
                  <span x-show="formSubmitting" class="sr-only">Sedang menyimpan...</span>
                </button>
              </div>
            </form>
          </section>
        </div>

        <div x-cloak x-show="deleteModalOpen" x-transition.opacity
          class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
          role="alertdialog" aria-modal="true" aria-labelledby="barang-delete-title"
          aria-describedby="barang-delete-description">
          <div class="absolute inset-0 bg-slate-900/35 backdrop-blur-md" @click="cancelDelete()"></div>
          <section x-show="deleteModalOpen" x-transition
            class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">
            <div class="p-6 text-center">
              <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100">
                <iconify-icon icon="mdi:alert-circle" class="text-rose-600" width="28"
                  aria-hidden="true"></iconify-icon>
              </div>
              <h2 id="barang-delete-title" class="text-lg font-semibold text-slate-900">Hapus barang?</h2>
              <p id="barang-delete-description" class="mt-2 text-sm text-slate-600">
                Barang <strong class="font-semibold text-slate-800" x-text="pendingDeleteName"></strong> akan dihapus.
                Data yang memiliki riwayat lokasi atau transaksi tetap terlindungi.
              </p>
            </div>
            <div class="flex justify-center gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
              <button type="button" @click="cancelDelete()"
                :disabled="deleteLoading"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">
                Batal
              </button>
              <button type="button" @click="confirmDelete()"
                :disabled="deleteLoading" :aria-busy="deleteLoading"
                class="inline-flex min-w-28 items-center justify-center gap-2 rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                <iconify-icon x-show="deleteLoading" icon="mdi:loading" class="animate-spin text-lg"
                  aria-hidden="true"></iconify-icon>
                <iconify-icon x-show="!deleteLoading" icon="mdi:delete-outline" class="text-lg"
                  aria-hidden="true"></iconify-icon>
                <span x-text="deleteLoading ? 'Menghapus...' : 'Ya, Hapus'"></span>
              </button>
            </div>
          </section>
        </div>
      </div>
    </template>
    @endif
  </div>
</x-layout.admin>
