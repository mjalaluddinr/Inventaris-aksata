<x-layout.admin title="Wadah - InvenTrack" header-title="Wadah"
  header-description="Kelola wadah penyimpanan untuk lokasi stok barang.">
  <div x-data="{
      searchQuery: @js($filters['search'] ?? ''),
      resultsUrl: @js(route('admin.toolbox.index')),
      searchTimer: null,
      filterRequest: null,
      filterLoading: false,
      filterError: '',
      createUrl: @js(route('admin.toolbox.store')),
      updateUrlTemplate: @js(route('admin.toolbox.update', ['toolbox' => '__TOOLBOX__'])),
      modalOpen: @js($errors->any() && in_array(old('toolbox_form'), ['create', 'edit'], true)),
      modalMode: @js(old('toolbox_form') === 'edit' ? 'edit' : 'create'),
      formBusy: false,
      currentToolbox: {
          toolbox_id: @js(old('toolbox_id', '')),
          nama_toolbox: @js(old('nama_toolbox', '')),
          keterangan: @js(old('keterangan', ''))
      },
      deleteModalOpen: false,
      deleteBusy: false,
      deleteAction: '',
      pendingDeleteName: '',
      feedbackMessage: @js(session('success') ?? session('error')),
      feedbackType: @js(session()->has('error') ? 'error' : 'success'),
      init() {
          if (this.modalMode === 'edit' && this.currentToolbox.toolbox_id) {
              this.formAction = this.updateUrl(this.currentToolbox.toolbox_id);
          } else {
              this.formAction = this.createUrl;
          }
          if (this.feedbackMessage) window.showToast(this.feedbackMessage, this.feedbackType);
      },
      formAction: '',
      updateUrl(id) {
          return this.updateUrlTemplate.replace('__TOOLBOX__', encodeURIComponent(id));
      },
      async filterToolboxes(url = null) {
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
                  search: this.searchQuery.trim(),
              }).toString();
          }

          try {
              const response = await fetch(requestUrl, {
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                  signal: request.signal,
              });
              if (!response.ok || response.redirected) throw new Error('Daftar wadah tidak dapat dimuat.');

              this.$refs.toolboxResults.innerHTML = await response.text();
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.filterError = error.message || 'Daftar wadah tidak dapat dimuat.';
                  this.$refs.toolboxResults.querySelectorAll('[data-toolbox-row]').forEach((row) => {
                      row.hidden = false;
                  });
              }
          } finally {
              if (this.filterRequest === request) {
                  this.filterLoading = false;
                  this.filterRequest = null;
              }
          }
      },
      searchToolboxes() {
          const searchTerms = this.searchQuery.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
          this.$refs.toolboxResults.querySelectorAll('[data-toolbox-row]').forEach((row) => {
              const searchable = (row.dataset.searchable || '').toLocaleLowerCase();
              row.hidden = !searchTerms.every((term) => searchable.includes(term));
          });
          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => this.filterToolboxes(), 100);
      },
      handleToolboxResultsClick(event) {
          const target = event.target instanceof Element ? event.target : null;
          const editButton = target?.closest('[data-toolbox-edit]');
          if (editButton && this.$refs.toolboxResults.contains(editButton)) {
              this.editToolbox(JSON.parse(editButton.dataset.toolboxEdit));
              return;
          }

          const deleteButton = target?.closest('[data-toolbox-delete]');
          if (deleteButton && this.$refs.toolboxResults.contains(deleteButton)) {
              this.confirmDelete(JSON.parse(deleteButton.dataset.toolboxDelete));
              return;
          }

          const link = target?.closest('a[data-toolbox-pagination]');
          if (!link || !this.$refs.toolboxResults.contains(link)) return;
          event.preventDefault();
          this.filterToolboxes(link.href);
      },
      createToolbox() {
          this.modalMode = 'create';
          this.currentToolbox = { toolbox_id: '', nama_toolbox: '', keterangan: '' };
          this.formAction = this.createUrl;
          this.modalOpen = true;
      },
      editToolbox(toolbox) {
          this.modalMode = 'edit';
          this.currentToolbox = { ...toolbox, keterangan: toolbox.keterangan || '' };
          this.formAction = this.updateUrl(toolbox.toolbox_id);
          this.modalOpen = true;
      },
      confirmDelete(toolbox) {
          this.deleteAction = this.updateUrl(toolbox.toolbox_id);
          this.pendingDeleteName = toolbox.nama_toolbox;
          this.deleteModalOpen = true;
      },
      cancelDelete() {
          if (this.deleteBusy) return;
          this.deleteModalOpen = false;
          this.deleteAction = '';
          this.pendingDeleteName = '';
      }
  }" class="relative -mx-8 -mt-8 -mb-8 min-h-[calc(100vh-10rem)] bg-[#E8F8F4] p-4 sm:p-8">
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      @if (auth()->user()?->canManageInventory())
        <button type="button" @click="createToolbox()"
          class="inline-flex min-h-10 items-center justify-center gap-2 self-start rounded-lg border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] focus:outline-none focus:ring-2 focus:ring-[#00B19C] focus:ring-offset-2">
          <iconify-icon icon="mdi:archive-plus-outline" width="18" aria-hidden="true"></iconify-icon>
          Tambah Wadah
        </button>
      @endif
      <label class="relative block w-full sm:ml-auto sm:w-72">
        <span class="sr-only">Cari wadah</span>
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
          <iconify-icon icon="mdi:magnify" width="17" aria-hidden="true"></iconify-icon>
        </span>
        <input type="search" name="search" x-model="searchQuery" @input="searchToolboxes()"
          maxlength="100" placeholder="Cari wadah..." :aria-busy="filterLoading.toString()"
          class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm leading-5 text-slate-800 placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
      </label>
    </div>

    <div x-cloak x-show="filterError"
      class="mb-3 flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800"
      role="alert">
      <span x-text="filterError"></span>
      <button type="button" @click="filterToolboxes()"
        class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">Coba lagi</button>
    </div>

    <div x-ref="toolboxResults" @click="handleToolboxResultsClick($event)" :aria-busy="filterLoading.toString()">
      @fragment('toolbox-results')
      <div class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Kode</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Nama Wadah</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Keterangan</th>
              <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Jumlah Barang</th>
              @if (auth()->user()?->canManageInventory())
                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Aksi</th>
              @endif
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse ($toolboxes as $toolbox)
              <tr data-toolbox-row
                data-searchable="{{ mb_strtolower(trim($toolbox->kode_toolbox.' '.$toolbox->nama_toolbox.' '.($toolbox->keterangan ?? ''))) }}"
                class="transition hover:bg-emerald-50/50">
                <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-700">{{ $toolbox->kode_toolbox }}</td>
                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $toolbox->nama_toolbox }}</td>
                <td class="max-w-sm px-4 py-3 text-sm text-slate-600">{{ $toolbox->keterangan ?: '-' }}</td>
                <td class="px-4 py-3 text-center text-sm text-slate-700">{{ $toolbox->lokasi_barangs_count }}</td>
                @if (auth()->user()?->canManageInventory())
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                      {{-- ASVS V1.2.1/V1.2.3: Escape JSON for both HTML attribute and JavaScript contexts. --}}
                      <button type="button"
                        data-toolbox-edit="{{ json_encode([
                            'toolbox_id' => $toolbox->toolbox_id,
                            'nama_toolbox' => $toolbox->nama_toolbox,
                            'keterangan' => $toolbox->keterangan,
                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                        class="inline-flex min-h-9 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <iconify-icon icon="mdi:pencil" width="15" aria-hidden="true"></iconify-icon>
                        Edit
                      </button>
                      <button type="button"
                        data-toolbox-delete="{{ json_encode([
                            'toolbox_id' => $toolbox->toolbox_id,
                            'nama_toolbox' => $toolbox->nama_toolbox,
                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                        <iconify-icon icon="mdi:delete-outline" width="16" aria-hidden="true"></iconify-icon>
                        Hapus
                      </button>
                    </div>
                  </td>
                @endif
              </tr>
            @empty
              <tr>
                <td colspan="{{ auth()->user()?->canManageInventory() ? 5 : 4 }}" class="px-6 py-12 text-center">
                  <iconify-icon icon="mdi:archive-search-outline" class="mb-2 text-slate-300" width="42" aria-hidden="true"></iconify-icon>
                  <p class="text-sm font-medium text-slate-600">
                    @if (filled($filters['search'] ?? null))
                      Tidak ada wadah yang cocok dengan kata kunci
                      <strong class="font-bold text-slate-900">"{{ $filters['search'] }}"</strong>.
                    @else
                      Belum ada wadah yang terdaftar.
                    @endif
                  </p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500">Menampilkan {{ $toolboxes->count() }} dari {{ $toolboxes->total() }} wadah</p>
        <x-pagination :paginator="$toolboxes" label="Navigasi halaman wadah"
          link-attribute="data-toolbox-pagination" />
      </div>
      </div>
      @endfragment
    </div>

    @if (auth()->user()?->canManageInventory())
      <template x-teleport="body">
        <div x-cloak x-show="modalOpen" x-transition.opacity
          class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto p-4 sm:p-6"
          @click.self="if (!formBusy) modalOpen = false" role="presentation">
          <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" @click="if (!formBusy) modalOpen = false"></div>
          <section x-show="modalOpen" x-transition
            class="relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
            role="dialog" aria-modal="true" aria-labelledby="toolbox-modal-title">
            <form :action="formAction" method="POST" @submit="formBusy = true">
              @csrf
              <input type="hidden" name="toolbox_form" :value="modalMode">
              <input type="hidden" name="toolbox_id" :value="currentToolbox.toolbox_id">
              <input type="hidden" name="return_to" value="toolbox">
              <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
              </template>
              <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-3">
                  <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                    <iconify-icon :icon="modalMode === 'create' ? 'mdi:archive-plus-outline' : 'mdi:archive-edit-outline'"
                      width="22" aria-hidden="true"></iconify-icon>
                  </span>
                  <h2 id="toolbox-modal-title" class="text-base font-semibold text-slate-800"
                    x-text="modalMode === 'create' ? 'Tambah Wadah' : 'Edit Wadah'"></h2>
                </div>
                <button type="button" @click="modalOpen = false" :disabled="formBusy"
                  class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                  aria-label="Tutup formulir wadah">
                  <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
                </button>
              </div>
              <div class="space-y-4 px-5 py-4">
                <label class="block text-xs font-medium text-slate-700">
                  Nama Wadah <span class="text-rose-500">*</span>
                  <input name="nama_toolbox" type="text" maxlength="100" required x-model="currentToolbox.nama_toolbox"
                    class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                    placeholder="Nama wadah">
                  @error('nama_toolbox')
                    <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                  @enderror
                </label>
                <label class="block text-xs font-medium text-slate-700">
                  Keterangan
                  <textarea name="keterangan" maxlength="255" rows="3" x-model="currentToolbox.keterangan"
                    class="mt-1 block w-full resize-y rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20"
                    placeholder="Keterangan wadah"></textarea>
                  @error('keterangan')
                    <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                  @enderror
                </label>
              </div>
              <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                <button type="button" @click="modalOpen = false" :disabled="formBusy"
                  class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                  Batal
                </button>
                <button type="submit" :disabled="formBusy" :aria-busy="formBusy.toString()"
                  class="admin-submit-button inline-flex items-center justify-center gap-2 rounded-md border border-[#009F91] bg-[#00B19C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009A87] disabled:cursor-not-allowed disabled:opacity-70">
                  <iconify-icon x-show="formBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
                  <iconify-icon x-show="!formBusy"
                    :icon="modalMode === 'create' ? 'mdi:content-save-plus-outline' : 'mdi:content-save-edit-outline'"
                    width="18" aria-hidden="true"></iconify-icon>
                  <span x-show="!formBusy" x-text="modalMode === 'create' ? 'Simpan Wadah' : 'Simpan Perubahan'"></span>
                  <span x-show="formBusy">Menyimpan...</span>
                </button>
              </div>
            </form>
          </section>
        </div>
      </template>

      <template x-teleport="body">
        <div x-cloak x-show="deleteModalOpen" x-transition.opacity
          class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto p-4 sm:p-6"
          @click.self="cancelDelete()" role="presentation">
          <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" @click="cancelDelete()"></div>
          <section x-show="deleteModalOpen" x-transition
            class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]"
            role="alertdialog" aria-modal="true" aria-labelledby="toolbox-delete-title">
            <form :action="deleteAction" method="POST" @submit="deleteBusy = true">
              @csrf
              @method('DELETE')
              <div class="p-6 text-center">
                <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                  <iconify-icon icon="mdi:alert-circle" width="28" aria-hidden="true"></iconify-icon>
                </span>
                <h2 id="toolbox-delete-title" class="text-lg font-semibold text-slate-900">Hapus Wadah?</h2>
                <p class="mt-2 text-sm text-slate-600">
                  Apakah Anda yakin ingin menghapus wadah
                  <strong class="font-bold text-slate-800" x-text="pendingDeleteName"></strong> dari daftar wadah?
                </p>
              </div>
              <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" @click="cancelDelete()" :disabled="deleteBusy"
                  class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                  Batal
                </button>
                <button type="submit" :disabled="deleteBusy" :aria-busy="deleteBusy.toString()"
                  class="inline-flex min-w-28 items-center justify-center gap-2 rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-70">
                  <iconify-icon x-show="deleteBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
                  <iconify-icon x-show="!deleteBusy" icon="mdi:delete-outline" class="text-lg" aria-hidden="true"></iconify-icon>
                  <span x-show="!deleteBusy">Ya, Hapus</span>
                  <span x-show="deleteBusy">Menghapus...</span>
                </button>
              </div>
            </form>
          </section>
        </div>
      </template>
    @endif
  </div>
</x-layout.admin>
