<x-layout.admin title="Ruangan - InvenTrack" headerTitle="Ruangan"
  headerDescription="Daftar ruangan sebagai acuan lokasi baku.">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <style>
    .ruangan-surface {
      box-sizing: border-box;
      min-height: max(0px, calc(100vh - 10rem));
      margin-top: -2rem;
      background-color: #e8f8f4;
    }

    .ruangan-primary-button {
      background: #00b19c;
      border: 1px solid #009f91;
      color: #fff;
    }

    .ruangan-primary-button:hover {
      background: #009a87;
    }

    .ruangan-modal-backdrop {
      background: linear-gradient(135deg, rgba(7, 59, 86, .84), rgba(2, 6, 23, .76), rgba(6, 78, 72, .78));
      backdrop-filter: blur(9px);
    }

    .ruangan-modal-panel {
      border: 2px solid #08a9d6;
      box-shadow: 0 24px 80px rgba(2, 25, 41, .4);
    }

    .ruangan-card {
      background: white;
      border-radius: 0.75rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      transition: all 0.2s ease;
      border: 1px solid #d3e3df;
    }

    .ruangan-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 177, 156, 0.15);
    }

    .ruangan-form-field {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 0.375rem;
      background: #fff;
      padding: 0.5rem 0.75rem;
      color: #1e293b;
    }

    .ruangan-toggle {
      position: relative;
      display: inline-flex;
      width: 2.5rem;
      height: 1.4rem;
      flex: none;
      cursor: pointer;
    }

    .ruangan-toggle input {
      position: absolute;
      width: 1px;
      height: 1px;
      opacity: 0;
    }

    .ruangan-toggle-track {
      position: absolute;
      inset: 0;
      border-radius: 9999px;
      background: #cbd5e1;
      transition: background-color 0.2s ease;
    }

    .ruangan-toggle-track::after {
      position: absolute;
      top: 0.2rem;
      left: 0.2rem;
      width: 1rem;
      height: 1rem;
      border-radius: 9999px;
      background: #fff;
      content: "";
      transition: transform 0.2s ease;
    }

    .ruangan-toggle input:checked+.ruangan-toggle-track {
      background: #00b19c;
    }

    .ruangan-toggle input:checked+.ruangan-toggle-track::after {
      transform: translateX(1.1rem);
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
      openModal: @js($errors->any() && in_array(old('ruangan_form'), ['create', 'edit'], true)),
      modalMode: @js(old('ruangan_form') === 'edit' ? 'edit' : 'create'),
      currentRuangan: {},
      deleteModalOpen: false,
      deleteShaking: false,
      feedbackOpen: @js(session()->has('success') || session()->has('error')),
      feedbackType: @js(session()->has('error') ? 'error' : 'success'),
      feedbackMessage: @js(session('success') ?? session('error')),
      feedbackRuanganName: '',
      feedbackRuanganAction: '',
      pendingDeleteForm: null,
      pendingDeleteNama: '',
      searchQuery: @js($filters['search'] ?? ''),
      resultsUrl: @js(route('admin.ruangan.index')),
      filterRequest: null,
      searchTimer: null,
      previewCount: null,
      filterLoading: false,
      filterError: '',
      formBusy: false,
      csrf: '{{ csrf_token() }}',
  
      init() {
          if (this.modalMode === 'edit' && this.openModal) {
              this.currentRuangan = {
                  ruangan_id: @js(old('ruangan_id')),
                  kode_ruangan: @js(old('kode_ruangan', '')),
                  nama_ruangan: @js(old('nama_ruangan', '')),
                  keterangan: @js(old('keterangan', '')),
              };
          } else {
              this.currentRuangan = {
                  kode_ruangan: '',
                  nama_ruangan: '',
                  keterangan: ''
              };
          }
          if (this.feedbackOpen) window.setTimeout(() => this.feedbackOpen = false, 2000);
      },
  
      createRuangan() {
          this.modalMode = 'create';
          this.currentRuangan = {
              kode_ruangan: '',
              nama_ruangan: '',
              keterangan: ''
          };
          this.openModal = true;
      },
      closeAccountModal() { this.openModal = false; },
  
      editRuangan(ruangan) {
          this.modalMode = 'edit';
          this.currentRuangan = {
              ruangan_id: ruangan.ruangan_id,
              kode_ruangan: ruangan.kode_ruangan,
              nama_ruangan: ruangan.nama_ruangan,
              keterangan: ruangan.keterangan || ''
          };
          this.openModal = true;
      },
  
      async filterRuangan(url = null) {
          window.clearTimeout(this.searchTimer);
          this.searchTimer = null;
          if (this.filterRequest) this.filterRequest.abort();
          const request = new AbortController();
          this.filterRequest = request;
          this.filterLoading = true;
          this.filterError = '';

          const requestUrl = new URL(url || this.resultsUrl, window.location.href);
          if (!url) {
              requestUrl.search = new URLSearchParams({ search: this.searchQuery.trim() }).toString();
          }

          try {
              const response = await fetch(requestUrl, {
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                  signal: request.signal,
              });
              if (!response.ok || response.redirected) throw new Error('Daftar ruangan tidak dapat dimuat.');

              const fragment = await response.text();
              this.$refs.ruanganResults.innerHTML = fragment;
              this.previewCount = null;
              this.updateRuanganCount();
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.filterError = error.message || 'Daftar ruangan tidak dapat dimuat.';
                  this.$refs.ruanganResults.querySelectorAll('[data-searchable]').forEach(card => card.hidden = false);
                  this.previewCount = null;
                  this.updateRuanganCount();
              }
          } finally {
              if (this.filterRequest === request) {
                  this.filterLoading = false;
                  this.filterRequest = null;
              }
          }
      },
      searchRuangan() {
          const query = this.searchQuery.trim().toLocaleLowerCase();
          const cards = Array.from(this.$refs.ruanganResults.querySelectorAll('[data-searchable]'));
          cards.forEach(card => {
              card.hidden = !(card.dataset.searchable || '').includes(query);
          });
          this.previewCount = cards.filter(card => !card.hidden).length;
          this.updateRuanganCount();
          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => {
              this.searchTimer = null;
              this.filterRuangan();
          }, 100);
      },
      updateRuanganCount() {
          const count = this.$refs.ruanganResults.querySelector('[data-ruangan-list-count]');
          if (!count) return;

          count.textContent = this.previewCount === null
              ? `Menampilkan ${count.dataset.pageCount} dari ${count.dataset.totalCount} ruangan`
              : `Menampilkan ${this.previewCount} hasil sementara`;
      },
      handleRuanganPagination(event) {
          const target = event.target instanceof Element ? event.target : null;
          const editButton = target?.closest('[data-ruangan]');
          if (editButton && this.$refs.ruanganResults.contains(editButton)) {
              this.editRuangan(JSON.parse(editButton.dataset.ruangan));
              return;
          }

          const link = target?.closest('a');
          if (!link || !this.$refs.ruanganResults.contains(link)) return;
          event.preventDefault();
          this.filterRuangan(link.href);
      },
      handleRuanganDelete(event) {
          const form = event.target instanceof HTMLFormElement ? event.target : null;
          if (!form || !form.matches('[data-ruangan-delete-form]')) return;
          this.requestDelete(form);
      },
  
      async submitRuangan(event) { await this.sendCrudRequest(event.target); },
      async sendCrudRequest(form) {
          this.formBusy = true;
          this.feedbackRuanganName = '';
          this.feedbackRuanganAction = '';
          try {
              const formData = new FormData(form);
              const isDelete = formData.get('_method') === 'DELETE';
              const action = isDelete
                  ? 'dihapus'
                  : formData.get('ruangan_form') === 'edit' ? 'diperbarui' : 'ditambahkan';
              const ruanganName = isDelete
                  ? this.pendingDeleteNama
                  : String(formData.get('nama_ruangan') || '').trim();
              const response = await fetch(form.action, {
                  method: form.method === 'DELETE' ? 'DELETE' : 'POST',
                  body: formData,
                  headers: { 'Accept': 'text/html', 'X-CSRF-TOKEN': this.csrf }
              });
              const html = await response.text();
              const responseDocument = new DOMParser().parseFromString(html, 'text/html');
              const validationMessage = responseDocument.querySelector('[data-ruangan-validation]')?.dataset.ruanganValidation;
              const successMessage = responseDocument.querySelector('[data-ruangan-success]')?.dataset.ruanganSuccess;
              const errorMessage = responseDocument.querySelector('[data-ruangan-error]')?.dataset.ruanganError;
  
              if (validationMessage) {
                  this.openModal = false;
                  this.feedbackType = 'error';
                  this.feedbackMessage = validationMessage;
                  this.feedbackOpen = true;
                  window.setTimeout(() => this.feedbackOpen = false, 2000);
                  return false;
              }
              if (!response.ok) { throw new Error('Permintaan tidak berhasil.'); }
              if (errorMessage) {
                  this.openModal = false;
                  this.feedbackType = 'error';
                  this.feedbackMessage = errorMessage;
                  this.feedbackOpen = true;
                  window.setTimeout(() => this.feedbackOpen = false, 2000);
                  return false;
              }
              if (!successMessage) { throw new Error('Server tidak mengirim konfirmasi penyimpanan.'); }

              this.openModal = false;
              this.feedbackRuanganName = ruanganName;
              this.feedbackRuanganAction = action;
              this.feedbackType = 'success';
              this.feedbackMessage = successMessage;
              this.feedbackOpen = true;
              window.setTimeout(() => this.feedbackOpen = false, 2000);
              await this.filterRuangan();
              return true;
          } catch (error) {
              this.openModal = false;
              this.feedbackType = 'error';
              this.feedbackMessage = error.message || 'Terjadi kesalahan saat menyimpan.';
              this.feedbackOpen = true;
              window.setTimeout(() => this.feedbackOpen = false, 2000);
              return false;
          } finally { this.formBusy = false; }
      },
  
      requestDelete(form) {
          this.pendingDeleteForm = form;
          this.pendingDeleteNama = form.dataset.nama;
          this.deleteModalOpen = true;
      },
      cancelDelete() {
          this.deleteModalOpen = false;
          this.pendingDeleteForm = null;
          this.pendingDeleteNama = '';
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
          this.feedbackOpen = false;
      },
      async submitDelete() {
          if (!this.pendingDeleteForm) return;
          try {
              if (await this.sendCrudRequest(this.pendingDeleteForm)) this.cancelDelete();
          } catch (error) {
              this.feedbackType = 'error';
              this.feedbackMessage = error.message || 'Terjadi kesalahan saat menghapus ruangan.';
              this.feedbackOpen = true;
              window.setTimeout(() => this.feedbackOpen = false, 2000);
          }
      }
  }" @keydown.escape.window="handleEscape($event)"
    class="ruangan-surface relative -mx-8 -mb-8 min-h-full bg-[#E8F8F4]">

    <div class="p-4 sm:p-8">
      <div class="hidden" data-ruangan-validation="{{ $errors->first() }}"
        data-ruangan-success="{{ session('success') }}" data-ruangan-error="{{ session('error') }}"></div>

      <div class="ruangan-toolbar mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        @if(auth()->user()?->canManageInventory())
        <div class="flex flex-wrap gap-2">
          <button type="button" @click="createRuangan()"
            class="ruangan-primary-button inline-flex items-center gap-2 rounded-lg border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009a87] focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
            <iconify-icon icon="mdi:plus" width="18"></iconify-icon> Tambah Ruangan
          </button>
        </div>
        @endif
        <label class="ruangan-search relative block w-full sm:ml-auto sm:w-72">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center"><iconify-icon icon="mdi:magnify"
              width="17" class="block text-slate-400"></iconify-icon></span>
          <input type="search" name="search" x-model="searchQuery" @input="searchRuangan()"
            maxlength="100" placeholder="Cari Ruangan..."
            class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm leading-5 text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
        </label>
      </div>

      <div x-cloak x-show="filterError" class="mb-3 flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800" role="alert">
        <span x-text="filterError"></span>
        <button type="button" @click="filterRuangan()" class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">
          Coba lagi
        </button>
      </div>

      <div x-ref="ruanganResults" @click="handleRuanganPagination($event)"
        @submit.prevent="handleRuanganDelete($event)" :aria-busy="filterLoading.toString()">
        @fragment('ruangan-results')
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($ruangans as $ruangan)
          <div data-ruangan-card data-searchable="{{ strtolower($ruangan->kode_ruangan . ' ' . $ruangan->nama_ruangan) }}" class="ruangan-card p-5">
            <div class="flex items-start justify-between">
              <div class="flex items-start gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-[#E0F7F4]">
                  <iconify-icon icon="mdi:office-building" class="text-[#00b19c]" width="24"></iconify-icon>
                </div>
                <div>
                  <h3 class="text-base font-semibold text-slate-800">{{ $ruangan->nama_ruangan }}</h3>
                  <p class="text-xs text-slate-500 mt-0.5">{{ $ruangan->kode_ruangan }}</p>
                </div>
              </div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-xs text-slate-600">
              <iconify-icon icon="mdi:shelf" width="16" class="text-slate-400"></iconify-icon>
              <span>{{ $ruangan->lemaris_count }} lemari</span>
            </div>

            @if ($ruangan->keterangan)
              <p class="mt-3 text-xs text-slate-500 line-clamp-2">{{ $ruangan->keterangan }}</p>
            @endif

            @if(auth()->user()?->canManageInventory())
            <div class="mt-4 flex justify-end gap-2 pt-3 border-t border-slate-100">
              <button type="button"
                data-ruangan="{{ json_encode([
                    'ruangan_id' => $ruangan->ruangan_id,
                    'kode_ruangan' => $ruangan->kode_ruangan,
                    'nama_ruangan' => $ruangan->nama_ruangan,
                    'keterangan' => $ruangan->keterangan,
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <iconify-icon icon="mdi:pencil" width="15"></iconify-icon> Edit
              </button>
              <form action="{{ route('admin.ruangan.destroy', $ruangan->ruangan_id) }}" method="POST"
                data-ruangan-delete-form data-nama="{{ $ruangan->nama_ruangan }}">
                @csrf @method('DELETE')
                <button type="submit"
                  class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                  <iconify-icon icon="mdi:delete-outline" width="16"></iconify-icon> Delete
                </button>
              </form>
            </div>
            @endif
          </div>
        @empty
          <div class="col-span-full flex flex-col items-center justify-center py-12 text-center">
            @if (filled($filters['search'] ?? null))
              {{-- ASVS V1.2.1: Blade-escape the search keyword in its HTML text context. --}}
              <iconify-icon icon="mdi:office-building-outline" class="mb-2 text-slate-300" width="42"
                aria-hidden="true"></iconify-icon>
              <p class="text-sm font-semibold text-slate-700">
                Tidak ada ruangan yang cocok dengan kata kunci
                <strong class="font-bold text-slate-900">"{{ $filters['search'] }}"</strong>.
              </p>
            @else
              <iconify-icon icon="mdi:office-building-outline" class="mb-2 text-slate-300" width="48"></iconify-icon>
              <p class="text-sm font-medium text-slate-600">Belum ada ruangan yang terdaftar.</p>
            @endif
          </div>
        @endforelse
      </div>

      <div data-ruangan-list-footer
        class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p data-ruangan-list-count data-page-count="{{ $ruangans->count() }}" data-total-count="{{ $ruangans->total() }}"
          class="text-xs text-slate-500">
          Menampilkan {{ $ruangans->count() }} dari {{ $ruangans->total() }} ruangan
        </p>
        @if ($ruangans->hasPages())
          @php
            $paginationStart = max(1, min($ruangans->currentPage() - 1, $ruangans->lastPage() - 2));
            $paginationEnd = min($ruangans->lastPage(), $paginationStart + 2);
          @endphp
          <nav aria-label="Navigasi halaman ruangan" class="flex items-center justify-end gap-2">
            @if (!$ruangans->onFirstPage())
              <a href="{{ $ruangans->previousPageUrl() }}" aria-label="Halaman sebelumnya"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                <iconify-icon icon="mdi:chevron-left" width="22"></iconify-icon>
              </a>
            @endif
            @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
              @if ($page === $ruangans->currentPage())
                <span aria-current="page"
                  class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-[#00CFA8] px-3 text-sm font-semibold text-slate-950 shadow-[0_4px_10px_rgba(0,177,156,0.2)]">
                  {{ $page }}
                </span>
              @else
                <a href="{{ $ruangans->url($page) }}" aria-label="Halaman {{ $page }}"
                  class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white px-3 text-sm font-semibold text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                  {{ $page }}
                </a>
              @endif
            @endfor
            @if ($ruangans->hasMorePages())
              <a href="{{ $ruangans->nextPageUrl() }}" aria-label="Halaman berikutnya"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                <iconify-icon icon="mdi:chevron-right" width="22"></iconify-icon>
              </a>
            @endif
          </nav>
        @endif
      </div>
        @endfragment
      </div>
    </div>

    @if(auth()->user()?->canManageInventory())
    <template x-teleport="body">
    {{-- MODAL: Tambah / Edit Ruangan --}}
    <div x-show="openModal" x-transition.opacity style="display: none; z-index: 80;"
      class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto p-4 sm:p-6" role="dialog"
      aria-modal="true">
      <div class="ruangan-modal-backdrop absolute inset-0" @click="closeAccountModal()"></div>
      <div x-show="openModal" x-transition
        class="ruangan-modal-panel relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">
        <form
          :action="modalMode === 'create' ? '{{ route('admin.ruangan.store') }}' :
              '{{ route('admin.ruangan.update', '__ID__') }}'
              .replace('__ID__', currentRuangan.ruangan_id)"
          method="POST" @submit.prevent="submitRuangan($event)">
          @csrf
          <input type="hidden" name="ruangan_form" :value="modalMode">
          <template x-if="modalMode === 'edit'">
            <div><input type="hidden" name="_method" value="PUT"><input type="hidden" name="ruangan_id"
                :value="currentRuangan.ruangan_id"></div>
          </template>

          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                <iconify-icon
                  :icon="modalMode === 'create' ? 'mdi:office-building-plus-outline' : 'mdi:office-building-edit-outline'"
                  width="22" aria-hidden="true"></iconify-icon>
              </span>
              <h2 class="text-base font-semibold text-slate-800"
                x-text="modalMode === 'create' ? 'Tambah Ruangan' : 'Edit Ruangan'"></h2>
            </div>
            <button type="button" @click="closeAccountModal()"
              class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"><iconify-icon
                icon="mdi:close" width="20"></iconify-icon></button>
          </div>

          <div class="px-5 py-4 space-y-4">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Kode Ruangan <span
                  class="text-rose-500">*</span></label>
              <input type="text" name="kode_ruangan" x-model="currentRuangan.kode_ruangan" required
                maxlength="50" placeholder="Contoh: RPL-D2"
                class="ruangan-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Nama Ruangan <span
                  class="text-rose-500">*</span></label>
              <input type="text" name="nama_ruangan" x-model="currentRuangan.nama_ruangan" required
                maxlength="100" placeholder="Contoh: Lab RPL D"
                class="ruangan-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Keterangan</label>
              <textarea name="keterangan" x-model="currentRuangan.keterangan" rows="3" maxlength="255"
                placeholder="Opsional"
                class="ruangan-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"></textarea>
            </div>
          </div>

          <div class="ruangan-modal-footer flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" @click="closeAccountModal()"
              class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal              </button>
              <button type="submit" :disabled="formBusy" :aria-busy="formBusy.toString()"
                class="ruangan-primary-button inline-flex items-center justify-center rounded-md border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
              <iconify-icon x-show="formBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
              <iconify-icon x-show="!formBusy"
                :icon="modalMode === 'create' ? 'mdi:content-save-plus-outline' : 'mdi:content-save-edit-outline'"
                width="18" aria-hidden="true"></iconify-icon>
              <span x-show="!formBusy" class="ml-2" x-text="modalMode === 'create' ? 'Simpan Ruangan' : 'Simpan Perubahan'"></span>
              <span x-show="formBusy" class="sr-only">Sedang menyimpan...</span>
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
      <div class="ruangan-modal-backdrop absolute inset-0" @click="if (!formBusy) cancelDelete()"></div>
      <div x-show="deleteModalOpen" x-transition :class="deleteShaking ? 'animate-[shake_0.4s_ease-in-out]' : ''"
        class="ruangan-modal-panel relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">

        <div class="p-6 text-center">
          <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100">
            <iconify-icon icon="mdi:alert-circle" class="text-rose-600" width="28"></iconify-icon>
          </div>
          <h3 class="text-lg font-semibold text-slate-900">Hapus Ruangan?</h3>
          <p class="mt-2 text-sm text-slate-600">
            Anda akan menghapus ruangan <span class="font-bold text-slate-800" x-text="pendingDeleteNama"></span>.
            Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div class="flex justify-center gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
          <button type="button" @click="cancelDelete()" :disabled="formBusy"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal</button>
          <button type="button" @click="submitDelete()" :disabled="formBusy" :aria-busy="formBusy.toString()"
            class="inline-flex items-center justify-center rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70">
            <iconify-icon x-show="formBusy" icon="mdi:loading" class="animate-spin" width="18"
              aria-hidden="true"></iconify-icon>
            <span x-show="!formBusy">Ya, Hapus</span>
            <span x-show="formBusy" class="sr-only">Sedang menghapus...</span>
          </button>
        </div>
      </div>
    </div>
    </template>
    @endif

    {{-- TOAST: Feedback --}}
    <div x-cloak x-show="feedbackOpen" x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="translate-x-4 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
      x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
      x-transition:leave-end="translate-x-4 opacity-0"
      class="pointer-events-none fixed right-4 top-24 z-[120] w-[calc(100%-2rem)] max-w-md sm:right-6"
      :role="feedbackType === 'error' ? 'alert' : 'status'" aria-live="polite" aria-atomic="true">
      <div class="pointer-events-auto flex items-start gap-3 rounded-xl border border-l-4 bg-white p-4 shadow-[0_16px_48px_rgba(2,25,41,0.18)]"
        :class="feedbackType === 'error' ? 'border-rose-200 border-l-rose-500' : 'border-emerald-200 border-l-emerald-500'">
        <iconify-icon :icon="feedbackType === 'error' ? 'mdi:close-circle' : 'mdi:check-circle'"
          class="mt-0.5 shrink-0" :class="feedbackType === 'error' ? 'text-rose-600' : 'text-emerald-600'"
          width="22" aria-hidden="true"></iconify-icon>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-slate-900" x-text="feedbackType === 'error' ? 'Gagal!' : 'Berhasil!'"></p>
          <p x-show="!feedbackRuanganName" class="mt-1 break-words text-sm text-slate-600" x-text="feedbackMessage"></p>
          <p x-show="feedbackRuanganName" class="mt-1 break-words text-sm text-slate-600">
            <template x-if="feedbackRuanganAction === 'dihapus'">
              <span><strong class="font-bold text-slate-800" x-text="feedbackRuanganName"></strong> berhasil dihapus.</span>
            </template>
            <template x-if="feedbackRuanganAction !== 'dihapus'">
              <span>Ruangan <strong class="font-bold text-slate-800" x-text="feedbackRuanganName"></strong>
                berhasil <span x-text="feedbackRuanganAction"></span>.</span>
            </template>
          </p>
        </div>
        <button type="button" @click="feedbackOpen = false; feedbackRuanganName = ''; feedbackRuanganAction = ''"
          class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400"
          aria-label="Tutup notifikasi">
          <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
        </button>
      </div>
    </div>

  </div>
</x-layout.admin>
