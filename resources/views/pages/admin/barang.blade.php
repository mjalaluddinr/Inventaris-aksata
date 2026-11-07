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
      importStatusUrlTemplate: @js(route('admin.barang.import.status', ['logInput' => '__LOG_INPUT__'])),
      formAction: '',
      importOpen: false,
      importLoading: false,
      importQueued: false,
      importPollTimedOut: false,
      importFiles: [],
      importJobs: [],
      importError: '',
      imagePreview: '',
      imagePreviewName: '',
      modalOpen: @js($errors->any() && in_array(old('barang_form'), ['create', 'edit'], true)),
      modalMode: @js(old('barang_form') === 'edit' ? 'edit' : 'create'),
      currentItem: {
          barang_id: @js(old('barang_id', '')),
          kategori_id: @js(old('kategori_id', '')),
          locations: @js(old('locations', [['ruangan_id' => '', 'lemari_id' => '', 'toolbox_id' => '', 'stok_total' => 0, 'stok_tersedia' => 0]])),
          kode_barang: @js(old('kode_barang', '')),
          nama_barang: @js(old('nama_barang', '')),
          satuan: @js(strtoupper((string) old('satuan', ''))),
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
      toolboxOptions: @js(
    $toolboxes
        ->map(
            fn($toolbox) => [
                'toolbox_id' => $toolbox->toolbox_id,
                'kode_toolbox' => $toolbox->kode_toolbox,
                'nama_toolbox' => $toolbox->nama_toolbox,
            ],
        )
        ->values(),
),
      roomOptions: @js(
    $ruangans
        ->map(fn($ruangan) => [
            'ruangan_id' => $ruangan->ruangan_id,
            'nama_ruangan' => $ruangan->nama_ruangan,
        ])
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
      usagePanelRequest: null,
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
      async filterBarang(url = null, paginationScrollTop = null) {
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
              if (paginationScrollTop !== null) {
                  const scrollContainer = this.$el.closest('main');
                  requestAnimationFrame(() => {
                      if (request.signal.aborted || !scrollContainer) return;
                      scrollContainer.scrollTop = paginationScrollTop;
                  });
              }
              window.history.replaceState({}, '', requestUrl);
              await this.reloadUsagePanel();
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
          const link = target?.closest('a[data-barang-pagination]');
          if (!link || !this.$refs.barangResults.contains(link)) return;
          event.preventDefault();
          const scrollContainer = this.$el.closest('main');
          this.filterBarang(link.href, scrollContainer?.scrollTop ?? null);
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
          if (this.usagePanelRequest) this.usagePanelRequest.abort();

          const request = new AbortController();
          this.usagePanelRequest = request;
          this.usagePanelLoading = true;
          try {
              const url = new URL(window.location.href);
              url.searchParams.delete('fragment');
              url.searchParams.delete('mutation_page');
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
                  },
                  signal: request.signal,
              });
              if (!response.ok) throw new Error('Data barang tidak dapat dimuat.');
              const html = await response.text();
              if (request.signal.aborted || this.usagePanelRequest !== request) return;

              container.innerHTML = html;
              if (window.Alpine) {
                  window.Alpine.initTree(container);
              }
              url.searchParams.delete('fragment');
              window.history.replaceState({}, '', url);
          } catch (err) {
              if (err.name !== 'AbortError') {
                  window.showToast(err.message || 'Gagal memuat barang.', 'error');
              }
          } finally {
              if (this.usagePanelRequest === request) {
                  this.usagePanelLoading = false;
                  this.usagePanelRequest = null;
              }
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
          this.clearBarangImagePreview();
          this.currentItem = {
              barang_id: '',
              kategori_id: '',
              locations: [{
                  ruangan_id: '',
                  lemari_id: '',
                  toolbox_id: '',
                  stok_total: 0,
                  stok_tersedia: 0,
                  reserved_stock: 0
              }],
              kode_barang: '',
              nama_barang: '',
              satuan: '',
              stok_total: 0,
              stok_tersedia: 0,
              kondisi: 'baru',
              kelayakan: 'layak',
              spesifikasi: ''
          };
          this.syncStockTotals('total');
          this.formAction = this.createUrl;
          this.modalOpen = true;
      },
      addStockLocation() {
          if (this.currentItem.locations.length >= 50) return;
          this.currentItem.locations.push({
              ruangan_id: '',
              lemari_id: '',
              toolbox_id: '',
              stok_total: 0,
              stok_tersedia: 0,
              reserved_stock: 0
          });
      },
      stockLocationGridStyle() {
          const labelFor = (options, id, label) => {
              const selectedOption = options.find((option) => String(option.id) === String(id));
              return selectedOption ? label(selectedOption) : '';
          };
          const widthFor = (labels, minimum) => {
              const longestLabel = labels.reduce((longest, label) => Math.max(longest, Array.from(label).length), 0);
              return `minmax(${minimum}ch, ${Math.max(minimum, longestLabel + 7)}ch)`;
          };
          const roomWidth = widthFor(this.currentItem.locations.map((location) =>
              labelFor(this.roomOptions.map((room) => ({ id: room.ruangan_id, name: room.nama_ruangan })),
                  location.ruangan_id, (room) => room.name)
          ), 15);
          const cabinetWidth = widthFor(this.currentItem.locations.map((location) =>
              labelFor(this.lemariOptions.map((cabinet) => ({ id: cabinet.lemari_id, name: cabinet.nama_lemari })),
                  location.lemari_id, (cabinet) => cabinet.name)
          ), 15);
          const toolboxWidth = widthFor(this.currentItem.locations.map((location) =>
              labelFor(this.toolboxOptions.map((toolbox) => ({
                  id: toolbox.toolbox_id,
                  name: `${toolbox.kode_toolbox} · ${toolbox.nama_toolbox}`,
              })), location.toolbox_id, (toolbox) => toolbox.name)
          ), 15);

          return {
              gridTemplateColumns: `${roomWidth} ${cabinetWidth} ${toolboxWidth} 10rem 10rem minmax(12rem, max-content) 2rem`,
          };
      },
      removeStockLocation(index) {
          if (
              this.currentItem.locations.length <= 1 ||
              Number(this.currentItem.locations[index].reserved_stock) > 0
          ) return;
          this.currentItem.locations.splice(index, 1);
          this.syncStockTotals('location');
      },
      setStockLocationRoom(location, roomId) {
          location.ruangan_id = roomId;
          location.lemari_id = '';
      },
      reservedStockTotal() {
          return this.currentItem.locations.reduce(
              (total, location) => total + Math.max(0, Number(location.reserved_stock) || 0),
              0
          );
      },
      syncStockTotals(changedField = 'location', locationIndex = null) {
          this.currentItem.locations.forEach((location) => {
              const available = Math.max(0, Number(location.stok_tersedia) || 0);
              const reserved = Number.isFinite(Number(location.reserved_stock)) ?
                  Math.max(0, Number(location.reserved_stock)) :
                  Math.max(0, (Number(location.stok_total) || 0) - available);

              location.reserved_stock = reserved;
              location.stok_total = Math.max(reserved, Number(location.stok_total) || 0);
              location.stok_tersedia = Math.min(location.stok_total, available);
          });

          const reservedTotal = this.reservedStockTotal();
          const onlyLocation = this.currentItem.locations.length === 1 ?
              this.currentItem.locations[0] :
              null;

          if (changedField === 'total') {
              this.currentItem.stok_total = Math.max(
                  reservedTotal,
                  Number(this.currentItem.stok_total) || 0
              );
              this.currentItem.stok_tersedia = Math.min(
                  this.currentItem.stok_total,
                  Math.max(0, Number(this.currentItem.stok_tersedia) || 0)
              );
              if (onlyLocation) {
                  onlyLocation.stok_total = this.currentItem.stok_total;
                  onlyLocation.stok_tersedia = this.currentItem.stok_tersedia;
              }
          } else if (changedField === 'available') {
              this.currentItem.stok_tersedia = Math.min(
                  Math.max(0, Number(this.currentItem.stok_total) || 0),
                  Math.max(0, Number(this.currentItem.stok_tersedia) || 0)
              );
              if (onlyLocation) {
                  onlyLocation.stok_tersedia = this.currentItem.stok_tersedia;
              }
          } else if (changedField === 'location_total' && this.currentItem.locations[locationIndex]) {
              const location = this.currentItem.locations[locationIndex];
              location.stok_total = Math.max(
                  location.reserved_stock,
                  Number(location.stok_total) || 0
              );
              location.stok_tersedia = Math.min(location.stok_total, location.stok_tersedia);
              if (onlyLocation) {
                  this.currentItem.stok_total = location.stok_total;
                  this.currentItem.stok_tersedia = location.stok_tersedia;
              }
          } else if (changedField === 'location_available' && this.currentItem.locations[locationIndex]) {
              const location = this.currentItem.locations[locationIndex];
              location.stok_tersedia = Math.min(
                  location.stok_total,
                  Math.max(0, Number(location.stok_tersedia) || 0)
              );
              if (onlyLocation) {
                  this.currentItem.stok_total = location.stok_total;
                  this.currentItem.stok_tersedia = location.stok_tersedia;
              }
          } else if (onlyLocation) {
              onlyLocation.stok_total = Math.max(
                  onlyLocation.reserved_stock,
                  Number(this.currentItem.stok_total) || 0
              );
              onlyLocation.stok_tersedia = Math.min(
                  onlyLocation.stok_total,
                  Math.max(0, Number(this.currentItem.stok_tersedia) || 0)
              );
              this.currentItem.stok_total = onlyLocation.stok_total;
              this.currentItem.stok_tersedia = onlyLocation.stok_tersedia;
          }

          this.currentItem.stok_total = Math.max(
              reservedTotal,
              Number(this.currentItem.stok_total) || 0
          );
          this.currentItem.stok_tersedia = Math.min(
              this.currentItem.stok_total,
              Math.max(0, Number(this.currentItem.stok_tersedia) || 0)
          );
      },
      editItem(item) {
          this.modalMode = 'edit';
          this.formSubmitting = false;
          this.clearBarangImagePreview();
          const hasActiveReservations = item.has_active_reservations === true;
          this.currentItem = {
              ...item,
              satuan: String(item.satuan || '').toUpperCase(),
              stok_tersedia: hasActiveReservations ? item.stok_tersedia : item.stok_total,
              locations: item.locations.map((location) => {
                  const reservedStock = hasActiveReservations
                      ? Math.max(0, Number(location.stok_total) - Number(location.stok_tersedia))
                      : 0;

                  return {
                      ...location,
                      stok_tersedia: hasActiveReservations ? location.stok_tersedia : location.stok_total,
                      reserved_stock: reservedStock
                  };
              })
          };
          this.imagePreview = item.image_url || '';
          this.syncStockTotals('location');
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
          if (this.$refs.barangImageInput) this.$refs.barangImageInput.value = '';
          this.clearBarangImagePreview();
      },
      openBarangImagePicker() {
          this.$refs.barangImageInput.click();
      },
      previewBarangImage(event) {
          const file = event.target.files?.[0];
          if (!file) return;

          this.clearBarangImagePreview();
          this.imagePreview = URL.createObjectURL(file);
          this.imagePreviewName = file.name;
      },
      handleBarangImageDrop(event) {
          const file = Array.from(event.dataTransfer.files).find(file => file.type.startsWith('image/'));
          if (!file) return;

          const transfer = new DataTransfer();
          transfer.items.add(file);
          this.$refs.barangImageInput.files = transfer.files;
          this.previewBarangImage({ target: this.$refs.barangImageInput });
      },
      clearBarangImagePreview() {
          if (this.imagePreview.startsWith('blob:')) URL.revokeObjectURL(this.imagePreview);
          this.imagePreview = '';
          this.imagePreviewName = '';
      },
      removeBarangImage() {
          if (this.$refs.barangImageInput) this.$refs.barangImageInput.value = '';
          this.clearBarangImagePreview();
          this.imagePreview = this.modalMode === 'edit' ? this.currentItem.image_url || '' : '';
      },
      handleEscape() {
          this.modalOpen = false;
          this.importOpen = false;
          this.cancelDelete();
      },
      selectImportFiles(files = this.$refs.barangImportPicker.files) {
          if (this.importLoading || this.importQueued) return;

          const selectedFiles = Array.from(files || []);
          const spreadsheets = [];
          const imageArchives = [];
          const unsupportedFiles = [];

          selectedFiles.forEach(file => {
              const extension = file.name.split('.').pop().toLowerCase();
              if (['csv', 'xls', 'xlsx'].includes(extension)) {
                  spreadsheets.push(file);
              } else if (extension === 'zip') {
                  imageArchives.push(file);
              } else {
                  unsupportedFiles.push(file.name);
              }
          });
          this.$refs.barangImportPicker.value = '';

          if (unsupportedFiles.length) {
              this.importError = `Format file tidak didukung: ${unsupportedFiles.join(', ')}. Pilih CSV, XLS, XLSX, atau ZIP.`;
              return;
          }
          if (spreadsheets.length > 10) {
              this.importError = 'Maksimal 10 file Excel dapat diimpor sekaligus.';
              return;
          }
          if (imageArchives.length > 1) {
              this.importError = 'Pilih maksimal satu file ZIP gambar.';
              return;
          }
          if (spreadsheets.some(file => file.size > 5 * 1024 * 1024)) {
              this.importError = 'Ukuran setiap file Excel maksimal 5 MB.';
              return;
          }
          if (imageArchives.some(file => file.size > 20 * 1024 * 1024)) {
              this.importError = 'Ukuran file ZIP gambar maksimal 20 MB.';
              return;
          }

          const spreadsheetTransfer = new DataTransfer();
          spreadsheets.forEach(file => spreadsheetTransfer.items.add(file));
          this.$refs.barangImportFile.files = spreadsheetTransfer.files;

          const archiveTransfer = new DataTransfer();
          imageArchives.forEach(file => archiveTransfer.items.add(file));
          this.$refs.barangImportZip.files = archiveTransfer.files;

          let spreadsheetIndex = 0;
          let archiveIndex = 0;
          this.importFiles = selectedFiles.map(file => {
              const isZip = file.name.split('.').pop().toLowerCase() === 'zip';

              return {
                  name: file.name,
                  size: file.size < 1024 ? `${file.size} B` : `${(file.size / 1024).toFixed(1)} KB`,
                  isZip,
                  fileIndex: isZip ? archiveIndex++ : spreadsheetIndex++
              };
          });
          this.importError = '';
      },
      clearImportFile() {
          if (this.importLoading || this.importQueued) return;

          this.$refs.barangImportPicker.value = '';
          this.$refs.barangImportFile.value = '';
          this.$refs.barangImportZip.value = '';
          this.importFiles = [];
          this.importError = '';
      },
      removeImportFile(index) {
          if (this.importLoading || this.importQueued) return;

          const removedFile = this.importFiles[index];
          if (!removedFile) return;

          const spreadsheets = Array.from(this.$refs.barangImportFile.files)
              .filter((_, fileIndex) => removedFile.isZip || fileIndex !== removedFile.fileIndex);
          const imageArchives = Array.from(this.$refs.barangImportZip.files)
              .filter((_, fileIndex) => !removedFile.isZip || fileIndex !== removedFile.fileIndex);
          this.selectImportFiles([...spreadsheets, ...imageArchives]);
      },
      openImportFilePicker() {
          if (this.importLoading || this.importQueued) return;

          this.$refs.barangImportPicker.click();
      },
      handleImportFileKeydown(event) {
          if (event.target !== event.currentTarget) return;
          if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              this.openImportFilePicker();
          }
      },
      handleImportDrop(event) {
          if (this.importLoading || this.importQueued || !event.dataTransfer.files.length) return;
          this.selectImportFiles(event.dataTransfer.files);
      },
      closeImport() {
          this.importOpen = false;
      },
      async submitBarangImport() {
          if (this.importLoading || this.importQueued) return;

          this.importLoading = true;
          this.importError = '';
          this.importJobs = [];

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
                  this.importError = Object.values(result.errors || {}).flat()[0] ||
                      (response.status === 422 ? result.message : 'Import gagal diproses. Coba lagi nanti.');
                  return;
              }

              this.importJobs = result.imports || [{
                  log_id: result.log_id,
                  file_name: this.importFiles.find(file => !file.isZip)?.name || 'File import',
                  status: result.status
              }];
              if (!this.importJobs.length || this.importJobs.some(job => !job.log_id)) {
                  throw new Error('Status file import tidak tersedia. Periksa kembali halaman barang.');
              }
              this.importQueued = true;
              this.importPollTimedOut = false;
              this.importJobs.forEach(job => {
                  job.pollTimedOut = false;
                  void this.pollBarangImportStatus(job.log_id);
              });
          } catch (error) {
              this.importError = error.message || 'File tidak dapat diimpor. Periksa koneksi lalu coba lagi.';
          } finally {
              this.importLoading = this.importQueued;
          }
      },
      async pollBarangImportStatus(logId) {
          const job = this.importJobs.find(item => item.log_id === logId);
          if (!job) return;

          const statusUrl = this.importStatusUrlTemplate.replace('__LOG_INPUT__', encodeURIComponent(logId));

          for (let attempt = 0; attempt < 150; attempt++) {
              if (attempt > 0) {
                  await new Promise(resolve => setTimeout(resolve, 2000));
              }

              try {
                  const response = await fetch(statusUrl, {
                      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                      cache: 'no-store'
                  });
                  const result = await response.json();
                  if (!response.ok) throw new Error('Status import tidak dapat dimuat.');
                  if (result.status === 'proses') continue;

                  Object.assign(job, result, { pollTimedOut: false });
                  this.finishBarangImportPolling();
                  return;
              } catch (error) {
                  if (attempt === 149) {
                      this.importError = error.message || 'Status import gagal diperiksa. Coba buka kembali halaman.';
                      job.pollTimedOut = true;
                      this.importPollTimedOut = true;
                  }
              }
          }

          if (job.status === 'proses') {
              job.pollTimedOut = true;
              this.importPollTimedOut = true;
              this.importError = 'Import masih berjalan. Periksa kembali statusnya atau biarkan halaman ini terbuka.';
          }
      },
      finishBarangImportPolling() {
          const pendingJobs = this.importJobs.some(job => job.status === 'proses' || job.pollTimedOut);
          this.importQueued = pendingJobs;
          this.importLoading = pendingJobs;
          if (pendingJobs) return;

          this.importPollTimedOut = false;
          this.$refs.barangImportForm.reset();
          this.importFiles = [];
          const failedJobs = this.importJobs.filter(job => Number(job.failed_rows) > 0 || job.status === 'gagal');
          if (!failedJobs.length) {
              window.showToast('Import selesai.', 'success', 5000);
              return;
          }

          const importedRows = this.importJobs.reduce(
              (total, job) => total + Math.max(0, Number(job.success_rows) || 0),
              0
          );
          const isPartial = importedRows > 0;
          window.showToast(
              isPartial ?
              'Import selesai sebagian. Periksa ringkasan pada Log Import.' :
              'Import gagal. Periksa ringkasan pada Log Import.',
              isPartial ? 'warning' : 'error',
              2500,
              isPartial ? 'Import selesai sebagian' : 'Import gagal'
          );
      },
      retryBarangImportStatus() {
          if (!this.importQueued) return;
          this.importPollTimedOut = false;
          this.importError = '';
          this.importJobs.filter(job => job.status === 'proses' || job.pollTimedOut).forEach(job => {
              job.pollTimedOut = false;
              void this.pollBarangImportStatus(job.log_id);
          });
      }
  }" @keydown.escape.window="handleEscape()" style="overflow-anchor: none"
    class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-10rem)] bg-[#E8F8F4]">
    <div class="space-y-4 p-4 sm:p-8">
      <form method="GET" action="{{ route('admin.barang') }}" x-ref="barangFilterForm" @submit.prevent="filterBarang()"
        class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          @if (auth()->user()?->canManageInventory())
            <div class="flex w-full flex-wrap items-center gap-2">
              <button type="button" @click="createItem()"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2">
                <iconify-icon icon="mdi:package-variant-plus" width="18" aria-hidden="true"></iconify-icon>
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
            <div class="overflow-x-auto">
              <table class="min-w-full w-full divide-y divide-[#DCE9E6]">
                <thead class="bg-[#F1F3F4]">
                  <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Gambar</th>
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
                      <td class="w-24 px-4 py-3">
                        @if ($barang->image_url)
                          <img src="{{ $barang->image_url }}" alt="Gambar {{ $barang->nama_barang }}" decoding="async"
                            class="h-16 w-16 rounded-lg border border-slate-200 bg-white object-cover shadow-sm">
                        @else
                          <span
                            class="flex h-16 w-16 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-slate-400"
                            role="img" aria-label="Barang tidak memiliki gambar">
                            <iconify-icon icon="mdi:image-outline" width="22" aria-hidden="true"></iconify-icon>
                          </span>
                        @endif
                      </td>
                      <td class="max-w-56 px-4 py-3">
                        <button type="button" @click="changeUsageBarang('{{ $barang->barang_id }}')"
                          class="group text-left cursor-pointer focus:outline-none">
                          <p
                            class="truncate text-sm font-semibold text-slate-800 transition-colors group-hover:text-[#008b7a]">
                            {{ $barang->nama_barang }}</p>
                          <p class="mt-0.5 text-xs text-slate-500">{{ $barang->kode_barang }}</p>
                        </button>
                      </td>
                      <td class="max-w-64 whitespace-nowrap px-4 py-3 text-xs leading-5 text-slate-600">
                        @if ($barang->spesifikasi)
                          <p class="max-w-64 truncate" title="{{ $barang->spesifikasi }}">{{ $barang->spesifikasi }}</p>
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
                            <input type="hidden" name="status_field" value="kondisi">
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
                              class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium transition-colors duration-200 hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-[#08A9D6] disabled:cursor-wait disabled:opacity-60 {{ $barangCondition === 'baru' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
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
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium {{ $barangCondition === 'baru' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $barangCondition === 'baru' ? 'bg-emerald-500' : 'bg-rose-500' }}"
                              aria-hidden="true"></span>
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
                            <input type="hidden" name="status_field" value="kelayakan">
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
                              class="ml-1 inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium transition-colors duration-200 hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-[#08A9D6] disabled:cursor-wait disabled:opacity-60 {{ $barangEligibility === 'layak' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
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
                            class="ml-1 inline-flex items-center gap-2 whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium {{ $barangEligibility === 'layak' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $barangEligibility === 'layak' ? 'bg-emerald-500' : 'bg-rose-500' }}"
                              aria-hidden="true"></span>
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
                            @if ($lokasi->toolbox)
                              <span class="px-1 font-semibold text-emerald-600" aria-hidden="true">›</span>
                              {{ $lokasi->toolbox->nama_toolbox }}
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
                            spesifikasi: $el.dataset.spesifikasi,
                            has_active_reservations: $el.dataset.hasActiveReservations === 'true',
                            image_url: $el.dataset.imageUrl
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
                                          'toolbox_id' => $location->toolbox_id,
                                          'stok_total' => $location->stok_total,
                                          'stok_tersedia' => $location->stok_tersedia,
                                          'reserved_stock' => max(0, $location->stok_total - $location->stok_tersedia),
                                      ],
                                  )->values()->toJson() }}"
                              data-has-active-reservations="{{ $barang->active_usages_count > 0 || $barang->active_loans_count > 0 ? 'true' : 'false' }}"
                              data-kelayakan="{{ $barangEligibility }}" data-spesifikasi="{{ $barang->spesifikasi }}"
                              data-image-url="{{ $barang->image_url }}"
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
          <div
            class="flex flex-col gap-3 border-t border-[#E2ECE9] bg-[#F7FBFA] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
              Menampilkan {{ $barangs->firstItem() ?? 0 }}-{{ $barangs->lastItem() ?? 0 }} dari {{ $barangs->total() }} barang
            </p>
            <x-pagination :paginator="$barangs" label="Navigasi halaman barang"
              link-attribute="data-barang-pagination"
              nav-class="flex w-full min-w-0 items-center justify-start gap-2 overflow-x-auto overscroll-x-contain touch-pan-x sm:w-auto sm:justify-end" />
          </div>
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
              <section class="overflow-hidden rounded-xl border border-[#B9DED4] bg-white shadow-sm">
                <header
                  class="flex flex-col gap-3 border-b border-[#D8EBE5] bg-[#F5FBF9] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                  <div class="min-w-0">
                    <h2 class="text-base font-bold text-slate-800">{{ $selectedBarang->nama_barang }}</h2>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ $selectedBarang->kode_barang }} · Stok tersedia
                      {{ number_format($selectedBarang->stok_tersedia, 0, ',', '.') }} {{ $selectedBarang->satuan }}
                    </p>
                  </div>
                  <div class="flex flex-col gap-2 sm:items-end">
                    <label for="usage-item" class="sr-only">Pilih barang untuk riwayat atau pemakaian</label>
                    <select id="usage-item" x-model="selectedBarangId" @change="changeUsageBarang($event.target.value)"
                      class="max-w-full rounded-lg border border-[#B9DED4] bg-white px-3 py-2 text-xs font-semibold text-slate-700 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
                      @if (!$usageBarangs->contains('barang_id', $selectedBarang->barang_id))
                        <option value="{{ $selectedBarang->barang_id }}" selected>
                          {{ $selectedBarang->nama_barang }} · {{ $selectedBarang->kode_barang }}
                        </option>
                      @endif
                      @foreach ($usageBarangs as $barangOption)
                        <option value="{{ $barangOption->barang_id }}"
                          @selected($selectedBarang->barang_id === $barangOption->barang_id)>
                          {{ $barangOption->nama_barang }} · {{ $barangOption->kode_barang }}
                        </option>
                      @endforeach
                    </select>
                    <nav class="flex flex-wrap gap-2" aria-label="Riwayat dan pemakaian barang">
                      <button type="button" @click="setUsagePanel('history')"
                        :aria-current="usageActivePanel === 'history' ? 'page' : null"
                        :class="usageActivePanel === 'history' ?
                            'border-[#008b7a] bg-[#008b7a] text-white hover:bg-[#006f62]' :
                            'border-[#B9DED4] bg-white text-slate-700 hover:bg-emerald-50'"
                        class="inline-flex min-h-9 items-center rounded-lg border px-3 py-2 text-xs font-semibold transition">
                        Riwayat Mutasi
                      </button>
                      <button type="button" @click="setUsagePanel('usage')"
                        :aria-current="usageActivePanel === 'usage' ? 'page' : null"
                        :class="usageActivePanel === 'usage' ?
                            'border-[#008b7a] bg-[#008b7a] text-white hover:bg-[#006f62]' :
                            'border-[#B9DED4] bg-white text-slate-700 hover:bg-emerald-50'"
                        class="inline-flex min-h-9 items-center rounded-lg border px-3 py-2 text-xs font-semibold transition">
                        Catat Pemakaian
                      </button>
                    </nav>
                  </div>
                </header>

                <div x-show="usageActivePanel === 'usage'" class="space-y-5 p-4 sm:p-6">
                  <form action="{{ route('admin.barang.pemakaian.store', $selectedBarang) }}" method="POST"
                    @submit.prevent="submitUsageForm($event)" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @csrf
                    <div>
                      <label for="usage-quantity" class="mb-1 block text-[11px] font-semibold text-slate-600">Jumlah
                        dipakai</label>
                      <input id="usage-quantity" name="jumlah" type="number" min="1" step="1" required
                        placeholder="Contoh: 2"
                        class="w-full rounded-md border border-slate-300 bg-[#F4F5F5] px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
                    </div>
                    <div>
                      <label for="usage-room" class="mb-1 block text-[11px] font-semibold text-slate-600">Lokasi
                        pemakaian</label>
                      <select id="usage-room" name="ruangan_id" required
                        class="w-full rounded-md border border-slate-300 bg-[#F4F5F5] px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
                        <option value="">Pilih ruangan</option>
                        @foreach ($usageLocations as $usageLocation)
                          <option value="{{ $usageLocation->ruangan_id }}">{{ $usageLocation->nama_ruangan }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label for="usage-admin" class="mb-1 block text-[11px] font-semibold text-slate-600">Dipakai oleh
                        (Admin)</label>
                      <select id="usage-admin" name="akun_id" required
                        class="w-full rounded-md border border-slate-300 bg-[#F4F5F5] px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
                        <option value="">Pilih admin</option>
                        @foreach ($adminAccounts as $adminAccount)
                          @php
                            $adminName = trim(
                                ($adminAccount->first_name ?? '') . ' ' . ($adminAccount->last_name ?? ''),
                            );
                          @endphp
                          <option value="{{ $adminAccount->akun_id }}">
                            {{ $adminName !== '' ? $adminName : $adminAccount->username }} (Admin)
                          </option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label for="usage-date" class="mb-1 block text-[11px] font-semibold text-slate-600">Tanggal
                        pasang</label>
                      <input id="usage-date" name="tanggal_pasang" type="date"
                        value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required
                        class="w-full rounded-md border border-slate-300 bg-[#F4F5F5] px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20">
                    </div>
                    <div class="sm:col-span-2">
                      <label for="usage-note" class="mb-1 block text-[11px] font-semibold text-slate-600">Catatan
                        pemakaian</label>
                      <textarea id="usage-note" name="keterangan" rows="2" maxlength="255"
                        placeholder="Contoh: Dipakai sebagai colokan tambahan"
                        class="w-full resize-y rounded-md border border-slate-300 bg-[#F4F5F5] px-3 py-2 text-sm text-slate-800 focus:border-[#008b7a] focus:outline-none focus:ring-2 focus:ring-[#008b7a]/20"></textarea>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                      <button type="submit"
                        :disabled="usageSubmitting ||
                            {{ $usageLocations->isEmpty() || $adminAccounts->isEmpty() || $selectedBarang->stok_tersedia < 1 ? 'true' : 'false' }}"
                        class="inline-flex min-h-9 items-center justify-center rounded-md bg-[#00BFA5] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#009E89] disabled:cursor-not-allowed disabled:opacity-50">
                        Catat Pemakaian
                      </button>
                      @if ($selectedBarang->stok_tersedia < 1)
                        <span class="text-xs text-amber-700">Tidak ada stok tersedia di lokasi penyimpanan.</span>
                      @elseif ($usageLocations->isEmpty())
                        <span class="text-xs text-amber-700">Tidak ada ruangan aktif untuk lokasi pemakaian.</span>
                      @endif
                      @if ($adminAccounts->isEmpty())
                        <span class="text-xs text-amber-700">Tidak ada akun admin aktif untuk dicatat.</span>
                      @endif
                    </div>
                  </form>

                  <section aria-labelledby="active-usage-title">
                    <div class="mb-2 flex items-center justify-between gap-3">
                      <h3 id="active-usage-title" class="text-sm font-bold text-slate-800">Pemakaian aktif</h3>
                      <span class="text-xs text-slate-500">{{ $activeUsages->count() }} catatan</span>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-[#DCE9E6]">
                      <table class="min-w-full divide-y divide-[#E7EFED]">
                        <thead class="bg-[#F5FBF9]">
                          <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-slate-600">Tanggal
                            </th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-slate-600">Lokasi /
                              Admin</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold text-slate-600">Jumlah
                            </th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-slate-600">Catatan
                            </th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold text-slate-600">Aksi
                            </th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E7EFED] bg-white">
                          @forelse ($activeUsages as $activeUsage)
                            @php
                              $usageAccountName = $activeUsage->akun
                                  ? (trim(
                                      ($activeUsage->akun->first_name ?? '') .
                                          ' ' .
                                          ($activeUsage->akun->last_name ?? ''),
                                  ) ?:
                                  $activeUsage->akun->username)
                                  : 'Admin tidak tersedia';
                            @endphp
                            <tr>
                              <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-600">
                                {{ $activeUsage->tanggal_pasang?->translatedFormat('d M Y') ?? '—' }}
                              </td>
                              <td class="px-3 py-2 text-xs text-slate-700">
                                {{ $activeUsage->ruangan?->nama_ruangan ?? 'Ruangan tidak tersedia' }} ·
                                {{ $usageAccountName }}
                              </td>
                              <td class="whitespace-nowrap px-3 py-2 text-right text-xs font-semibold text-slate-700">
                                {{ number_format($activeUsage->jumlah, 0, ',', '.') }} {{ $selectedBarang->satuan }}
                              </td>
                              <td class="max-w-64 px-3 py-2 text-xs text-slate-600">
                                {{ $activeUsage->keterangan ?: '—' }}</td>
                              <td class="whitespace-nowrap px-3 py-2 text-right">
                                <form
                                  action="{{ route('admin.barang.pemakaian.tarik', [$selectedBarang, $activeUsage]) }}"
                                  method="POST" @submit.prevent="withdrawUsageItem($event)">
                                  @csrf
                                  <button type="submit"
                                    class="inline-flex min-h-8 items-center rounded-md border border-[#B9DED4] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#007f70] transition hover:bg-emerald-50">
                                    Tarik kembali
                                  </button>
                                </form>
                              </td>
                            </tr>
                          @empty
                            <tr>
                              <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-500">Belum ada
                                pemakaian aktif.</td>
                            </tr>
                          @endforelse
                        </tbody>
                      </table>
                    </div>
                  </section>
                </div>

                <ol x-show="usageActivePanel === 'history'" class="divide-y divide-[#E3EEEA] px-4 sm:px-6">
                  @forelse ($mutationLogs as $mutation)
                    @php
                      $mutationColor = match ($mutation->tipe) {
                          'masuk', 'dikembalikan' => 'bg-teal-500',
                          'dipakai' => 'bg-orange-400',
                          'ditarik' => 'bg-rose-600',
                          default => 'bg-slate-400',
                      };
                      $mutationAccount = $mutation->akun ?? $mutation->olehAkun;
                      $mutationAccountName = $mutationAccount
                          ? (trim(($mutationAccount->first_name ?? '') . ' ' . ($mutationAccount->last_name ?? '')) ?:
                          $mutationAccount->username)
                          : null;
                    @endphp
                    <li class="flex gap-3 py-3">
                      <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $mutationColor }}"
                        aria-hidden="true"></span>
                      <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                          <p class="text-xs font-semibold text-slate-800">
                            {{ $mutation->created_at?->translatedFormat('d M Y') ?? 'Tanggal tidak tersedia' }} ·
                            {{ $mutationLabels[$mutation->tipe] ?? ucfirst($mutation->tipe) }}
                          </p>
                          <p class="whitespace-nowrap text-xs font-semibold text-slate-700">
                            {{ number_format($mutation->jumlah, 0, ',', '.') }} {{ $selectedBarang->satuan }}
                          </p>
                        </div>
                        <p class="mt-0.5 text-xs leading-5 text-slate-600">
                          @if ($mutation->tipe === 'dipindahkan')
                            Dari
                            {{ $mutation->lemariAsal?->nama_lemari ?? ($mutation->ruangan?->nama_ruangan ?? 'lokasi belum diketahui') }}
                            ke {{ $mutation->lemariTujuan?->nama_lemari ?? 'lokasi tujuan' }} ·
                          @elseif ($mutation->tipe === 'dipakai')
                            Stok diambil dari {{ $mutation->ruangan?->nama_ruangan ?? 'lokasi penyimpanan' }}
                            @if ($mutation->lemariAsal)
                              › {{ $mutation->lemariAsal->nama_lemari }}
                            @endif
                            untuk dipakai di {{ $mutation->barangDipakai?->ruangan?->nama_ruangan ?? 'lokasi pemakaian' }} ·
                          @elseif ($mutation->tipe === 'ditarik')
                            Stok dikembalikan ke {{ $mutation->ruangan?->nama_ruangan ?? 'lokasi penyimpanan' }}
                            @if ($mutation->lemariTujuan)
                              › {{ $mutation->lemariTujuan->nama_lemari }}
                            @endif
                            ·
                          @elseif ($mutation->ruangan)
                            {{ $mutation->ruangan->nama_ruangan }} ·
                          @endif
                          @if ($mutationAccountName)
                            {{ $mutationAccountName }} ·
                          @endif
                          {{ $mutation->keterangan ?: 'Perubahan stok barang.' }}
                        </p>
                      </div>
                    </li>
                  @empty
                    <li class="py-8 text-center text-xs text-slate-500">Belum ada riwayat mutasi untuk barang ini.</li>
                  @endforelse
                </ol>
                @if ($mutationLogs->hasPages())
                  <div class="flex flex-col gap-3 border-t border-[#E3EEEA] px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs text-slate-500">
                      Menampilkan {{ $mutationLogs->firstItem() }}-{{ $mutationLogs->lastItem() }} dari
                      {{ $mutationLogs->total() }} mutasi
                    </p>
                    <x-pagination :paginator="$mutationLogs" label="Navigasi riwayat mutasi" />
                  </div>
                @endif
              </section>
            @else
              <div class="rounded-xl border border-[#B9DED4] bg-white px-5 py-8 text-center">
                <h2 class="text-sm font-semibold text-slate-800">Riwayat dan pemakaian barang</h2>
                <p class="mt-1 text-xs text-slate-500">Belum ada barang yang dapat ditampilkan.</p>
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
                      <h2 id="barang-import-title" class="text-base font-semibold text-slate-800">Import Data Barang
                      </h2>
                      <p class="mt-1 text-xs text-slate-500">Unggah file Excel dan ZIP gambar barang.</p>
                    </div>
                  </div>
                  <button type="button" @click="closeImport()"
                    class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Tutup formulir impor">
                    <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
                  </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                  <input id="barang-import-file" x-ref="barangImportFile" name="files[]" type="file"
                    accept=".xlsx,.xls,.csv" multiple required aria-label="File Excel untuk diimpor" class="hidden">
                  <input id="barang-import-zip" x-ref="barangImportZip" name="images_zip" type="file"
                    accept=".zip" aria-label="Arsip ZIP gambar" class="hidden">
                  <input id="barang-import-picker" x-ref="barangImportPicker" type="file"
                    accept=".xlsx,.xls,.csv,.zip" multiple aria-label="Pilih file Excel dan ZIP gambar"
                    @change="selectImportFiles($event.target.files)" class="hidden">
                  <div role="button" tabindex="0" @click="openImportFilePicker()"
                    @keydown="handleImportFileKeydown($event)" @dragover.prevent
                    @drop.prevent="handleImportDrop($event)" :aria-disabled="importLoading || importQueued"
                    class="group cursor-pointer rounded-lg border-2 border-dashed p-5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#08A9D6] focus-visible:ring-offset-2"
                    :class="importFiles.length ?
                        'border-emerald-300 bg-emerald-50/70 hover:border-emerald-400 hover:bg-emerald-50' :
                        'border-slate-300 bg-slate-50 text-center hover:border-[#08A9D6] hover:bg-[#F0FDFA]'">
                    <template x-if="!importFiles.length">
                      <div class="flex flex-col items-center">
                        <span
                          class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 transition group-hover:bg-emerald-200">
                          <iconify-icon icon="mdi:file-excel-outline" width="32"></iconify-icon>
                        </span>
                        <p class="text-sm font-semibold text-slate-800">Seret file Excel dan ZIP ke sini</p>
                        <p class="mt-1 text-sm text-slate-600">atau klik untuk memilih semua file sekaligus</p>
                        <p
                          class="mt-3 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                          .xlsx, .xls, .csv, .zip · Maks. 10 Excel (5 MB/file) + 1 ZIP (20 MB)
                        </p>
                      </div>
                    </template>
                    <template x-if="importFiles.length">
                      <div class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                          <span class="text-sm font-semibold text-slate-800">
                            <span x-text="importFiles.length"></span> file siap diunggah
                          </span>
                          <button type="button" @click.stop="clearImportFile()"
                            :disabled="importLoading || importQueued"
                            class="text-xs font-semibold text-rose-700 hover:text-rose-800">
                            Hapus semua
                          </button>
                        </div>
                        <ul class="max-h-48 space-y-2 overflow-y-auto">
                          <template x-for="(file, index) in importFiles" :key="`${file.name}-${index}`">
                            <li
                              class="flex items-center gap-3 rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-emerald-200">
                              <span
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl shadow-sm ring-1"
                                :class="file.isZip ? 'bg-sky-50 text-sky-700 ring-sky-200' :
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200'">
                                <iconify-icon :icon="file.isZip ? 'mdi:folder-zip-outline' : 'mdi:file-excel'"
                                  width="26" aria-hidden="true"></iconify-icon>
                              </span>
                              <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-800"
                                  x-text="file.name"></span>
                                <span class="mt-1 block text-xs text-emerald-800">
                                  File siap diimpor <span class="text-emerald-700" x-text="file.size"></span>
                                </span>
                                <span class="mt-1 block text-xs font-medium text-[#087F70]"
                                  x-text="file.isZip ? 'Arsip ZIP gambar' : 'Data barang'"></span>
                              </span>
                              <button type="button" @click.stop="removeImportFile(index)"
                                :disabled="importLoading || importQueued"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:cursor-not-allowed disabled:opacity-50"
                                :aria-label="`Hapus ${file.name}`">
                                <iconify-icon icon="mdi:close-circle-outline" width="18"></iconify-icon>
                              </button>
                            </li>
                          </template>
                        </ul>
                        <p x-show="!importFiles.some(file => !file.isZip)" class="text-xs text-amber-700">
                          Pilih minimal satu file Excel atau CSV. ZIP gambar dapat disertakan.
                        </p>
                      </div>
                    </template>
                  </div>

                  <div class="rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
                    <div class="flex gap-2">
                      <iconify-icon icon="mdi:information" class="mt-0.5 shrink-0" width="16"></iconify-icon>
                      <div class="space-y-2">
                        <p class="font-semibold">Format import barang</p>
                        <p>Isi kolom <span class="font-semibold">GAMBAR BARANG</span> dengan nama file gambar di
                          dalam ZIP. Nama file dicocokkan tanpa membedakan huruf besar/kecil.</p>
                        <p>Kode barang, kode ruangan, dan kode lemari boleh dikosongkan; sistem akan membuat kode
                          secara berurutan. Ruangan wajib diisi pada setiap baris.</p>
                        <p>Jika barang yang sama berada di beberapa ruangan, ulangi nama dan data barang pada setiap
                          baris lalu isi ruangan serta stok lokasi masing-masing. Stok barang dijumlahkan otomatis.
                          Kondisi barang dengan nama yang sama harus konsisten; baris yang tidak konsisten ditolak.</p>
                        <a href="{{ asset('import-templates/format-import-barang.xlsx') }}" download
                          class="inline-flex items-center gap-1 font-semibold text-[#087E78] hover:text-[#065f59] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]">
                          <iconify-icon icon="mdi:download" width="16" aria-hidden="true"></iconify-icon>
                          Unduh format-import-barang.xlsx
                        </a>
                      </div>
                    </div>
                  </div>

                  <div x-cloak x-show="importError"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                    role="alert">
                    <span x-text="importError"></span>
                    <button x-show="importPollTimedOut" type="button" @click="retryBarangImportStatus()"
                      class="ml-2 inline-flex items-center gap-1 font-semibold underline">
                      Periksa status lagi
                    </button>
                  </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                  <button type="button" @click="closeImport()"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                    Batal
                  </button>
                  <button type="submit"
                    :disabled="importLoading || importQueued || importError || !importFiles.some(file => !file.isZip)"
                    :aria-busy="(importLoading || importQueued).toString()"
                    :aria-label="importLoading || importQueued ? 'Sedang mengimpor barang' : 'Simpan Import'"
                    class="admin-submit-button akun-primary-button inline-flex items-center justify-center gap-2 rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2 disabled:cursor-not-allowed">
                    <iconify-icon x-show="importLoading || importQueued" icon="mdi:loading"
                      class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
                    <iconify-icon x-show="!importLoading && !importQueued" icon="mdi:upload" width="16"
                      aria-hidden="true"></iconify-icon>
                    <span x-show="!importLoading && !importQueued">Simpan Import</span>
                    <span x-show="importLoading || importQueued">Mengimpor...</span>
                  </button>
                </div>
              </form>
            </section>
          </div>
          <div x-cloak x-show="modalOpen" x-transition.opacity
            class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
            @click.self="closeModal()" role="presentation">
            <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" @click="closeModal()"></div>
            <section x-show="modalOpen" x-transition
              class="relative z-10 my-auto w-full max-w-4xl overflow-hidden rounded-xl border border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
              role="dialog" aria-modal="true" aria-labelledby="barang-modal-title">
              <form :action="formAction" method="POST" enctype="multipart/form-data"
                @submit="formSubmitting = true">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="modalMode !== 'edit'">
                <input type="hidden" name="barang_form" :value="modalMode">
                <input type="hidden" name="barang_id" x-model="currentItem.barang_id">
                <input type="hidden" name="kode_barang" x-model="currentItem.kode_barang"
                  :disabled="modalMode !== 'edit'">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                  <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                      <iconify-icon
                        :icon="modalMode === 'create' ? 'mdi:package-variant-plus' : 'mdi:package-variant'"
                        width="22" aria-hidden="true"></iconify-icon>
                    </span>
                    <h2 id="barang-modal-title" class="text-base font-semibold text-slate-800"
                      x-text="modalMode === 'create' ? 'Tambah Barang' : 'Edit Barang'"></h2>
                  </div>
                  <button type="button" @click="closeModal()"
                    class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Tutup formulir">
                    <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
                  </button>
                </div>

                <div
                  class="grid max-h-[calc(100dvh-12rem)] grid-cols-1 gap-4 overflow-y-auto px-5 py-4 sm:grid-cols-2">
                  <label class="text-xs font-medium text-slate-700">
                    Nama Barang
                    <input name="nama_barang" type="text" maxlength="150" required
                      x-model="currentItem.nama_barang"
                      class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                      placeholder="Nama barang">
                    @error('nama_barang')
                      <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                    @enderror
                  </label>

                  <label class="text-xs font-medium text-slate-700">
                    Kategori
                    <select name="kategori_id" x-model="currentItem.kategori_id" required
                      class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                      <option value="">Pilih kategori</option>
                      @foreach ($kategoris as $kategori)
                        <option value="{{ $kategori->kategori_id }}">{{ $kategori->nama_kategori }}</option>
                      @endforeach
                    </select>
                    @error('kategori_id')
                      <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                    @enderror
                  </label>

                  <label x-show="modalMode === 'create'" class="text-xs font-medium text-slate-700">
                    Kondisi
                    <select name="kondisi" x-model="currentItem.kondisi" :disabled="modalMode !== 'create'"
                      required
                      class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                      <option value="baru">Baru</option>
                      <option value="lama">Lama</option>
                    </select>
                  </label>

                  <label x-show="modalMode === 'create'" class="text-xs font-medium text-slate-700">
                    Kelayakan
                    <select name="kelayakan" x-model="currentItem.kelayakan" :disabled="modalMode !== 'create'"
                      required
                      class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                      <option value="layak">Layak</option>
                      <option value="tidak_layak">Tidak layak</option>
                    </select>
                  </label>

                  <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-3">
                    <label class="text-xs font-medium text-slate-700">
                      Jumlah total barang
                      <input name="stok_total" type="number" min="0" step="1" required
                        x-model.number="currentItem.stok_total" @input="syncStockTotals('total')"
                        class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                        placeholder="0">
                      @error('stok_total')
                        <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                      @enderror
                    </label>

                    <label class="text-xs font-medium text-slate-700">
                      Jumlah tersedia barang
                      <input name="stok_tersedia" type="number" min="0" step="1" required
                        x-model.number="currentItem.stok_tersedia" @input="syncStockTotals('available')"
                        :max="Math.max(0, currentItem.stok_total - reservedStockTotal())"
                        class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                        placeholder="0">
                      @error('stok_tersedia')
                        <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                      @enderror
                    </label>

                    <label class="text-xs font-medium text-slate-700">
                      Satuan
                      <select name="satuan" required x-model="currentItem.satuan"
                        class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                        <option value="">Pilih satuan</option>
                        <option value="PCS">PCS</option>
                        <option value="METER">METER</option>
                        <option value="UNIT">UNIT</option>
                        <option value="BUAH">BUAH</option>
                        <option value="ROLL">ROLL</option>
                        <option value="SET">SET</option>
                        <option value="LAINNYA">LAINNYA</option>
                      </select>
                      @error('satuan')
                        <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                      @enderror
                    </label>
                  </div>

                  <section class="min-w-0 sm:col-span-2" aria-label="Lokasi stok barang">
                    <div class="mb-2 flex items-center justify-between gap-3">
                      <p class="text-xs font-semibold text-slate-700">Stok per lokasi</p>
                      <button type="button" @click="addStockLocation()"
                        :disabled="currentItem.locations.length >= 50"
                        class="inline-flex items-center gap-1 rounded-md border border-[#B9DED4] px-3 py-1.5 text-xs font-semibold text-[#007f70] transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50">
                        <iconify-icon icon="mdi:plus" aria-hidden="true"></iconify-icon>
                        Tambah lokasi
                      </button>
                    </div>
                    <div class="w-full min-w-0 space-y-2 overflow-x-auto overscroll-x-contain touch-pan-x">
                      <template x-for="(location, index) in currentItem.locations" :key="index">
                        <div
                          class="grid w-max min-w-full items-end gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3"
                          :style="stockLocationGridStyle()">
                          <input type="hidden" :name="`locations[${index}][ruangan_id]`"
                            :value="location.ruangan_id" :disabled="Number(location.reserved_stock) <= 0">
                          <label class="block min-w-0 text-[11px] font-semibold text-slate-600">
                            Ruangan
                            <span class="relative mt-1 block">
                              <select :name="`locations[${index}][ruangan_id]`" x-model="location.ruangan_id"
                                @change="setStockLocationRoom(location, $event.target.value)" required
                                :disabled="Number(location.reserved_stock) > 0"
                                class="block w-full appearance-none rounded-md border border-slate-300 bg-white py-2 pl-2.5 pr-12 text-xs font-normal text-slate-800 disabled:bg-slate-100">
                                <option value="">Pilih ruangan</option>
                                @foreach ($ruangans as $ruangan)
                                  <option value="{{ $ruangan->ruangan_id }}">{{ $ruangan->nama_ruangan }}</option>
                                @endforeach
                              </select>
                              <iconify-icon icon="mdi:chevron-down" width="17"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"
                                aria-hidden="true"></iconify-icon>
                            </span>
                          </label>
                          <input type="hidden" :name="`locations[${index}][lemari_id]`" :value="location.lemari_id"
                            :disabled="Number(location.reserved_stock) <= 0">
                          <label class="block min-w-0 text-[11px] font-semibold text-slate-600">
                            Lemari
                            <span class="relative mt-1 block">
                              <select :name="`locations[${index}][lemari_id]`" x-model="location.lemari_id"
                                :disabled="!location.ruangan_id || Number(location.reserved_stock) > 0"
                                class="block w-full appearance-none rounded-md border border-slate-300 bg-white py-2 pl-2.5 pr-12 text-xs font-normal text-slate-800 disabled:bg-slate-100">
                                <option value="">Tanpa lemari</option>
                                <template
                                  x-for="cabinet in lemariOptions.filter((item) => item.ruangan_id === location.ruangan_id)"
                                  :key="cabinet.lemari_id">
                                  <option :value="cabinet.lemari_id" x-text="cabinet.nama_lemari"></option>
                                </template>
                              </select>
                              <iconify-icon icon="mdi:chevron-down" width="17"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"
                                aria-hidden="true"></iconify-icon>
                            </span>
                          </label>
                          <input type="hidden" :name="`locations[${index}][toolbox_id]`"
                            :value="location.toolbox_id" :disabled="Number(location.reserved_stock) <= 0">
                          <label class="block min-w-0 text-[11px] font-semibold text-slate-600">
                            Wadah
                            <span class="relative mt-1 block">
                              <select :name="`locations[${index}][toolbox_id]`" x-model="location.toolbox_id"
                                :disabled="Number(location.reserved_stock) > 0"
                                class="block w-full appearance-none rounded-md border border-slate-300 bg-white py-2 pl-2.5 pr-12 text-xs font-normal text-slate-800 disabled:bg-slate-100">
                                <option value="">Tanpa wadah</option>
                                <template x-for="toolbox in toolboxOptions" :key="toolbox.toolbox_id">
                                  <option :value="toolbox.toolbox_id"
                                    x-text="`${toolbox.kode_toolbox} · ${toolbox.nama_toolbox}`"></option>
                                </template>
                              </select>
                              <iconify-icon icon="mdi:chevron-down" width="17"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"
                                aria-hidden="true"></iconify-icon>
                            </span>
                          </label>
                          <label class="block min-w-0 whitespace-nowrap text-[11px] font-semibold text-slate-600">
                            Jumlah total lokasi
                            <input type="number" min="0" step="1" required
                              :name="`locations[${index}][stok_total]`" x-model.number="location.stok_total"
                              :min="Number(location.reserved_stock) || 0"
                              @input="syncStockTotals('location_total', index)"
                              class="mt-1 block w-full min-w-0 rounded-md border border-slate-300 bg-white px-2 py-2 text-xs font-normal text-slate-800">
                          </label>
                          <label class="block min-w-0 whitespace-nowrap text-[11px] font-semibold text-slate-600">
                            Jumlah tersedia lokasi
                            <input type="number" min="0" step="1" required
                              :name="`locations[${index}][stok_tersedia]`" x-model.number="location.stok_tersedia"
                              :max="Math.max(0, Number(location.stok_total) - Number(location.reserved_stock))"
                              @input="syncStockTotals('location_available', index)"
                              class="mt-1 block w-full min-w-0 rounded-md border border-slate-300 bg-white px-2 py-2 text-xs font-normal text-slate-800">
                          </label>
                          <p x-show="Number(location.reserved_stock) > 0"
                            class="col-start-6 text-[10px] font-normal text-amber-700">
                            Stok yang sedang dipinjam/dipakai tetap di lokasi ini.
                          </p>
                          <button type="button" @click="removeStockLocation(index)"
                            :disabled="currentItem.locations.length <= 1 || Number(location.reserved_stock) > 0"
                            class="col-start-7 flex h-8 items-center justify-center self-end rounded-md px-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label="Hapus lokasi">
                            <iconify-icon icon="mdi:trash-can-outline" width="17"
                              aria-hidden="true"></iconify-icon>
                          </button>
                        </div>
                      </template>
                    </div>
                    @error('locations')
                      <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                    @error('locations.*.ruangan_id')
                      <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                    @error('locations.*.lemari_id')
                      <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                    @error('locations.*.toolbox_id')
                      <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                    @error('locations.*.stok_total')
                      <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                    @error('locations.*.stok_tersedia')
                      <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                  </section>

                  <label class="text-xs font-medium text-slate-700 sm:col-span-2">
                    Spesifikasi
                    <textarea name="spesifikasi" maxlength="255" rows="2" x-model="currentItem.spesifikasi"
                      class="mt-1 w-full resize-y rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                      placeholder="Keterangan teknis barang"></textarea>
                    @error('spesifikasi')
                      <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                    @enderror
                  </label>

                  <div class="sm:col-span-2">
                    <span class="text-xs font-medium text-slate-700">
                      Foto Barang <span class="font-normal text-slate-500">(JPG/PNG/WEBP, maks. 5 MB)</span>
                    </span>
                    <input x-ref="barangImageInput" name="image" type="file" accept=".jpg,.jpeg,.png,.webp"
                      @change="previewBarangImage($event)" class="hidden">
                    <div role="button" tabindex="0" @click="openBarangImagePicker()"
                      @keydown.enter.prevent="openBarangImagePicker()"
                      @keydown.space.prevent="openBarangImagePicker()" @dragover.prevent
                      @drop.prevent="handleBarangImageDrop($event)"
                      class="mt-1 flex min-h-28 cursor-pointer items-center gap-4 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 p-4 transition hover:border-[#08A9D6] hover:bg-[#F0FDFA] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#08A9D6] focus-visible:ring-offset-2">
                      <img x-show="imagePreview" :src="imagePreview" alt="Pratinjau gambar barang"
                        class="h-20 w-20 shrink-0 rounded-md border border-slate-200 bg-white object-cover">
                      <span x-show="!imagePreview"
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                        <iconify-icon icon="mdi:image-plus-outline" width="28" aria-hidden="true"></iconify-icon>
                      </span>
                      <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-800"
                          x-text="imagePreviewName || (imagePreview ? 'Gambar saat ini' : 'Pilih atau seret gambar ke sini')"></span>
                        <span class="mt-1 block text-xs text-slate-500">Klik area ini atau seret satu gambar ke sini.</span>
                      </span>
                      <button x-show="imagePreviewName" type="button" @click.stop="removeBarangImage()"
                        class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                        Batalkan gambar baru
                      </button>
                    </div>
                    @error('image')
                      <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                    @enderror
                  </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                  <button type="button" @click="closeModal()"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                    Batal
                  </button>
                  <button type="submit" :disabled="formSubmitting" :aria-busy="formSubmitting.toString()"
                    class="admin-submit-button inline-flex items-center justify-center gap-2 rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                    <iconify-icon x-show="formSubmitting" icon="mdi:loading" class="animate-spin text-lg"
                      aria-hidden="true"></iconify-icon>
                    <iconify-icon x-show="!formSubmitting"
                      :icon="modalMode === 'create' ? 'mdi:content-save-plus-outline' : 'mdi:content-save-edit-outline'"
                      width="18" aria-hidden="true"></iconify-icon>
                    <span x-show="!formSubmitting" class="ml-1"
                      x-text="modalMode === 'create' ? 'Simpan Barang' : 'Simpan Perubahan'"></span>
                    <span x-show="formSubmitting">Menyimpan...</span>
                  </button>
                </div>
              </form>
            </section>
          </div>
          <div x-cloak x-show="deleteModalOpen" x-transition.opacity
            class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-950/55 p-4 backdrop-blur-sm sm:p-6"
            @click.self="cancelDelete()" role="presentation">
            <section x-show="deleteModalOpen" x-transition
              class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
              role="alertdialog" aria-modal="true" aria-labelledby="barang-delete-title"
              aria-describedby="barang-delete-description">
              <div class="p-6 text-center">
                <span
                  class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                  <iconify-icon icon="mdi:alert-circle" width="28" aria-hidden="true"></iconify-icon>
                </span>
                <h2 id="barang-delete-title" class="text-lg font-semibold text-slate-900">Hapus Barang?</h2>
                <p id="barang-delete-description" class="mt-2 text-sm text-slate-600">
                  Barang <strong class="font-bold text-slate-900" x-text="pendingDeleteName"></strong> akan
                  disembunyikan dari daftar. Riwayat dan data lokasinya tetap tersimpan.
                  Barang yang masih dipinjam atau digunakan tidak dapat dihapus.
                </p>
              </div>
              <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" @click="cancelDelete()" :disabled="deleteLoading"
                  class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60">
                  Batal
                </button>
                <button type="button" @click="confirmDelete()" :disabled="deleteLoading"
                  :aria-busy="deleteLoading.toString()"
                  class="inline-flex min-w-32 items-center justify-center gap-2 rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-70">
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
