<x-layout.admin title="Kategori Inventaris - InvenTrack" header-title="Kategori Inventaris"
  header-description="Master kategori barang.">
  <style>
    .kategori-modal-backdrop {
      background: linear-gradient(135deg, rgba(7, 59, 86, .84), rgba(2, 6, 23, .76), rgba(6, 78, 72, .78));
      backdrop-filter: blur(9px);
    }

    html.kategori-modal-open,
    html.kategori-modal-open main {
      overflow: hidden;
    }
  </style>

  <div x-data="{
      searchQuery: @js($filters['search'] ?? ''),
      createUrl: @js(route('admin.kategori.store')),
      updateUrlTemplate: @js(route('admin.kategori.update', ['kategori' => '__KATEGORI__'])),
      resultsUrl: @js(route('admin.kategori')),
      formAction: '',
      filterRequest: null,
      searchTimer: null,
      filterLoading: false,
      filterError: '',
      categoryBusy: false,
      modalOpen: @js($errors->any() && in_array(old('kategori_form'), ['create', 'edit'], true)),
      modalMode: @js(old('kategori_form') === 'edit' ? 'edit' : 'create'),
      currentCategory: {
          kategori_id: @js(old('kategori_id', '')),
          nama_kategori: @js(old('nama_kategori', '')),
          keterangan: @js(old('keterangan', ''))
      },
      deleteModalOpen: false,
      deleteShaking: false,
      pendingDeleteForm: null,
      pendingDeleteName: '',
      csrf: '{{ csrf_token() }}',
      feedbackCategoryAction: '',
      feedbackType: @js(session()->has('error') ? 'error' : 'success'),
      feedbackMessage: @js(session('success') ?? session('error')),
      feedbackCategoryName: '',
      init() {
          this.formAction = this.modalMode === 'edit' && this.currentCategory.kategori_id
              ? this.categoryUpdateUrl(this.currentCategory.kategori_id)
              : this.createUrl;
          if (this.feedbackMessage) this.notifyFeedback();
      },
      notifyFeedback() {
          const message = this.feedbackCategoryName
              ? [
                  { text: 'Kategori ' },
                  { text: this.feedbackCategoryName, bold: true },
                  { text: ` berhasil ${this.feedbackCategoryAction}.` },
              ]
              : this.feedbackMessage;
          window.showToast(message, this.feedbackType);
      },
      categoryUpdateUrl(id) {
          return this.updateUrlTemplate.replace('__KATEGORI__', encodeURIComponent(id));
      },
      matchesCategory(searchableText) {
          const searchable = searchableText.toLocaleLowerCase();
          const searchTerms = this.searchQuery.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
          return searchTerms.every(term => searchable.includes(term));
      },
      async filterCategories(url = null) {
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
              if (!response.ok || response.redirected) throw new Error('Daftar kategori tidak dapat dimuat.');

              const fragment = await response.text();
              this.$refs.kategoriResults.innerHTML = fragment;
              this.updateCategoryCount();
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.filterError = error.message || 'Daftar kategori tidak dapat dimuat.';
                  this.$refs.kategoriResults.querySelectorAll('[data-kategori-row]').forEach(row => row.hidden = false);
                  this.updateCategoryCount();
              }
          } finally {
              if (this.filterRequest === request) {
                  this.filterLoading = false;
                  this.filterRequest = null;
              }
          }
      },
      searchCategories() {
          const rows = Array.from(this.$refs.kategoriResults.querySelectorAll('[data-kategori-row]'));
          rows.forEach(row => row.hidden = !this.matchesCategory(row.dataset.searchable || ''));
          this.updateCategoryCount(rows.filter(row => !row.hidden).length);
          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => {
              this.searchTimer = null;
              this.filterCategories();
          }, 100);
      },
      updateCategoryCount(visibleCount = null) {
          const count = this.$refs.kategoriResults.querySelector('[data-kategori-list-count]');
          if (!count) return;

          count.textContent = `Menampilkan ${count.dataset.pageCount} dari ${count.dataset.totalCount} kategori`;
      },
      handleCategoryPagination(event) {
          const target = event.target instanceof Element ? event.target : null;
          const editButton = target?.closest('button[data-kategori-id]');
          if (editButton && this.$refs.kategoriResults.contains(editButton)) {
              this.editCategory({
                  kategori_id: editButton.dataset.kategoriId,
                  nama_kategori: editButton.dataset.namaKategori,
                  keterangan: editButton.dataset.keterangan
              });
              return;
          }

          const link = target?.closest('a');
          if (!link || !this.$refs.kategoriResults.contains(link)) return;
          event.preventDefault();
          this.filterCategories(link.href);
      },
      handleCategoryDelete(event) {
          const form = event.target instanceof HTMLFormElement ? event.target : null;
          if (!form || !form.matches('form[data-category-name]')) return;
          this.requestDelete(form);
      },
      createCategory() {
          this.modalMode = 'create';
          this.currentCategory = { kategori_id: '', nama_kategori: '', keterangan: '' };
          this.formAction = this.createUrl;
          this.modalOpen = true;
      },
      editCategory(category) {
          this.modalMode = 'edit';
          this.currentCategory = {
              kategori_id: category.kategori_id,
              nama_kategori: category.nama_kategori,
              keterangan: category.keterangan || ''
          };
          this.formAction = this.categoryUpdateUrl(category.kategori_id);
          this.modalOpen = true;
      },
      async submitCategory(event) {
          const form = event.target;
          const formData = new FormData(form);
          const action = this.modalMode === 'edit' ? 'diperbarui' : 'ditambahkan';
          const categoryName = String(formData.get('nama_kategori') || '').trim();
          this.categoryBusy = true;
          this.feedbackCategoryName = '';
          this.feedbackCategoryAction = '';

          try {
              const response = await fetch(form.action, {
                  method: 'POST',
                  body: formData,
                  headers: { 'Accept': 'text/html', 'X-CSRF-TOKEN': this.csrf }
              });
              const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
              const result = responseDocument.querySelector('[data-kategori-success], [data-kategori-error], [data-kategori-validation]');
              const successMessage = result?.dataset.kategoriSuccess;
              const errorMessage = result?.dataset.kategoriError || result?.dataset.kategoriValidation;

              if (!response.ok || !successMessage) {
                  this.modalOpen = false;
                  this.feedbackType = 'error';
                  this.feedbackMessage = errorMessage || 'Permintaan kategori tidak berhasil. Silakan coba lagi.';
                  this.notifyFeedback();
                  return;
              }

              this.modalOpen = false;
              this.feedbackCategoryName = categoryName;
              this.feedbackCategoryAction = action;
              this.feedbackType = 'success';
              this.feedbackMessage = successMessage;
              this.notifyFeedback();
              await this.filterCategories();
          } catch (error) {
              this.modalOpen = false;
              this.feedbackType = 'error';
              this.feedbackMessage = error.message || 'Terjadi kesalahan saat menyimpan kategori.';
              this.notifyFeedback();
          } finally {
              this.categoryBusy = false;
          }
      },
      requestDelete(form) {
          this.pendingDeleteForm = form;
          this.pendingDeleteName = form.dataset.categoryName || '';
          this.deleteModalOpen = true;
      },
      cancelDelete() {
          this.deleteModalOpen = false;
          this.pendingDeleteForm = null;
          this.pendingDeleteName = '';
          this.deleteShaking = false;
      },
      shakeDelete() {
          this.deleteShaking = false;
          window.setTimeout(() => this.deleteShaking = true, 10);
          window.setTimeout(() => this.deleteShaking = false, 460);
      },
      async submitDelete() {
          if (!this.pendingDeleteForm) return;

          const form = this.pendingDeleteForm;
          this.categoryBusy = true;
          this.feedbackCategoryName = '';
          try {
              const response = await fetch(form.action, {
                  method: form.method === 'DELETE' ? 'DELETE' : 'POST',
                  body: new FormData(form),
                  headers: {
                          'Accept': 'text/html',
                          'X-CSRF-TOKEN': this.csrf
                  }
              });
              const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
              const result = responseDocument.querySelector('[data-kategori-success], [data-kategori-error]');
              const successMessage = result?.dataset.kategoriSuccess;
              const errorMessage = result?.dataset.kategoriError;
              const responseUrl = new URL(response.url);
              const hasKategoriResponse = responseDocument.querySelector('[data-kategori-list-count]');

              if (errorMessage) {
                  this.feedbackMessage = errorMessage;
                  this.feedbackType = 'error';
                  this.notifyFeedback();
                  this.shakeDelete();
                  return;
              }
              if (!response.ok || !successMessage) {
                  this.feedbackMessage = hasKategoriResponse && response.redirected && responseUrl.pathname.endsWith('/kategori')
                      ? 'Permintaan kategori tidak berhasil. Silakan coba lagi.'
                      : 'Terjadi kesalahan saat menghapus kategori. Silakan coba lagi.';
                  this.feedbackType = 'error';
                  this.notifyFeedback();
                  this.shakeDelete();
                  return;
              }
              this.feedbackCategoryAction = 'dihapus';
              this.feedbackCategoryName = this.pendingDeleteName;
              this.feedbackMessage = successMessage;
              this.feedbackType = 'success';
              this.notifyFeedback();

              this.cancelDelete();
              await this.filterCategories();
          } catch (error) {
              this.feedbackMessage = error.message || 'Terjadi kesalahan saat menghapus kategori.';
              this.feedbackType = 'error';
              this.notifyFeedback();
          } finally {
              this.categoryBusy = false;
          }
      },
      closeModal() {
          this.modalOpen = false;
      },
      handleEscape(event) {
          if (this.deleteModalOpen) {
              event.preventDefault();
              this.shakeDelete();
              return;
          }
          this.modalOpen = false;
      }
  }" @keydown.escape.window="handleEscape($event)"
    x-effect="document.documentElement.classList.toggle('kategori-modal-open', modalOpen || deleteModalOpen)"
    class="-mx-8 -mt-8 -mb-8 min-h-[calc(100vh-10rem)] bg-[#E8F8F4]">
    <div class="p-4 sm:p-8">
      @if ($errors->any() || session()->has('success') || session()->has('error'))
        <div class="hidden" data-kategori-success="{{ session('success') }}"
          data-kategori-error="{{ session('error') }}" data-kategori-validation="{{ $errors->first() }}"></div>
      @endif

      <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if(auth()->user()?->canManageInventory())
        <button type="button" @click="createCategory()"
          class="inline-flex min-h-9 items-center gap-2 self-start rounded-md border border-[#009F91] bg-[#00B19C] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2">
          <iconify-icon icon="mdi:plus" width="16" aria-hidden="true"></iconify-icon>
          Tambah Kategori
        </button>
        @endif
        <label class="relative block w-full sm:ml-auto sm:w-72">
          <span class="sr-only">Cari kategori</span>
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            <iconify-icon icon="mdi:magnify" width="17" aria-hidden="true"></iconify-icon>
          </span>
          <input type="search" name="search" x-model="searchQuery" @input="searchCategories()"
            placeholder="Cari kategori..."
            class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm leading-5 text-slate-800 placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
        </label>
      </div>

      <div x-cloak x-show="filterError" class="mb-3 flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800" role="alert">
        <span x-text="filterError"></span>
        <button type="button" @click="filterCategories()" class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">
          Coba lagi
        </button>
      </div>
      <div x-ref="kategoriResults" @click="handleCategoryPagination($event)"
        @submit.prevent="handleCategoryDelete($event)" :aria-busy="filterLoading.toString()">
        @fragment('kategori-results')
      <section class="overflow-hidden rounded-lg border border-[#D3E3DF] bg-white shadow-sm">
        <div class="theme-scrollbar overflow-x-auto">
          <table class="min-w-full w-full divide-y divide-[#DCE9E6]">
            <thead class="bg-[#F1F3F4]">
              <tr>
                <th scope="col" class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold text-slate-700">Nama Kategori</th>
                <th scope="col" class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold text-slate-700">Keterangan</th>
                <th scope="col" class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold text-slate-700">Jumlah Barang</th>
                @if(auth()->user()?->canManageInventory())
                  <th scope="col" class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold text-slate-700">Aksi</th>
                @endif
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E7EFED] bg-white">
              @forelse ($kategoris as $kategori)
                {{-- ASVS V1.2.1: Blade-escape category values in HTML text and attribute contexts. --}}
                <tr data-kategori-row
                  data-searchable="{{ mb_strtolower(trim($kategori->nama_kategori . ' ' . ($kategori->keterangan ?? ''))) }}"
                  x-show="matchesCategory($el.dataset.searchable)"
                  class="transition-colors hover:bg-[#F7FBFA]">
                  <td class="whitespace-nowrap px-3 py-2.5 text-sm text-slate-800">{{ $kategori->nama_kategori }}</td>
                  <td class="max-w-xl whitespace-nowrap px-3 py-2.5 text-sm text-slate-600">{{ $kategori->keterangan ?: '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-2.5 text-sm text-slate-700">
                    {{ $kategori->barangs_count }} barang
                  </td>
                  @if(auth()->user()?->canManageInventory())
                  <td class="whitespace-nowrap px-3 py-2.5 text-right">
                    <div class="flex justify-end gap-2">
                      <button type="button"
                        data-kategori-id="{{ $kategori->kategori_id }}"
                        data-nama-kategori="{{ $kategori->nama_kategori }}"
                        data-keterangan="{{ $kategori->keterangan }}"
                        class="inline-flex min-h-8 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <iconify-icon icon="mdi:pencil" width="15" aria-hidden="true"></iconify-icon> Edit
                      </button>
                      <form action="{{ route('admin.kategori.destroy', $kategori->kategori_id) }}" method="POST"
                        data-category-name="{{ $kategori->nama_kategori }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                          class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                          <iconify-icon icon="mdi:delete-outline" width="16" aria-hidden="true"></iconify-icon> Hapus
                        </button>
                      </form>
                    </div>
                  </td>
                  @endif
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="px-6 py-12 text-center">
                    @if (filled($filters['search'] ?? null))
                      {{-- ASVS V1.2.1: Blade-escape the search keyword in its HTML text context. --}}
                      <iconify-icon icon="mdi:tag-multiple-outline" class="mb-2 text-slate-300" width="42"
                        aria-hidden="true"></iconify-icon>
                      <p class="text-sm font-semibold text-slate-700">
                        Tidak ada kategori yang cocok dengan kata kunci
                        <strong class="font-bold text-slate-900">"{{ $filters['search'] }}"</strong>.
                      </p>
                    @else
                      <iconify-icon icon="mdi:tag-multiple-outline" class="mb-3 text-slate-300" width="40"
                        aria-hidden="true"></iconify-icon>
                      <p class="text-sm font-medium text-slate-700">Belum ada kategori</p>
                      <p class="mt-1 text-xs text-slate-500">Tambahkan kategori untuk mulai mengelompokkan barang.</p>
                    @endif
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-[#E2ECE9] bg-[#F7FBFA] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p data-kategori-list-count data-page-count="{{ $kategoris->count() }}" data-total-count="{{ $kategoris->total() }}"
            class="text-xs text-slate-500">
            Menampilkan {{ $kategoris->count() }} dari {{ $kategoris->total() }} kategori
          </p>
          @if ($kategoris->hasPages())
            @php
              $paginationStart = max(1, min($kategoris->currentPage() - 1, $kategoris->lastPage() - 2));
              $paginationEnd = min($kategoris->lastPage(), $paginationStart + 2);
            @endphp
            <nav aria-label="Navigasi halaman kategori" class="flex items-center justify-end gap-2">
              @if (!$kategoris->onFirstPage())
                <a href="{{ $kategoris->previousPageUrl() }}" aria-label="Halaman sebelumnya"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                  <iconify-icon icon="mdi:chevron-left" width="22"></iconify-icon>
                </a>
              @endif
              @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
                @if ($page === $kategoris->currentPage())
                  <span aria-current="page"
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-[#00CFA8] px-3 text-sm font-semibold text-slate-950 shadow-[0_4px_10px_rgba(0,177,156,0.2)]">
                    {{ $page }}
                  </span>
                @else
                  <a href="{{ $kategoris->url($page) }}" aria-label="Halaman {{ $page }}"
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white px-3 text-sm font-semibold text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                    {{ $page }}
                  </a>
                @endif
              @endfor
              @if ($kategoris->hasMorePages())
                <a href="{{ $kategoris->nextPageUrl() }}" aria-label="Halaman berikutnya"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
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
    <div x-cloak x-show="modalOpen" x-transition.opacity
      class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
      role="presentation">
      <div class="kategori-modal-backdrop absolute inset-0" @click="closeModal()"></div>
      <section x-show="modalOpen" x-transition
        class="relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
        role="dialog" aria-modal="true" aria-labelledby="kategori-modal-title">
        <form :action="formAction" method="POST" class="relative bg-white" @submit.prevent="submitCategory($event)">
          @csrf
          <input type="hidden" name="_method" value="PUT" :disabled="modalMode !== 'edit'">
          <input type="hidden" name="kategori_form" :value="modalMode">
          <input type="hidden" name="kategori_id" x-model="currentCategory.kategori_id">
          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                <iconify-icon
                  :icon="modalMode === 'create' ? 'mdi:tag-plus-outline' : 'mdi:tag-edit-outline'"
                  width="22" aria-hidden="true"></iconify-icon>
              </span>
              <h2 id="kategori-modal-title" class="text-base font-semibold text-slate-800"
              x-text="modalMode === 'edit' ? 'Ubah kategori' : 'Tambah kategori'"></h2>
            </div>
            <button type="button" @click="closeModal()"
              class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
              aria-label="Tutup formulir">
              <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
            </button>
          </div>
          <div class="space-y-4 px-5 py-4">
            <div>
              <label for="nama_kategori" class="mb-1 block text-xs font-medium text-slate-700">Nama kategori</label>
            <input id="nama_kategori" name="nama_kategori" type="text" maxlength="255" required
              x-model="currentCategory.nama_kategori" autocomplete="off"
              class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
              placeholder="Contoh: Peralatan laboratorium">
            @error('nama_kategori')
              <p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>
            @enderror
            </div>
            <div>
              <label for="keterangan" class="mb-1 block text-xs font-medium text-slate-700">Keterangan <span
                class="font-normal text-slate-400">(opsional)</span></label>
            <textarea id="keterangan" name="keterangan" maxlength="255" rows="3"
              x-model="currentCategory.keterangan"
              class="block w-full resize-y rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
              placeholder="Catatan singkat tentang kategori"></textarea>
            @error('keterangan')
              <p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>
            @enderror
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" @click="closeModal()"
              class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">
              Batal
            </button>
            <button type="submit" :disabled="categoryBusy" :aria-busy="categoryBusy.toString()"
              class="inline-flex items-center justify-center rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2">
              <iconify-icon x-show="categoryBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
              <iconify-icon x-show="!categoryBusy"
                :icon="modalMode === 'edit' ? 'mdi:content-save-edit-outline' : 'mdi:content-save-plus-outline'"
                width="18" aria-hidden="true"></iconify-icon>
              <span x-show="!categoryBusy" class="ml-2" x-text="modalMode === 'edit' ? 'Simpan perubahan' : 'Simpan kategori'"></span>
              <span x-show="categoryBusy" class="sr-only">Sedang menyimpan...</span>
            </button>
          </div>
        </form>
      </section>
    </div>
    </template>

    <template x-teleport="body">
    <div x-cloak x-show="deleteModalOpen" x-transition.opacity
      class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
      role="alertdialog" aria-modal="true" aria-labelledby="kategori-delete-title"
      aria-describedby="kategori-delete-description">
      <div class="kategori-modal-backdrop absolute inset-0" @click="cancelDelete()"></div>
      <section x-show="deleteModalOpen" x-transition :class="deleteShaking ? 'animate-[shake_0.4s_ease-in-out]' : ''"
        class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">
        <div class="p-6 text-center">
          <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100">
            <iconify-icon icon="mdi:alert-circle" class="text-rose-600" width="28" aria-hidden="true"></iconify-icon>
          </div>
          <h2 id="kategori-delete-title" class="text-lg font-semibold text-slate-900">Hapus Kategori?</h2>
          <p id="kategori-delete-description" class="mt-2 text-sm text-slate-600">
          Anda akan menghapus kategori <strong class="font-bold text-slate-800" x-text="pendingDeleteName"></strong>.
          Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div class="flex justify-center gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
          <button type="button" @click="cancelDelete()"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">
            Batal
          </button>
          <button type="button" @click="submitDelete()" :disabled="categoryBusy" :aria-busy="categoryBusy.toString()"
            class="inline-flex min-w-28 items-center justify-center gap-2 rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
            <iconify-icon x-show="categoryBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
            <iconify-icon x-show="!categoryBusy" icon="mdi:delete-outline" class="text-lg" aria-hidden="true"></iconify-icon>
            <span x-show="!categoryBusy">Ya, Hapus</span>
            <span x-show="categoryBusy" class="sr-only">Sedang menghapus...</span>
          </button>
        </div>
      </section>
    </div>
    </template>
    @endif
  </div>
</x-layout.admin>
