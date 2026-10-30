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
      importZipName: '',
      importZipSize: '',
      importError: '',
      importResult: '',
      importErrors: [],
      importErrorsTruncated: false,
      modalOpen: @js($errors->any() && in_array(old('barang_form'), ['create', 'edit'], true)),
      modalMode: @js(old('barang_form') === 'edit' ? 'edit' : 'create'),
      currentItem: {
          barang_id: @js(old('barang_id', '')),
          kategori_id: @js(old('kategori_id', '')),
          locations: @js(old('locations', [['ruangan_id' => '', 'lemari_id' => '', 'stok_total' => 0, 'stok_tersedia' => 0]])),
          kode_barang: @js(old('kode_barang', '')),
          nama_barang: @js(old('nama_barang', '')),
          satuan: @js(old('satuan', 'unit')),
          stok_total: @js(old('stok_total', 0)),
          stok_tersedia: @js(old('stok_tersedia', 0)),
          kondisi: @js(old('kondisi', 'baru')),
          kelayakan: @js(old('kelayakan', 'layak')),
          spesifikasi: @js(old('spesifikasi', ''))
      },
      lemariOptions: @js(
    $lemaris
        ->map(
            fn($lemari) => [
                'lemari_id' => $lemari->lemari_id,
                'ruangan_id' => $lemari->ruangan_id,
                'nama_lemari' => $lemari->nama_lemari,
            ],
        )
        ->values(),
),
      deleteModalOpen: false,
      deleteLoading: false,
      pendingDeleteForm: null,
      pendingDeleteName: '',
      feedbackType: @js(session()->has('error') ? 'error' : 'success'),
      feedbackMessage: @js(session('success') ?? (session('error') ?? ($errors->any() ? $errors->first() : ''))),
      feedbackBarangName: @js(session('barang_feedback_name', '')),
      feedbackBarangAction: @js(session('barang_feedback_action', '')),
      formSubmitting: false,
      usageActivePanel: @js($filters['panel'] ?? 'usage'),
      selectedBarangId: @js($selectedBarang?->barang_id ?? ''),
      usagePanelLoading: false,
      usageSubmitting: false,
      activeUsageExpanded: false,
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
          const message = this.feedbackBarangName ? [
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
      setUsagePanel(panel) {
          this.usageActivePanel = panel;
          const url = new URL(window.location.href);
          url.searchParams.set('panel', panel);
          if (this.selectedBarangId) {
              url.searchParams.set('barang_id', this.selectedBarangId);
          }
          window.history.replaceState({}, '', url);
      },
      async changeUsageBarang(barangId) {
          if (!barangId) return;
          this.selectedBarangId = barangId;
          await this.reloadUsagePanel();
      },
      async reloadUsagePanel() {
          const container = document.getElementById('barang-usage-container');
          if (!container) return;
          this.usagePanelLoading = true;
          try {
              const url = new URL(this.barangResultsUrl, window.location.href);
              if (this.selectedBarangId) {
                  url.searchParams.set('barang_id', this.selectedBarangId);
              }
              url.searchParams.set('panel', this.usageActivePanel);
              url.searchParams.set('fragment', 'barang-usage-panel');
              const response = await fetch(url, {
                  headers: {
                      'X-Requested-With': 'XMLHttpRequest',
                      'X-Fragment': 'barang-usage-panel',
                      'Accept': 'text/html',
                  }
              });
              if (!response.ok) throw new Error('Data barang tidak dapat dimuat.');
              const html = await response.text();
              container.innerHTML = html;
              if (window.Alpine) {
                  window.Alpine.initTree(container);
              }
              url.searchParams.delete('fragment');
              window.history.replaceState({}, '', url);
          } catch (err) {
              window.showToast(err.message || 'Gagal memuat barang.', 'error');
          } finally {
              this.usagePanelLoading = false;
          }
      },
      async submitUsageForm(event) {
          const form = event.target;
          const submitBtn = form.querySelector('button[type=submit]');
          if (submitBtn) submitBtn.disabled = true;
          this.usageSubmitting = true;
          try {
              const formData = new FormData(form);
              const response = await fetch(form.action, {
                  method: 'POST',
                  headers: {
                      'X-Requested-With': 'XMLHttpRequest',
                      'Accept': 'application/json',
                  },
                  body: formData,
              });
              const data = await response.json().catch(() => null);
              if (!response.ok) {
                  const errorMsg = data?.message || (data?.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal mencatat pemakaian.');
                  window.showToast(errorMsg, 'error');
                  return;
              }
              window.showToast(data?.message || 'Pemakaian barang berhasil dicatat.', 'success');
              await this.reloadUsagePanel();
              await this.filterBarang();
          } catch (err) {
              window.showToast(err.message || 'Terjadi kesalahan sistem.', 'error');
          } finally {
              if (submitBtn) submitBtn.disabled = false;
              this.usageSubmitting = false;
          }
      },
      async withdrawUsageItem(event) {
          const form = event.target;
          const submitBtn = form.querySelector('button[type=submit]');
          if (submitBtn) submitBtn.disabled = true;
          try {
              const formData = new FormData(form);
              const response = await fetch(form.action, {
                  method: 'POST',
                  headers: {
                      'X-Requested-With': 'XMLHttpRequest',
                      'Accept': 'application/json',
                  },
                  body: formData,
              });
              const data = await response.json().catch(() => null);
              if (!response.ok) {
                  const errorMsg = data?.message || 'Gagal menarik pemakaian.';
                  window.showToast(errorMsg, 'error');
                  return;
              }
              window.showToast(data?.message || 'Barang berhasil ditarik kembali.', 'success');
              await this.reloadUsagePanel();
              await this.filterBarang();
          } catch (err) {
              window.showToast(err.message || 'Terjadi kesalahan sistem.', 'error');
          } finally {
              if (submitBtn) submitBtn.disabled = false;
          }
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
              locations: [{ ruangan_id: '', lemari_id: '', stok_total: 0, stok_tersedia: 0 }],
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
      addStockLocation() {
          if (this.currentItem.locations.length >= 50) return;
          this.currentItem.locations.push({
              ruangan_id: '',
              lemari_id: '',
              stok_total: 0,
              stok_tersedia: 0
          });
      },
      removeStockLocation(index) {
          if (this.currentItem.locations.length <= 1) return;
          this.currentItem.locations.splice(index, 1);
          this.syncStockTotals();
      },
      setStockLocationRoom(location, roomId) {
          location.ruangan_id = roomId;
          location.lemari_id = '';
      },
      syncStockTotals() {
          if (this.modalMode !== 'create') return;
  
          this.currentItem.stok_total = this.currentItem.locations.reduce(
              (total, location) => total + Math.max(0, Number(location.stok_total) || 0),
              0
          );
          this.currentItem.stok_tersedia = this.currentItem.locations.reduce(
              (total, location) => total + Math.max(0, Number(location.stok_tersedia) || 0),
              0
          );
      },
      editItem(item) {
          this.modalMode = 'edit';
          this.formSubmitting = false;
          this.currentItem = item;
          this.syncStockTotals();
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
      selectImportZip(files = this.$refs.barangImportZip.files) {
          const file = files[0];
          this.importZipName = file?.name || '';
          this.importZipSize = file ?
              file.size < 1024 ?
              `${file.size} B` :
              `${(file.size / 1024).toFixed(1)} KB` :
              '';
      },
      clearImportZip() {
          this.$refs.barangImportZip.value = '';
          this.selectImportZip([]);
      },
      openImportZipPicker() {
          this.$refs.barangImportZip.click();
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
                  this.importError = result.errors?.file?.[0] || result.errors?.images_zip?.[0] ||
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
      <form method="GET" action="{{ route('admin.barang') }}" x-ref="barangFilterForm" @submit.prevent="filterBarang()"
        class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          @if (auth()->user()?->canManageInventory())
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
            <input type="search" name="search" x-model="searchQuery" @input="scheduleBarangSearch()"
              @keydown.enter.prevent="filterBarang()" placeholder="Cari barang..."
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

      <div x-cloak x-show="filterError"
        class="flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800"
        role="alert">
        <span x-text="filterError"></span>
        <button type="button" @click="filterBarang()"
          class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">
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
                    @if (auth()->user()?->canManageInventory())
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
                    <tr data-barang-row
                      data-searchable="{{ mb_strtolower(trim($barang->kode_barang . ' ' . $barang->nama_barang . ' ' . ($barang->spesifikasi ?? ''))) }}"
                      x-show="matchesBarang($el.dataset.searchable)" class="transition-colors hover:bg-[#F7FBFA]">
                      <td class="max-w-56 px-4 py-3">
                        <button type="button" @click="changeUsageBarang('{{ $barang->barang_id }}')"
                          class="group text-left cursor-pointer focus:outline-none">
                          <p
                            class="truncate text-sm font-semibold text-slate-800 transition-colors group-hover:text-[#008b7a]">
                            {{ $barang->nama_barang }}</p>
                          <p class="mt-0.5 text-xs text-slate-500">{{ $barang->kode_barang }}</p>
                        </button>
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
                      <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                        {{ $barang->kategori?->nama_kategori ?? '-' }}</td>
                      <td class="px-4 py-3">
                        @if (auth()->user()?->canManageInventory())
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
                          <span
                            class="inline-flex w-24 items-center justify-center rounded-md px-2 py-1 text-xs font-medium {{ $barangCondition === 'baru' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ ucfirst($barangCondition) }}
                          </span>
                        @endif
                      </td>
                      <td class="px-4 py-3">
                        @if (auth()->user()?->canManageInventory())
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
                              <span
                                data-status-label>{{ $barangEligibility === 'layak' ? 'Layak' : 'Tidak Layak' }}</span>
                            </button>
                          </form>
                        @else
                          <span
                            class="ml-1 inline-flex w-28 items-center justify-center rounded-md px-3 py-1 text-xs font-medium {{ $barangEligibility === 'layak' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $barangEligibility === 'layak' ? 'Layak' : 'Tidak Layak' }}
                          </span>
                        @endif
                      </td>
                      <td class="min-w-max whitespace-nowrap px-4 py-3 text-xs leading-5 text-slate-600">
                        @forelse ($barang->lokasiBarangs as $lokasi)
                          <p class="whitespace-nowrap font-medium text-slate-700">
                            {{ $lokasi->ruangan?->nama_ruangan ?? 'Ruangan tidak ditemukan' }}
                            @if ($lokasi->lemari)
                              <span class="px-1 font-semibold text-emerald-600" aria-hidden="true">›</span>
                              {{ $lokasi->lemari->nama_lemari }}
                            @endif
                            <span class="ml-1 text-[11px] font-normal text-slate-500">
                              {{ number_format($lokasi->stok_tersedia, 0, ',', '.') }}/{{ number_format($lokasi->stok_total, 0, ',', '.') }}
                              tersedia
                            </span>
                          </p>
                        @empty
                          <span class="text-slate-400">Belum ditempatkan</span>
                        @endforelse
                      </td>
                      @if (auth()->user()?->canManageInventory())
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                          <div class="flex justify-end gap-2">
                            <button type="button"
                              @click="editItem({
                            barang_id: $el.dataset.barangId,
                            kategori_id: $el.dataset.kategoriId,
                            locations: JSON.parse($el.dataset.locations),
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
                              data-locations="{{ $barang->lokasiBarangs->map(
                                      fn($location) => [
                                          'ruangan_id' => $location->ruangan_id,
                                          'lemari_id' => $location->lemari_id,
                                          'stok_total' => $location->stok_total,
                                          'stok_tersedia' => $location->stok_tersedia,
                                      ],
                                  )->values()->toJson() }}"
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
                                <iconify-icon icon="mdi:delete-outline" width="15"
                                  aria-hidden="true"></iconify-icon>
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
                        <iconify-icon icon="mdi:package-variant-closed" class="mb-2 text-slate-300" width="42"
                          aria-hidden="true"></iconify-icon>
                        <p class="text-sm font-medium text-slate-700">Belum ada barang</p>
                        <p class="mt-1 text-xs text-slate-500">Tambahkan barang untuk mulai mengelola inventaris.</p>
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </section>
        @endfragment
      </div>

      @if (auth()->user()?->canManageAllAccounts())
        <div id="barang-usage-container" class="relative">
          @fragment('barang-usage-panel')
            @php
              $mutationLabels = [
                  'masuk' => 'Barang Masuk',
                  'dipinjam' => 'Dipinjam',
                  'dikembalikan' => 'Dikembalikan',
                  'dipakai' => 'Dipakai di Ruangan',
                  'ditarik' => 'Ditarik dari Ruangan',
                  'dipindahkan' => 'Dipindahkan',
                  'penyesuaian' => 'Penyesuaian',
              ];
            @endphp
            @if ($selectedBarang)
              <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <nav class="flex items-center gap-2">
                  <button type="button" @click="setUsagePanel('history')"
                    :class="usageActivePanel === 'history' ? 'border-[#00c99c] bg-[#00d69f] text-[#003f38]' :
                        'border-[#9ccfc4] bg-white text-[#153c36] hover:bg-[#effbf7]'"
                    class="inline-flex items-center justify-center rounded-lg border px-3.5 py-2 text-xs font-semibold shadow-sm transition">
                    Riwayat Mutasi
                  </button>
                  <button type="button" @click="setUsagePanel('usage')"
                    :class="usageActivePanel === 'usage' ? 'border-[#00c99c] bg-[#00d69f] text-[#003f38]' :
                        'border-[#9ccfc4] bg-white text-[#153c36] hover:bg-[#effbf7]'"
                    class="inline-flex items-center justify-center rounded-lg border px-3.5 py-2 text-xs font-semibold shadow-sm transition">
                    Catat Pemakaian
                  </button>
                </nav>
              </div>
            @endif
          @endfragment
        </div>
      @endif
    </div>

    @if (auth()->user()?->canManageInventory())
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
                      <h2 id="barang-import-title" class="text-base font-semibold text-slate-800">Import Data Barang &
                        Gambar</h2>
                      <p class="mt-1 text-xs text-slate-500">Unggah Excel dan ZIP gambar. Isi kolom Gambar Barang
                        dengan nama file di ZIP.</p>
                    </div>
                  </div>
                  <button type="button" @click="closeImport()" :disabled="importLoading"
                    class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Tutup formulir impor">
                    <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
                  </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                  <!-- Input Excel -->
                  <div>
                    <label for="barang-import-file" class="mb-1 block text-sm font-medium text-slate-700">File Excel
                      atau CSV <span class="text-rose-500">*</span></label>
                    <input id="barang-import-file" x-ref="barangImportFile" name="file" type="file"
                      accept=".csv,.xls,.xlsx" required @change="selectImportFile($event.target.files)"
                      class="hidden">
                    <div role="button" tabindex="0" @click="openImportFilePicker()"
                      class="group cursor-pointer rounded-lg border-2 border-dashed p-4 text-left transition"
                      :class="importFileName ? 'border-emerald-300 bg-emerald-50/70' :
                          'border-slate-300 bg-slate-50 text-center'">
                      <template x-if="!importFileName">
                        <div>
                          <p class="text-sm font-semibold text-slate-800">Klik untuk pilih file Excel (.xlsx / .csv)
                          </p>
                        </div>
                      </template>
                      <template x-if="importFileName">
                        <div class="flex items-center justify-between">
                          <span class="text-xs font-semibold text-emerald-800"
                            x-text="importFileName + ' (' + importFileSize + ')'"></span>
                          <button type="button" @click.stop="clearImportFile()"
                            class="text-rose-600 text-xs">Ganti</button>
                        </div>
                      </template>
                    </div>
                  </div>

                  <!-- Input ZIP Gambar (Opsional) -->
                  <div>
                    <label for="barang-import-zip" class="mb-1 block text-sm font-medium text-slate-700">File ZIP
                      Gambar <span class="text-xs text-slate-400">(Opsional)</span></label>
                    <input id="barang-import-zip" x-ref="barangImportZip" name="images_zip" type="file"
                      accept=".zip" @change="selectImportZip($event.target.files)" class="hidden">
                    <div role="button" tabindex="0" @click="$refs.barangImportZip.click()"
                      class="group cursor-pointer rounded-lg border-2 border-dashed p-4 text-left transition"
                      :class="importZipName ? 'border-sky-300 bg-sky-50/70' : 'border-slate-300 bg-slate-50 text-center'">
                      <template x-if="!importZipName">
                        <div>
                          <p class="text-sm font-semibold text-slate-800">Pilih ZIP foto; nama file dicocokkan tanpa
                            membedakan huruf besar/kecil
                          </p>
                        </div>
                      </template>
                      <template x-if="importZipName">
                        <div class="flex items-center justify-between">
                          <span class="text-xs font-semibold text-sky-800"
                            x-text="importZipName + ' (' + importZipSize + ')'"></span>
                          <button type="button" @click.stop="clearImportZip()"
                            class="text-rose-600 text-xs">Ganti</button>
                        </div>
                      </template>
                    </div>
                  </div>

                  <div x-cloak x-show="importError"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                    role="alert">
                    <span x-text="importError"></span>
                  </div>
                  <div x-cloak x-show="importResult" class="rounded-lg border px-3 py-2 text-sm"
                    :class="importErrors.length ? 'border-amber-200 bg-amber-50 text-amber-900' :
                        'border-emerald-200 bg-emerald-50 text-emerald-800'">
                    <p x-text="importResult"></p>
                  </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                  <button type="button" @click="closeImport()" :disabled="importLoading"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                    Batal
                  </button>
                  <button type="submit" :disabled="importLoading || !importFileName"
                    class="inline-flex items-center justify-center rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white">
                    <span x-show="!importLoading">Mulai impor</span>
                    <span x-show="importLoading">Memproses...</span>
                  </button>
                </div>
              </form>
            </section>
          </div>
        </div>
      </template>
    @endif
  </div>
</x-layout.admin>
