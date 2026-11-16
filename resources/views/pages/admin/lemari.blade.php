<x-layout.admin title="Rak Penyimpanan - InvenTrack" headerTitle="Rak Penyimpanan"
  headerDescription="Kelola lemari penyimpanan per ruangan.">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <style>
    .rak-surface {
      box-sizing: border-box;
      min-height: max(0px, calc(100vh - 10rem));
      margin-top: -2rem;
      background-color: #e8f8f4;
    }

    html.rak-modal-open main {
      overflow: hidden;
    }

    .rak-primary-button {
      background: #00b19c;
      border: 1px solid #009f91;
      color: #fff;
    }

    .rak-primary-button:hover {
      background: #009a87;
    }

    .rak-modal-backdrop {
      background: linear-gradient(135deg, rgba(7, 59, 86, .84), rgba(2, 6, 23, .76), rgba(6, 78, 72, .78));
      backdrop-filter: blur(9px);
    }

    .rak-modal-panel {
      border: 2px solid #08a9d6;
      box-shadow: 0 24px 80px rgba(2, 25, 41, .4);
    }

    .rak-form-field {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 0.375rem;
      background: #fff;
      padding: 0.5rem 0.75rem;
      color: #1e293b;
    }

    @keyframes shake {

      0%,
      100% {
        transform: translateX(0);
      }

      25% {
        transform: translateX(-5px);
      }

      75% {
        transform: translateX(5px);
      }
    }
  </style>

  <div x-data="{
      openModal: @js($errors->any() && in_array(old('lemari_form'), ['create', 'edit'], true)),
      modalMode: @js(old('lemari_form') === 'edit' ? 'edit' : 'create'),
      currentLemari: {},
      deleteModalOpen: false,
      deleteShaking: false,
      feedbackType: @js(session()->has('error') ? 'error' : 'success'),
      feedbackMessage: @js((string) (session('success') ?? session('error', ''))),
      feedbackLemariName: '',
      feedbackLemariAction: '',
      pendingDeleteForm: null,
      pendingDeleteLemariName: '',
      searchQuery: @js($filters['search'] ?? ''),
      filterRuangan: @js($filters['ruangan_id'] ?? ''),
      resultsUrl: @js(route('admin.rak.index')),
      filterRequest: null,
      searchTimer: null,
      previewCount: null,
      filterLoading: false,
      filterError: '',
      formBusy: false,
      csrf: '{{ csrf_token() }}',
      ruangans: @js($ruangans),

      init() {
          this.feedbackMessage = this.normalizeLemariMessage(this.feedbackMessage);
          if (this.modalMode === 'edit' && this.openModal) {
              this.currentLemari = {
                  lemari_id: @js(old('lemari_id')),
                  ruangan_id: @js(old('ruangan_id')),
                  kode_lemari: @js(old('kode_lemari', '')),
                  nama_lemari: @js(old('nama_lemari', '')),
                  keterangan: @js(old('keterangan', '')),
              };
          } else {
              this.currentLemari = {
                  ruangan_id: this.filterRuangan || '',
                  kode_lemari: '',
                  nama_lemari: '',
                  keterangan: ''
              };
          }
          if (this.feedbackMessage) this.notifyFeedback();
      },
      notifyFeedback() {
          const message = this.feedbackLemariName ?
              [
                  { text: 'Lemari ' },
                  { text: this.feedbackLemariName, bold: true },
                  { text: ` berhasil ${this.feedbackLemariAction}.` },
              ] :
              this.feedbackMessage;
          window.showToast(message, this.feedbackType);
      },

      createLemari() {
          this.modalMode = 'create';
          this.currentLemari = {
              ruangan_id: this.filterRuangan || '',
              kode_lemari: '',
              nama_lemari: '',
              keterangan: ''
          };
          this.openModal = true;
      },
      closeLemariModal() { this.openModal = false; },

      editLemari(lemari) {
          this.modalMode = 'edit';
          this.currentLemari = {
              lemari_id: lemari.lemari_id,
              ruangan_id: lemari.ruangan_id,
              kode_lemari: lemari.kode_lemari,
              nama_lemari: lemari.nama_lemari,
              keterangan: lemari.keterangan || ''
          };
          this.openModal = true;
      },

      getRuanganName(ruanganId) {
          const ruangan = this.ruangans.find(r => r.ruangan_id === ruanganId);
          return ruangan ? ruangan.nama_ruangan : '-';
      },
      normalizeLemariMessage(message) {
          return message
              .replace(/^Lemari\/Rak berhasil ditambahkan\.$/i, 'Lemari berhasil ditambahkan.')
              .replace(/^Lemari\/Rak berhasil diperbarui\.$/i, 'Lemari berhasil diperbarui.')
              .replace(/^Lemari\/Rak berhasil dihapus\.$/i, 'Lemari berhasil dihapus.');
      },

      async submitLemari(event) { await this.sendCrudRequest(event.target); },
      async sendCrudRequest(form) {
          this.formBusy = true;
          this.feedbackLemariName = '';
          this.feedbackLemariAction = '';
          try {
              const formData = new FormData(form);
              const isDeleteRequest = formData.get('_method') === 'DELETE';
              const lemariAction = isDeleteRequest ?
                  'dihapus' :
                  formData.get('lemari_form') === 'edit' ? 'diperbarui' : 'ditambahkan';
              const lemariName = isDeleteRequest ?
                  this.pendingDeleteLemariName :
                  String(formData.get('nama_lemari') || '').trim();
              const response = await fetch(form.action, {
                  method: form.method === 'DELETE' ? 'DELETE' : 'POST',
                  body: formData,
                  headers: { 'Accept': 'text/html', 'X-CSRF-TOKEN': this.csrf }
              });
              const html = await response.text();
              const responseDocument = new DOMParser().parseFromString(html, 'text/html');
              const validationMessage = responseDocument.querySelector('[data-lemari-validation]')?.dataset.lemariValidation;
              const successMessage = responseDocument.querySelector('[data-lemari-success]')?.dataset.lemariSuccess;
              const errorMessage = responseDocument.querySelector('[data-lemari-error]')?.dataset.lemariError;

              if (validationMessage) {
                  this.openModal = false;
                  this.feedbackType = 'error';
                  this.feedbackMessage = validationMessage;
                  this.notifyFeedback();
                  return false;
              }
              if (!response.ok) { throw new Error('Permintaan tidak berhasil.'); }
              if (errorMessage) {
                  this.openModal = false;
                  this.feedbackType = 'error';
                  this.feedbackMessage = errorMessage;
                  this.notifyFeedback();
                  return false;
              }
              if (!successMessage) { throw new Error('Server tidak mengirim konfirmasi penyimpanan.'); }
              this.openModal = false;
              this.feedbackLemariName = lemariName;
              this.feedbackLemariAction = lemariAction;
              this.feedbackMessage = this.normalizeLemariMessage(successMessage);
              this.notifyFeedback();
              await this.filterLemari();
              return true;
          } catch (error) {
              this.openModal = false;
              this.feedbackType = 'error';
              this.feedbackMessage = error.message || 'Terjadi kesalahan saat menyimpan.';
              this.notifyFeedback();
              return false;
          } finally { this.formBusy = false; }
      },

      requestDelete(form) {
          this.pendingDeleteForm = form;
          this.pendingDeleteLemariName = form.dataset.lemariName;
          this.deleteModalOpen = true;
      },
      cancelDelete() {
          this.deleteModalOpen = false;
          this.pendingDeleteForm = null;
          this.pendingDeleteLemariName = '';
          this.deleteShaking = false;
      },
      shakeDelete() {
          this.deleteShaking = false;
          window.setTimeout(() => this.deleteShaking = true, 10);
          window.setTimeout(() => this.deleteShaking = false, 460);
      },
      handleEscape(event) {
          if (this.deleteModalOpen) {
              event.preventDefault();
              this.shakeDelete();
              return;
          }
          this.openModal = false;
      },
      async submitDelete() {
          if (!this.pendingDeleteForm) return;
          if (await this.sendCrudRequest(this.pendingDeleteForm)) this.cancelDelete();
      },
      handleLemariDelete(event) {
          const form = event.target instanceof HTMLFormElement ? event.target : null;
          if (form?.matches('form[data-lemari-name]')) this.requestDelete(form);
      },
      searchLemari() {
          const rows = Array.from(this.$refs.lemariResults.querySelectorAll('tr[data-searchable]'));
          const query = this.searchQuery.trim().toLocaleLowerCase();
          rows.forEach(row => row.hidden = false);
          const matchingRows = rows.filter(row =>
              (row.dataset.searchable || '').includes(query) &&
              (!this.filterRuangan || row.dataset.ruanganId === this.filterRuangan)
          );

          if (matchingRows.length > 0) {
              rows.forEach(row => row.hidden = !matchingRows.includes(row));
              this.previewCount = matchingRows.length;
          } else {
              this.previewCount = null;
          }

          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => {
              this.searchTimer = null;
              this.filterLemari();
          }, 100);
      },
      async filterLemari(url = null) {
          window.clearTimeout(this.searchTimer);
          this.searchTimer = null;
          if (this.filterRequest) this.filterRequest.abort();
          const request = new AbortController();
          this.filterRequest = request;
          this.filterLoading = true;
          this.filterError = '';

          const requestUrl = new URL(url || this.resultsUrl, window.location.href);
          if (!url) {
              requestUrl.search = new URLSearchParams({
                  ruangan_id: this.filterRuangan,
                  search: this.searchQuery.trim(),
              }).toString();
          }

          try {
              const response = await fetch(requestUrl, {
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                  signal: request.signal,
              });
              if (!response.ok || response.redirected) throw new Error('Daftar rak tidak dapat dimuat.');

              const fragment = await response.text();
              this.$refs.lemariResults.innerHTML = fragment;
              this.previewCount = null;
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.filterError = error.message || 'Daftar rak tidak dapat dimuat.';
                  this.$refs.lemariResults.querySelectorAll('tr[data-searchable]').forEach(row => row.hidden = false);
                  this.previewCount = null;
              }
          } finally {
              if (this.filterRequest === request) {
                  this.filterLoading = false;
                  this.filterRequest = null;
              }
          }
      },
      handleLemariPagination(event) {
          const target = event.target instanceof Element ? event.target : null;
          const editButton = target?.closest('[data-lemari]');
          if (editButton && this.$refs.lemariResults.contains(editButton)) {
              this.editLemari(JSON.parse(editButton.dataset.lemari));
              return;
          }

          const link = target?.closest('a');
          if (!link || !this.$refs.lemariResults.contains(link)) return;
          event.preventDefault();
          this.filterLemari(link.href);
      }
  }" @keydown.escape.window="handleEscape($event)"
    x-effect="document.documentElement.classList.toggle('rak-modal-open', openModal || deleteModalOpen)"
    class="rak-surface relative -mx-8 -mb-8 min-h-full bg-[#E8F8F4]">

    <div class="p-4 sm:p-8">
      <div class="hidden" data-lemari-validation="{{ $errors->first() }}" data-lemari-success="{{ session('success') }}"
        data-lemari-error="{{ session('error') }}"></div>

      <div class="rak-toolbar mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        @if (auth()->user()?->canManageInventory())
          <div class="flex flex-wrap gap-2">
            <button type="button" @click="createLemari()"
              class="rak-primary-button inline-flex items-center gap-2 rounded-lg border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009a87] focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
              <iconify-icon icon="mdi:archive-plus-outline" width="18" aria-hidden="true"></iconify-icon> Tambah
              Lemari
            </button>
          </div>
        @endif
        <div class="flex min-w-0 flex-col gap-3 sm:ml-auto sm:flex-row sm:items-center">
          <select x-model="filterRuangan" @change="filterLemari()"
            class="w-fit max-w-full min-w-0 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20 sm:shrink-0">
            <option value="">Semua Ruangan</option>
            @foreach ($ruangans as $r)
              <option value="{{ $r->ruangan_id }}">{{ $r->nama_ruangan }}</option>
            @endforeach
          </select>
          <label class="rak-search relative block w-full min-w-0 sm:w-72 sm:flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center"><iconify-icon
                icon="mdi:magnify" width="17" class="block text-slate-400"></iconify-icon></span>
            <input type="search" name="search" x-model="searchQuery" @input="searchLemari()" placeholder="Cari Rak..."
              class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm leading-5 text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
          </label>
        </div>
      </div>

      <div x-cloak x-show="filterError"
        class="mb-3 flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800"
        role="alert">
        <span x-text="filterError"></span>
        <button type="button" @click="filterLemari()"
          class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">
          Coba lagi
        </button>
      </div>
      <div x-ref="lemariResults" @click="handleLemariPagination($event)" @submit.prevent="handleLemariDelete($event)"
        :aria-busy="filterLoading.toString()">
        @fragment('lemari-results')
          <div class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm mb-8">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-[#DCE9E6]">
                <thead class="bg-[#F1F8F6]">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Kode Lemari</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Nama Lemari</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Ruangan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Jumlah Barang</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Keterangan</th>
                    @if (auth()->user()?->canManageInventory())
                      <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600">Aksi</th>
                    @endif
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#E7EFED] bg-white">
                  @forelse ($lemaris as $lemari)
                    <tr data-searchable="{{ strtolower($lemari->kode_lemari . ' ' . $lemari->nama_lemari) }}"
                      data-ruangan-id="{{ $lemari->ruangan_id }}" class="transition-colors hover:bg-[#F7FBFA]">
                      <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-800">
                        {{ $lemari->kode_lemari }}</td>
                      <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $lemari->nama_lemari }}</td>
                      <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lemari->ruangan->nama_ruangan }}
                      </td>
                      <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lemari->lokasi_barangs_count }}
                        Barang
                      </td>
                      <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lemari->keterangan ?? '-' }}
                      </td>
                      @if (auth()->user()?->canManageInventory())
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                          <div class="flex justify-end gap-2">
                            {{-- ASVS 1.2.1, 1.2.3: Encode row data for its HTML attribute and JSON contexts. --}}
                            <button type="button"
                              data-lemari="{{ json_encode(
                                  [
                                      'lemari_id' => $lemari->lemari_id,
                                      'ruangan_id' => $lemari->ruangan_id,
                                      'kode_lemari' => $lemari->kode_lemari,
                                      'nama_lemari' => $lemari->nama_lemari,
                                      'keterangan' => $lemari->keterangan,
                                  ],
                                  JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG,
                              ) }}"
                              class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                              <iconify-icon icon="mdi:pencil" width="15"></iconify-icon> Edit
                            </button>
                            <form action="{{ route('admin.rak.destroy', $lemari->lemari_id) }}" method="POST"
                              data-lemari-name="{{ $lemari->nama_lemari }}">
                              @csrf @method('DELETE')
                              <button type="submit"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                                <iconify-icon icon="mdi:delete-outline" width="16" aria-hidden="true"></iconify-icon>
                                Hapus
                              </button>
                            </form>
                          </div>
                        </td>
                      @endif
                    </tr>
                  @empty
                    <tr>
                      <td colspan="6" class="px-6 py-12 text-center">
                        <iconify-icon icon="mdi:bookshelf" class="mb-2 text-slate-300" width="42"
                          aria-hidden="true"></iconify-icon>
                        @if (filled($filters['search'] ?? null) || filled($filters['ruangan_id'] ?? null))
                          {{-- ASVS V1.2.1: Blade-escape the search keyword and room name in HTML text context. --}}
                          <p class="text-sm font-medium text-slate-600">
                            Tidak ada lemari yang cocok
                            @if (filled($filters['search'] ?? null))
                              dengan kata kunci <strong
                                class="font-bold text-slate-900">"{{ $filters['search'] }}"</strong>
                              @if (!$filteredRuangan)
                                .
                              @endif
                            @endif
                            @if ($filteredRuangan)
                              @if (filled($filters['search'] ?? null))
                                dan
                              @else
                                dengan
                              @endif
                              ruangan
                              <strong class="font-bold text-slate-900">{{ $filteredRuangan->nama_ruangan }}</strong>.
                            @endif
                          </p>
                        @else
                          <p class="text-sm font-medium text-slate-600">Belum ada lemari yang terdaftar.</p>
                        @endif
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div
              class="flex flex-col gap-3 border-t border-[#E2ECE9] bg-[#F7FBFA] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs text-slate-500"
                x-text="previewCount === null ? 'Menampilkan {{ $lemaris->count() }} dari {{ $lemaris->total() }} lemari' : `Menampilkan ${previewCount} hasil sementara`">
                Menampilkan {{ $lemaris->count() }} dari {{ $lemaris->total() }} lemari
              </p>
              <x-pagination :paginator="$lemaris" label="Navigasi halaman lemari" />
            </div>
          </div>
        @endfragment
      </div>

      @if (auth()->user()?->canManageInventory())
        <template x-teleport="body">
          {{-- MODAL: Tambah / Edit Lemari --}}
          <div x-show="openModal" x-transition.opacity style="display: none; z-index: 80;"
            class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto p-4 sm:p-6" role="dialog"
            aria-modal="true">
            <div class="rak-modal-backdrop absolute inset-0" @click="closeLemariModal()"></div>
            <div x-show="openModal" x-transition
              class="rak-modal-panel relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">
              <form
                :action="modalMode === 'create' ? '{{ route('admin.rak.store') }}' :
                    '{{ route('admin.rak.update', '__ID__') }}'
                    .replace('__ID__', currentLemari.lemari_id)"
                method="POST" @submit.prevent="submitLemari($event)">
                @csrf
                <input type="hidden" name="lemari_form" :value="modalMode">
                <template x-if="modalMode === 'edit'">
                  <div><input type="hidden" name="_method" value="PUT"><input type="hidden" name="lemari_id"
                      :value="currentLemari.lemari_id"></div>
                </template>

                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                  <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                      <iconify-icon
                        :icon="modalMode === 'create' ? 'mdi:archive-plus-outline' : 'mdi:archive-edit-outline'"
                        width="22" aria-hidden="true"></iconify-icon>
                    </span>
                    <h2 class="text-base font-semibold text-slate-800"
                      x-text="modalMode === 'create' ? 'Tambah Lemari' : 'Edit Lemari'"></h2>
                  </div>
                  <button type="button" @click="closeLemariModal()"
                    class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"><iconify-icon
                      icon="mdi:close" width="20"></iconify-icon></button>
                </div>

                <div class="px-5 py-4 space-y-4">
                  <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Ruangan <span
                        class="text-rose-500">*</span></label>
                    <select name="ruangan_id" x-model="currentLemari.ruangan_id" required
                      class="rak-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                      <option value="">Pilih Ruangan</option>
                      <template x-for="ruangan in ruangans" :key="ruangan.ruangan_id">
                        <option :value="ruangan.ruangan_id" x-text="ruangan.nama_ruangan"></option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Kode Lemari <span
                        class="text-rose-500">*</span></label>
                    <input type="text" name="kode_lemari" x-model="currentLemari.kode_lemari" required
                      maxlength="20" placeholder="Contoh: LMR-001" aria-describedby="lemari-code-hint"
                      class="rak-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Nama Lemari <span
                        class="text-rose-500">*</span></label>
                    <input type="text" name="nama_lemari" x-model="currentLemari.nama_lemari" required
                      maxlength="100" placeholder="Contoh: Lemari Lab RPL D"
                      class="rak-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Keterangan</label>
                    <textarea name="keterangan" x-model="currentLemari.keterangan" rows="3" maxlength="255"
                      placeholder="Opsional"
                      class="rak-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"></textarea>
                  </div>
                </div>

                <div class="rak-modal-footer flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                  <button type="button" @click="closeLemariModal()"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal</button>
                  <button type="submit" :disabled="formBusy" :aria-busy="formBusy.toString()"
                    class="admin-submit-button rak-primary-button inline-flex items-center justify-center gap-2 rounded-md border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
                    <iconify-icon x-show="formBusy" icon="mdi:loading" class="animate-spin text-lg"
                      aria-hidden="true"></iconify-icon>
                    <iconify-icon x-show="!formBusy"
                      :icon="modalMode === 'create' ? 'mdi:content-save-plus-outline' : 'mdi:content-save-edit-outline'"
                      width="18" aria-hidden="true"></iconify-icon>
                    <span x-show="!formBusy"
                      x-text="modalMode === 'create' ? 'Simpan Lemari' : 'Simpan Perubahan'"></span>
                    <span x-show="formBusy">Menyimpan...</span>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </template>

        <template x-teleport="body">
          {{-- MODAL: Konfirmasi Hapus --}}
          <div x-show="deleteModalOpen" x-transition.opacity style="display: none; z-index: 100;"
            class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto p-4 sm:p-6" role="dialog"
            aria-modal="true">
            <div class="rak-modal-backdrop absolute inset-0" @click="if (!formBusy) cancelDelete()"></div>
            <div x-show="deleteModalOpen" x-transition
              :class="deleteShaking ? 'animate-[shake_0.4s_ease-in-out]' : ''"
              class="rak-modal-panel relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">

              <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100">
                  <iconify-icon icon="mdi:alert-circle" class="text-rose-600" width="28"></iconify-icon>
                </div>
                <h3 class="text-lg font-semibold text-slate-900">Hapus Lemari?</h3>
                <p class="mt-2 text-sm text-slate-600">
                  Apakah Anda yakin ingin menghapus lemari
                  <span class="font-bold text-slate-800" x-text="pendingDeleteLemariName"></span> dari daftar lemari?
                </p>
              </div>
              <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" @click="cancelDelete()" :disabled="formBusy"
                  class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal</button>
                <button type="button" @click="submitDelete()" :disabled="formBusy"
                  :aria-busy="formBusy.toString()"
                  class="inline-flex min-w-28 items-center justify-center gap-2 rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                  <iconify-icon x-show="formBusy" icon="mdi:loading" class="animate-spin" width="18"
                    aria-hidden="true"></iconify-icon>
                  <iconify-icon x-show="!formBusy" icon="mdi:delete-outline" class="text-lg"
                    aria-hidden="true"></iconify-icon>
                  <span x-show="!formBusy">Ya, Hapus</span>
                  <span x-show="formBusy">Menghapus...</span>
                </button>
              </div>
            </div>
          </div>
        </template>
      @endif

    </div>
</x-layout.admin>
