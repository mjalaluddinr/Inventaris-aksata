<x-layout.admin title="Manajemen Akun - InvenTrack" header-title="Manajemen Akun"
  header-description="Kelola akun, peran, dan status akses pengguna.">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <style>
    .akun-surface {
      box-sizing: border-box;
      min-height: max(0px, calc(100vh - 10rem));
      margin-top: -2rem;
      background-color: #e8f8f4;
    }

    .akun-primary-button {
      background: #00b19c;
      border: 1px solid #009f91;
      color: #fff;
    }

    .akun-primary-button:hover {
      background: #009a87;
    }

    .akun-primary-button:disabled {
      background: #94a3b8;
      border-color: #cbd5e1;
      cursor: not-allowed;
    }

    .akun-modal-backdrop {
      background: linear-gradient(135deg, rgba(7, 59, 86, .84), rgba(2, 6, 23, .76), rgba(6, 78, 72, .78));
      backdrop-filter: blur(9px);
    }

    .akun-modal-panel {
      box-shadow: 0 24px 80px rgba(2, 25, 41, .4);
    }

    html.akun-modal-open {
      overflow: hidden;
    }

    html.akun-modal-open main {
      overflow: hidden;
    }

    .akun-form-grid {
      display: grid;
      gap: .75rem 1rem;
    }

    .akun-form-field {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: .375rem;
      background: #fff;
      padding: .5rem .75rem;
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

    @media (min-width: 640px) {
      .akun-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
  </style>

  {{-- ASVS 16.5.1: Keep raw importer exceptions out of client-visible feedback. --}}
  @php
    $feedbackMessage = (string) session('success', '');

    if (session()->has('import_log_id')) {
        $importMessage = $feedbackMessage;
        $feedbackMessage = match (session('import_status')) {
            'gagal' => 'Import gagal. Periksa ringkasan pada Log Import.',
            'sebagian' => 'Import selesai sebagian. Periksa ringkasan pada Log Import.',
            default => 'Import selesai.',
        };

        if (preg_match('/^Import selesai\.\s*Berhasil:\s*(\d+),\s*Gagal:\s*(\d+)\.$/', $importMessage, $importCounts) === 1) {
            $feedbackMessage = "Import selesai. Berhasil: {$importCounts[1]}, Gagal: {$importCounts[2]}.";
        }
    }
  @endphp
  <div x-data="{
      openModal: @js($errors->any() && in_array(old('akun_form'), ['create', 'edit'], true)),
      modalMode: @js(old('akun_form') === 'edit' ? 'edit' : 'create'),
      createDraft: { first_name: '', last_name: '', username: '', password: '', role: @js(auth()->user()?->role === 'admin' ? 'peminjam' : 'laboran'), is_active: true, must_change_password: false, privileges: [], legacyPrivileges: [] },
      currentUser: {},
      privilegeOptions: ['melihat', 'meminjamkan', 'manajemen'],
      roleDialogOpen: false,
      roleDialogDraft: { role: 'peminjam', privileges: [] },
      roleDialogSavesAccount: false,
      passwordVisible: false,
      openImport: false,
      deleteModalOpen: false,
      deleteShaking: false,
      feedbackOpen: @js(session()->has('success')),
      feedbackMessage: @js($feedbackMessage),
      feedbackAccountName: '',
      feedbackAccountAction: '',
      feedbackStatus: @js(session('import_status') ?? 'success'),
      importLogId: @js(session('import_log_id')),
      feedbackTimeout: null,
      pendingDeleteForm: null,
      pendingDeleteUsername: '',
      searchQuery: @js($filters['search'] ?? ''),
      resultsUrl: @js(route('admin.akun.index')),
      searchRequest: null,
      searchTimer: null,
      searchPreviewCount: null,
      searchError: '',
      accountBusy: false,
      importFileName: '',
      importFileSize: '',
      openLogDetail: false,
      logDetailLoading: false,
      logDetailData: null,
      csrf: '{{ csrf_token() }}',
      statuses: { @foreach ($users as $u) '{{ $u->akun_id }}': {{ $u->is_active ? 'true' : 'false' }}, @endforeach },
  
      init() {
          if (this.modalMode === 'edit' && this.openModal) {
              const normalizedPrivileges = this.normalizePrivileges(
                  @js(old('privileges', [])),
                  @js(old('role', 'peminjam'))
              );
              this.currentUser = {
                  akun_id: @js(old('akun_id')),
                  first_name: @js(old('first_name', '')),
                  last_name: @js(old('last_name', '')),
                  username: @js(old('username', '')),
                  password: '',
                  role: @js(old('role', 'peminjam')),
                  is_active: @js(old('is_active', '1') === '1'),
                  must_change_password: @js(in_array((string) old('must_change_password', '0'), ['1', 'true', 'on'], true)),
                  privileges: normalizedPrivileges.selected,
                  legacyPrivileges: normalizedPrivileges.legacy
              };
          } else { this.currentUser = this.createDraft; }
          this.bindAccountListEvents(this.$refs.accountResults.querySelector('[data-account-tbody]'));
          if (this.feedbackOpen) this.scheduleFeedbackDismissal();
      },
  
      createUser() {
          this.modalMode = 'create';
          this.passwordVisible = false;
          this.roleDialogOpen = false;
          this.roleDialogSavesAccount = false;
          this.currentUser = JSON.parse(JSON.stringify(this.createDraft));
          this.openModal = true;
      },
      closeAccountModal() {
          this.openModal = false;
          this.roleDialogOpen = false;
          this.roleDialogSavesAccount = false;
      },
      closeRoleDialog() {
          this.roleDialogOpen = false;
          this.roleDialogSavesAccount = false;
      },
      setCreateRole(role) {
          this.currentUser.role = role;
          if (role !== 'peminjam') {
              this.currentUser.must_change_password = false;
          }
          if (role !== 'laboran') {
              this.currentUser.privileges = [];
              this.currentUser.legacyPrivileges = [];
          }
      },
      toggleCreatePrivilege(privilege, event) {
          const selected = new Set(this.currentUser.privileges);
          if (event.target.checked) {
              selected.add(privilege);
          } else {
              selected.delete(privilege);
          }
          if (selected.has('meminjamkan') || selected.has('manajemen')) {
              selected.add('melihat');
          }
          this.currentUser.privileges = this.privilegeOptions.filter(option => selected.has(option));
      },
      openRoleDialog(account = null) {
          this.roleDialogSavesAccount = Boolean(account);
          if (account) {
              this.modalMode = 'edit';
              this.passwordVisible = false;
              const normalizedPrivileges = this.normalizePrivileges(account.privileges, account.role);
              this.currentUser = {
                  akun_id: account.akun_id,
                  first_name: account.first_name || '',
                  last_name: account.last_name || '',
                  username: account.username,
                  role: account.role,
                  is_active: account.is_active == 1,
                  must_change_password: Boolean(account.must_change_password),
                  password: '',
                  privileges: normalizedPrivileges.selected,
                  legacyPrivileges: normalizedPrivileges.legacy
              };
              this.openModal = false;
          }
          const normalizedPrivileges = this.normalizePrivileges(
              this.currentUser.privileges,
              this.currentUser.role
          );
          this.roleDialogDraft = {
              role: this.currentUser.role,
              privileges: normalizedPrivileges.selected,
          };
          this.roleDialogOpen = true;
      },
      toggleRoleDialogPrivilege(privilege, event) {
          const selected = new Set(this.roleDialogDraft.privileges);
          if (event.target.checked) {
              selected.add(privilege);
          } else {
              selected.delete(privilege);
          }
          if (selected.has('meminjamkan') || selected.has('manajemen')) {
              selected.add('melihat');
          }
          this.roleDialogDraft.privileges = this.privilegeOptions.filter(option => selected.has(option));
      },
      async applyRoleDialog() {
          const shouldSaveAccount = this.roleDialogSavesAccount;
          this.currentUser.role = this.roleDialogDraft.role;
          if (this.currentUser.role !== 'peminjam') {
              this.currentUser.must_change_password = false;
          }
          if (this.currentUser.role === 'laboran') {
              const selected = new Set(this.roleDialogDraft.privileges);
              if (selected.has('meminjamkan') || selected.has('manajemen')) {
                  selected.add('melihat');
              }
              this.currentUser.privileges = this.privilegeOptions.filter(option => selected.has(option));
          } else {
              this.currentUser.privileges = [];
              this.currentUser.legacyPrivileges = [];
          }
          if (shouldSaveAccount) {
              const saved = await this.sendCrudRequest(document.querySelector('#account-form'), {
                  role: this.currentUser.role,
                  privileges: this.currentUser.role === 'laboran'
                      ? [...this.currentUser.privileges, ...(this.currentUser.legacyPrivileges || [])]
                      : [],
              });
              if (saved) {
                  this.closeRoleDialog();
              }
          } else {
              this.closeRoleDialog();
          }
      },
  
      editUser(user) {
          this.modalMode = 'edit';
          this.passwordVisible = false;
          const normalizedPrivileges = this.normalizePrivileges(user.privileges, user.role);
          this.currentUser = {
              akun_id: user.akun_id,
              first_name: user.first_name || '',
              last_name: user.last_name || '',
              username: user.username,
              role: user.role,
              is_active: user.is_active == 1,
              must_change_password: Boolean(user.must_change_password),
              password: '',
              privileges: normalizedPrivileges.selected,
              legacyPrivileges: normalizedPrivileges.legacy
          };
          this.openModal = true;
      },
      normalizePrivileges(privileges, role = '') {
          const allowedPrivileges = ['melihat', 'meminjamkan', 'manajemen'];
          const normalized = Array.isArray(privileges)
              ? [...new Set(privileges
                  .filter(privilege => typeof privilege === 'string' && privilege.trim() !== '')
                  .map(privilege => privilege.trim().toLocaleLowerCase()))]
              : [];
          const selected = normalized.filter(privilege => allowedPrivileges.includes(privilege));
          if (role === 'laboran' && (selected.includes('meminjamkan') || selected.includes('manajemen'))) {
              selected.push('melihat');
          }

          return {
              selected: [...new Set(selected)],
              legacy: normalized.filter(privilege => !allowedPrivileges.includes(privilege)),
          };
      },
      scheduleFeedbackDismissal() {
          window.clearTimeout(this.feedbackTimeout);
          this.feedbackTimeout = window.setTimeout(() => {
              this.feedbackOpen = false;
              this.feedbackAccountName = '';
              this.feedbackAccountAction = '';
              this.feedbackTimeout = null;
          }, 2000);
      },
      dismissFeedback() {
          window.clearTimeout(this.feedbackTimeout);
          this.feedbackTimeout = null;
          this.feedbackOpen = false;
          this.feedbackAccountName = '';
          this.feedbackAccountAction = '';
      },
      accountFieldLabel(field) {
          if (field.startsWith('privileges')) return 'Hak akses';
          return ({
              first_name: 'Nama Depan',
              last_name: 'Nama Belakang',
              username: 'Username',
              password: 'Password',
              role: 'Role',
          })[field] || null;
      },
      privilegeLabel(privilege) {
          return ({
              melihat: 'Melihat',
              meminjamkan: 'Meminjamkan',
              manajemen: 'Manajemen',
          })[privilege.trim().toLocaleLowerCase()] || privilege;
      },
      bindAccountListEvents(body) {
          if (!body || body.dataset.ajaxHandlers) return;
          body.addEventListener('click', event => {
              const roleButton = event.target.closest('[data-account-role]');
              if (roleButton) {
                  this.openRoleDialog(JSON.parse(roleButton.dataset.accountRole));
                  return;
              }
              const editButton = event.target.closest('[data-account-user]');
              if (editButton) this.editUser(JSON.parse(editButton.dataset.accountUser));
          });
          body.addEventListener('submit', event => {
              const deleteForm = event.target.closest('form[data-username]');
              if (deleteForm) {
                  event.preventDefault();
                  this.requestDelete(deleteForm);
              }
          });
          body.dataset.ajaxHandlers = 'true';
      },
  
      matchesAccount(searchableText) {
          const searchable = searchableText.toLocaleLowerCase();
          const searchTerms = this.searchQuery.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
          return searchTerms.every(term => searchable.includes(term));
      },
      filterAccounts(query = this.searchQuery) {
          this.searchQuery = query;
          const rows = Array.from(this.$refs.accountResults.querySelectorAll('[data-account-row]'));
          rows.forEach(row => row.hidden = !this.matchesAccount(row.dataset.searchable || ''));
          this.searchPreviewCount = rows.filter(row => !row.hidden).length;
          window.clearTimeout(this.searchTimer);
          this.searchTimer = window.setTimeout(() => {
              this.searchTimer = null;
              this.searchAccounts();
          }, 100);
      },
      async searchAccounts(url = null) {
          window.clearTimeout(this.searchTimer);
          this.searchTimer = null;
          if (this.searchRequest) this.searchRequest.abort();
          const request = new AbortController();
          this.searchRequest = request;
          this.searchError = '';

          const requestUrl = new URL(url || this.resultsUrl, window.location.href);
          if (!url) {
              requestUrl.search = new URLSearchParams({ search: this.searchQuery.trim() }).toString();
          }

          try {
              const response = await fetch(requestUrl, {
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                  signal: request.signal,
              });
              if (!response.ok || response.redirected) throw new Error('Daftar akun tidak dapat dimuat.');

              this.$refs.accountResults.innerHTML = await response.text();
              this.statuses = {};
              this.$refs.accountResults.querySelectorAll('[data-account-row]').forEach(row => {
                  this.statuses[row.dataset.accountId] = row.dataset.accountStatus === '1';
              });
              window.Alpine.initTree(this.$refs.accountResults);
              this.bindAccountListEvents(this.$refs.accountResults.querySelector('[data-account-tbody]'));
              this.searchPreviewCount = null;
              window.history.replaceState({}, '', requestUrl);
          } catch (error) {
              if (error.name !== 'AbortError') {
                  this.searchError = error.message || 'Daftar akun tidak dapat dimuat.';
              }
          } finally {
              if (this.searchRequest === request) {
                  this.searchRequest = null;
              }
          }
      },
      handleAccountPagination(event) {
          const link = event.target instanceof Element ? event.target.closest('a') : null;
          if (!link || !this.$refs.accountResults.contains(link)) return;
          event.preventDefault();
          this.searchAccounts(link.href);
      },
  
      async toggleAccountStatus(userId) {
          try {
              const response = await fetch(`/admin/akun/${userId}/toggle-status`, {
                  method: 'PATCH',
                  headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
              });
              const data = await response.json();
              if (!response.ok || !data.success) {
                  throw new Error('Gagal mengubah status akun.');
              }
              this.statuses[userId] = data.is_active;
              this.importLogId = null;
              this.feedbackAccountName = '';
              this.feedbackAccountAction = '';
              this.feedbackMessage = data.message;
              this.feedbackStatus = 'success';
              this.feedbackOpen = true;
              this.scheduleFeedbackDismissal();
          } catch (error) {
              console.error('Gagal mengubah status:', error);
              this.importLogId = null;
              this.feedbackAccountName = '';
              this.feedbackAccountAction = '';
              this.feedbackMessage = 'Gagal mengubah status akun. Silakan coba lagi.';
              this.feedbackStatus = 'error';
              this.feedbackOpen = true;
              this.scheduleFeedbackDismissal();
          }
      },
  
      async submitAccount(event) {
          const form = event.target;
          this.feedbackAccountName = '';
          this.feedbackAccountAction = '';
          const invalidFields = Array.from(form.querySelectorAll(':invalid'));
          form.querySelectorAll('[required]').forEach(field => {
              if (!field.value.trim() && !invalidFields.includes(field)) invalidFields.push(field);
          });

          if (invalidFields.length) {
              const invalidFieldLabels = [...new Set(
                  invalidFields
                      .map(field => this.accountFieldLabel(field.name))
                      .filter(Boolean)
              )];
              this.feedbackMessage = invalidFieldLabels.length
                  ? `Data tidak valid: ${invalidFieldLabels.join(', ')}.`
                  : 'Data akun belum lengkap atau belum valid.';
              this.feedbackStatus = 'error';
              this.feedbackOpen = true;
              this.scheduleFeedbackDismissal();
              return false;
          }

          await this.sendCrudRequest(form);
      },
      async sendCrudRequest(form, roleUpdate = null) {
          this.accountBusy = true;
          this.feedbackAccountName = '';
          this.feedbackAccountAction = '';
          try {
              const formData = new FormData(form);
              if (roleUpdate) {
                  formData.set('role', roleUpdate.role);
                  formData.delete('privileges[]');
                  roleUpdate.privileges.forEach(privilege => formData.append('privileges[]', privilege));
              }
              const isDeleteRequest = formData.get('_method') === 'DELETE';
              const accountAction = isDeleteRequest
                  ? 'dihapus'
                  : formData.get('akun_form') === 'edit' ? 'diperbarui' : 'ditambahkan';
              const accountName = isDeleteRequest
                  ? this.pendingDeleteUsername
                  : [formData.get('first_name'), formData.get('last_name')]
                      .map(name => String(name || '').trim())
                      .filter(Boolean)
                      .join(' ') || String(formData.get('username') || '').trim();
              const response = await fetch(form.action, {
                  method: 'POST',
                  body: formData,
                  headers: {
                      'Accept': 'text/html',
                      'X-CSRF-TOKEN': this.csrf,
                      ...(roleUpdate ? { 'X-Requested-With': 'XMLHttpRequest' } : {}),
                  }
              });
              const html = await response.text();
              const responseDocument = new DOMParser().parseFromString(html, 'text/html');
              const validationMessage = responseDocument.querySelector('[data-account-validation]')?.dataset.accountValidation;
              const validationFields = responseDocument.querySelector('[data-account-validation]')?.dataset.accountInvalidFields;
              const successMessage = responseDocument.querySelector('[data-account-success]')?.dataset.accountSuccess;
              const hasAccountResults = responseDocument.querySelector('[data-account-tbody]');
              const accountIndexPath = new URL(this.resultsUrl, window.location.href).pathname;
              const redirectedToAccountIndex = response.redirected
                  && new URL(response.url, window.location.href).pathname === accountIndexPath;
              const deleteSucceeded = isDeleteRequest && response.ok
                  && redirectedToAccountIndex && Boolean(hasAccountResults);
              if (validationMessage) {
                  const invalidFieldLabels = [...new Set(
                      (validationFields || '')
                          .split(',')
                          .map(field => this.accountFieldLabel(field))
                          .filter(Boolean)
                  )];
                  this.feedbackMessage = invalidFieldLabels.length
                      ? `Data tidak valid: ${invalidFieldLabels.join(', ')}.`
                      : validationMessage;
                  this.feedbackStatus = 'error';
                  this.feedbackOpen = true;
                  this.scheduleFeedbackDismissal();
                  return false;
              }
              if (!response.ok || (!successMessage && !deleteSucceeded)) {
                  throw new Error('Permintaan akun tidak berhasil.');
              }
  
              this.refreshAccountList(responseDocument);
              this.openModal = false;
              this.feedbackAccountName = accountName;
              this.feedbackAccountAction = accountAction;
              this.feedbackMessage = successMessage || 'Akun berhasil dihapus.';
              this.feedbackStatus = 'success';
              this.feedbackOpen = true;
              this.scheduleFeedbackDismissal();
              return true;
          } catch (error) {
              this.feedbackMessage = error.message || 'Terjadi kesalahan saat menyimpan akun.';
              this.feedbackStatus = 'error';
              this.feedbackOpen = true;
              this.scheduleFeedbackDismissal();
              return false;
          } finally { this.accountBusy = false; }
      },
  
      refreshAccountList(responseDocument) {
          const refreshedResults = responseDocument.querySelector('[data-account-results]');
          const refreshedTbody = responseDocument.querySelector('[data-account-tbody]');
          if (!refreshedResults && !refreshedTbody) {
              throw new Error('Server tidak mengirim daftar akun terbaru.');
          }
          window.clearTimeout(this.searchTimer);
          this.searchTimer = null;
          this.$refs.accountResults.innerHTML = refreshedResults
              ? refreshedResults.innerHTML
              : responseDocument.body.innerHTML;
          this.statuses = {};
          this.$refs.accountResults.querySelectorAll('[data-account-row]').forEach(row => {
              this.statuses[row.dataset.accountId] = row.dataset.accountStatus === '1';
          });
          window.Alpine.initTree(this.$refs.accountResults);
          this.bindAccountListEvents(this.$refs.accountResults.querySelector('[data-account-tbody]'));
          if (this.searchQuery.trim()) {
              this.filterAccounts(this.searchQuery);
          } else {
              this.searchPreviewCount = null;
          }
      },
  
      requestDelete(form) {
          this.pendingDeleteForm = form;
          this.pendingDeleteUsername = form.dataset.username;
          this.deleteModalOpen = true;
      },
      cancelDelete() {
          this.deleteModalOpen = false;
          this.pendingDeleteForm = null;
          this.pendingDeleteUsername = '';
          this.deleteShaking = false;
      },
      shakeDelete() {
          this.deleteShaking = false;
          window.setTimeout(() => this.deleteShaking = true, 10);
          window.setTimeout(() => this.deleteShaking = false, 460);
      },
      handleEscape(event) {
          if (this.roleDialogOpen) {
              event.preventDefault();
              this.roleDialogOpen = false;
              return;
          }
          if (this.deleteModalOpen) {
              event.preventDefault();
              this.shakeDelete();
              return;
          }
          this.openModal = false;
          this.openImport = false;
          this.openLogDetail = false;
          this.feedbackOpen = false;
      },
      async submitDelete() { if (this.pendingDeleteForm && await this.sendCrudRequest(this.pendingDeleteForm)) { this.cancelDelete(); } },
  
      setImportFile(files) {
          if (files && files.length) {
              this.importFileName = files[0].name;
              this.importFileSize = files[0].size < 1024 ?
                  `${files[0].size} B` :
                  `${(files[0].size / 1024).toFixed(1)} KB`;
          }
      },
      clearImportFile() {
          this.importFileName = '';
          this.importFileSize = '';
          this.$refs.accountImportFile.value = '';
      },
      openImportFilePicker() { this.$refs.accountImportFile.click(); },
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
          this.$refs.accountImportFile.files = transfer.files;
          this.setImportFile(files);
      },
      async fetchLogDetail(logId) {
          if (!logId) return;
          this.openLogDetail = true;
          this.logDetailLoading = true;
          this.logDetailData = null;
          try {
              const response = await fetch(`/admin/akun/log/${logId}/detail`);
              const json = await response.json();
              if (json.success) { this.logDetailData = json.data; }
          } catch (error) { console.error('Gagal memuat detail log:', error); } finally { this.logDetailLoading = false; }
      }
  }" @keydown.escape.window="handleEscape($event)"
    x-effect="document.documentElement.classList.toggle('akun-modal-open', openModal || roleDialogOpen || openImport || deleteModalOpen || openLogDetail)"
    class="akun-surface relative -mx-8 -mb-8 min-h-full bg-[#E8F8F4]">

    <div class="p-4 sm:p-8">
      <div class="akun-toolbar mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-2">
          <button type="button" @click="createUser()"
            class="akun-primary-button inline-flex items-center gap-2 rounded-lg border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#009a87] focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
            <iconify-icon icon="mdi:plus" width="18"></iconify-icon> Tambah Akun
          </button>
          @if (auth()->user()?->canManageAllAccounts())
          <button type="button" @click="openImport = true"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
            <iconify-icon icon="mdi:file-excel" class="text-emerald-600" width="18"></iconify-icon> Import Excel
          </button>
          @endif
        </div>
        <label class="akun-search relative block w-full sm:w-72">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center"><iconify-icon icon="mdi:magnify"
              width="17" class="block text-slate-400"></iconify-icon></span>
          <input type="search" data-account-search x-model="searchQuery" @input="filterAccounts($event.target.value)"
            maxlength="100" placeholder="Cari Akun..."
            class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm leading-5 text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
        </label>
      </div>

      <div x-cloak x-show="searchError"
        class="mb-3 flex items-center justify-between rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800"
        role="alert">
        <span x-text="searchError"></span>
        <button type="button" @click="searchAccounts()"
          class="font-semibold underline focus:outline-none focus:ring-2 focus:ring-rose-400">
          Coba lagi
        </button>
      </div>

      <div x-ref="accountResults" data-account-results @click="handleAccountPagination($event)"
        :aria-busy="(searchRequest !== null).toString()">
        @fragment('account-results')
      @if ($errors->any() || session()->has('success'))
        <div class="hidden" data-account-validation="{{ $errors->first() }}"
          data-account-invalid-fields="{{ implode(',', $errors->keys()) }}"
          data-account-success="{{ session()->has('import_log_id') ? '' : $feedbackMessage }}"></div>
      @endif
      <div class="overflow-hidden rounded-xl border border-[#D3E3DF] bg-white shadow-sm mb-8">
        <div class="theme-scrollbar overflow-x-auto">
          <table class="min-w-full divide-y divide-[#DCE9E6]">
            <thead class="bg-[#F1F8F6]">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">No</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Nama & Username</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600">Login Terakhir</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600">Aksi</th>
              </tr>
            </thead>
            <tbody data-account-tbody class="divide-y divide-[#E7EFED] bg-white">
              @forelse ($users as $index => $user)
                <tr data-account-row data-account-id="{{ $user->akun_id }}"
                  data-account-status="{{ $user->is_active ? '1' : '0' }}"
                  data-searchable="{{ strtolower(trim($user->first_name . ' ' . $user->last_name . ' ' . $user->username)) }}"
                  x-show="matchesAccount($el.dataset.searchable)" class="transition-colors hover:bg-[#F7FBFA]">
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">{{ $users->firstItem() + $index }}</td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <div class="text-sm font-medium text-slate-800">{{ $user->first_name }} {{ $user->last_name }}
                    </div>
                    <div class="text-xs text-slate-500">{{ $user->username }}</div>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                    @php $roleColors = ['admin' => 'bg-rose-100 text-rose-800', 'laboran' => 'bg-blue-100 text-blue-800', 'peminjam' => 'bg-emerald-100 text-emerald-800']; @endphp
                    {{-- ASVS 1.2.1, 1.2.3: Keep account data escaped for the HTML attribute and JSON contexts. --}}
                    <button type="button"
                      data-account-role="{{ json_encode([
                            'akun_id' => $user->akun_id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'username' => $user->username,
                            'role' => $user->role,
                            'is_active' => $user->is_active,
                            'must_change_password' => $user->must_change_password,
                            'privileges' => $user->privileges->pluck('hak_akses')->values(),
                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                      class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-800' }} transition hover:ring-2 hover:ring-sky-300 focus:outline-none focus:ring-2 focus:ring-blue-400"
                      aria-label="Ubah role {{ $user->first_name }} {{ $user->last_name }}"
                      aria-haspopup="dialog" aria-controls="account-role-dialog">
                      {{ ucfirst($user->role) }}
                      <iconify-icon icon="mdi:pencil-outline" width="14" aria-hidden="true"></iconify-icon>
                    </button>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <button @click="toggleAccountStatus('{{ $user->akun_id }}')"
                      class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-xs font-medium transition cursor-pointer hover:opacity-80"
                      :class="statuses['{{ $user->akun_id }}'] ? 'bg-emerald-100 text-emerald-800' :
                          'bg-rose-100 text-rose-800'">
                      <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                          :class="statuses['{{ $user->akun_id }}'] ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2"
                          :class="statuses['{{ $user->akun_id }}'] ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                      </span>
                      <span x-text="statuses['{{ $user->akun_id }}'] ? 'Aktif' : 'Nonaktif'"></span>
                    </button>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                    {{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->format('d M Y H:i') : 'Belum Pernah' }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                      <button type="button"
                        data-account-user="{{ json_encode([
                            'akun_id' => $user->akun_id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'username' => $user->username,
                            'role' => $user->role,
                            'is_active' => $user->is_active,
                            'must_change_password' => $user->must_change_password,
                            'privileges' => $user->privileges->pluck('hak_akses')->values(),
                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                        class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <iconify-icon icon="mdi:pencil" width="15"></iconify-icon> Edit
                      </button>
                      <form action="{{ route('admin.akun.destroy', $user->akun_id) }}" method="POST"
                        @submit.prevent="requestDelete($el)" data-username="{{ $user->username }}">
                        @csrf @method('DELETE')
                        <button type="submit"
                          class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">
                          <iconify-icon icon="mdi:delete-outline" width="16"></iconify-icon> Delete
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="px-6 py-12 text-center"><iconify-icon icon="mdi:account-off-outline"
                      class="mb-2 text-slate-300" width="42"></iconify-icon>
                    @if (filled($filters['search'] ?? null))
                      <p class="text-sm font-medium text-slate-600">
                        Tidak ada akun yang terdaftar dengan kata kunci
                        <strong class="font-bold text-slate-900">"{{ $filters['search'] }}"</strong>.
                      </p>
                    @else
                      <p class="text-sm font-medium text-slate-600">Belum ada akun yang terdaftar.</p>
                    @endif
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div data-account-list-footer
          class="flex flex-col gap-3 border-t border-[#E2ECE9] bg-[#F7FBFA] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-xs text-slate-500"
            x-text="searchPreviewCount === null ? 'Menampilkan {{ $users->count() }} dari {{ $users->total() }} akun' : `Menampilkan ${searchPreviewCount} hasil sementara`">
            Menampilkan {{ $users->count() }} dari {{ $users->total() }} akun
          </p>
          @if ($users->hasPages())
            @php
              $paginationStart = max(1, min($users->currentPage() - 1, $users->lastPage() - 2));
              $paginationEnd = min($users->lastPage(), $paginationStart + 2);
            @endphp
            <nav aria-label="Navigasi halaman akun" class="flex items-center justify-end gap-2">
              @if (!$users->onFirstPage())
                <a href="{{ $users->previousPageUrl() }}" aria-label="Halaman sebelumnya"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                  <iconify-icon icon="mdi:chevron-left" width="22"></iconify-icon>
                </a>
              @endif
              @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
                @if ($page === $users->currentPage())
                  <span aria-current="page"
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-[#00CFA8] px-3 text-sm font-semibold text-slate-950 shadow-[0_4px_10px_rgba(0,177,156,0.2)]">
                    {{ $page }}
                  </span>
                @else
                  <a href="{{ $users->url($page) }}" aria-label="Halaman {{ $page }}"
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border border-[#00BFA5] bg-white px-3 text-sm font-semibold text-slate-900 shadow-[0_4px_10px_rgba(0,177,156,0.12)] transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#00BFA5] focus:ring-offset-2">
                    {{ $page }}
                  </a>
                @endif
              @endfor
              @if ($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}" aria-label="Halaman berikutnya"
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
    </div>

    {{-- ========================================== --}}
    {{-- MODAL: Tambah / Edit Akun --}}
    {{-- ========================================== --}}
    <template x-teleport="body">
      <div x-show="openModal" x-transition.opacity style="display: none; z-index: 80;"
        class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
        role="dialog" aria-modal="true">
        <div class="akun-modal-backdrop absolute inset-0 backdrop-blur-md" @click="closeAccountModal()"></div>
        <div x-show="openModal" x-transition
          class="akun-modal-panel relative z-10 my-auto w-full max-w-xl overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">
          <form id="account-form"
            :action="modalMode === 'create' ? '{{ route('admin.akun.store') }}' :
                '{{ route('admin.akun.update', '__ID__') }}'
                .replace('__ID__', currentUser.akun_id)"
            method="POST" novalidate @submit.prevent="submitAccount($event)">
            @csrf
            <input type="hidden" name="akun_form" :value="modalMode">
            <input type="hidden" name="role" :value="currentUser.role">
            <input type="hidden" name="must_change_password" :value="currentUser.must_change_password ? '1' : '0'">
            <template x-for="privilege in currentUser.role === 'laboran' ? currentUser.privileges : []" :key="`selected-${privilege}`">
              <input type="hidden" name="privileges[]" :value="privilege">
            </template>
            <template x-for="privilege in currentUser.role === 'laboran' ? currentUser.legacyPrivileges || [] : []" :key="`legacy-${privilege}`">
              <input type="hidden" name="privileges[]" :value="privilege">
            </template>
            <template x-if="modalMode === 'edit'">
              <div><input type="hidden" name="_method" value="PUT"><input type="hidden" name="akun_id"
                  :value="currentUser.akun_id"></div>
            </template>

            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                  <iconify-icon :icon="modalMode === 'create' ? 'mdi:account-plus-outline' : 'mdi:account-edit-outline'"
                    width="22" aria-hidden="true"></iconify-icon>
                </span>
                <h2 class="text-base font-semibold text-slate-800"
                  x-text="modalMode === 'create' ? 'Tambah Akun' : 'Edit Akun'"></h2>
              </div>
              <button type="button" @click="closeAccountModal()"
                class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"><iconify-icon
                  icon="mdi:close" width="20"></iconify-icon></button>
            </div>

            <div class="akun-form-grid px-5 py-4 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Nama Depan <span
                    class="text-rose-500">*</span></label>
                <input type="text" name="first_name" x-model="currentUser.first_name" required maxlength="100"
                  placeholder="Masukkan nama depan"
                  class="akun-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Nama Belakang <span
                    class="text-rose-500">*</span></label>
                <input type="text" name="last_name" x-model="currentUser.last_name" required maxlength="100"
                  placeholder="Masukkan nama belakang"
                  class="akun-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Username <span
                    class="text-rose-500">*</span></label>
                <input type="text" name="username" x-model="currentUser.username" required maxlength="50"
                  placeholder="Contoh: andi.saputra"
                  class="akun-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
              </div>
              <div>
                {{-- ASVS 6.2.6: Mask passwords by default and reveal them only on explicit user action. --}}
                <label for="account-password" class="mb-1 block text-xs font-medium text-slate-700">Password <span
                    class="text-rose-500">*</span></label>
                <div class="relative">
                  <input id="account-password" type="password"
                    :type="passwordVisible ? 'text' : 'password'" name="password" x-model="currentUser.password"
                    :required="modalMode === 'create'" minlength="6" placeholder="Minimal 6 karakter"
                    class="akun-form-field block w-full rounded-md border border-slate-300 bg-white px-3 py-2 pr-11 text-sm text-slate-800 shadow-sm focus:border-[#08A9D6] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]/20">
                  <button type="button" @click="passwordVisible = !passwordVisible"
                    :aria-label="passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'"
                    :aria-pressed="passwordVisible"
                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#08A9D6]"
                    :title="passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'">
                    <iconify-icon :icon="passwordVisible ? 'mdi:eye-off' : 'mdi:eye'" width="18"
                      aria-hidden="true"></iconify-icon>
                  </button>
                </div>
                <p x-show="modalMode === 'edit'" class="mt-1 text-[11px] text-slate-500">Kosongkan jika tidak diubah.
                </p>
              </div>
              <div x-show="modalMode === 'create'" class="col-span-full space-y-3">
                <fieldset>
                  <legend class="mb-2 block text-xs font-medium text-slate-700">Pilih Role <span
                      class="text-rose-500">*</span></legend>
                  <div class="grid w-full {{ auth()->user()?->canManageAllAccounts() ? 'grid-cols-3' : 'grid-cols-1' }} gap-2">
                    <template x-for="role in @js(auth()->user()?->canManageAllAccounts() ? ['admin', 'laboran', 'peminjam'] : ['laboran'])" :key="`create-role-${role}`">
                      <label
                        class="flex cursor-pointer w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-sky-300 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:font-semibold has-[:checked]:text-sky-800">
                        <input type="radio" name="account-create-role" :value="role"
                          :checked="currentUser.role === role" @change="setCreateRole(role)"
                          class="border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span x-text="role.charAt(0).toUpperCase() + role.slice(1)"></span>
                      </label>
                    </template>
                  </div>
                </fieldset>
                <div x-show="currentUser.role === 'laboran'" x-transition
                  class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
                  <div class="mb-3 flex items-center gap-2">
                    <iconify-icon icon="mdi:shield-account" class="text-amber-600" width="20"
                      aria-hidden="true"></iconify-icon>
                    <h3 class="text-sm font-bold text-amber-800">Manajemen Hak Akses Laboran</h3>
                  </div>
                  <div class="mb-3 flex flex-wrap gap-3">
                    <template x-for="privilege in privilegeOptions" :key="`create-${privilege}`">
                      <label
                        class="inline-flex cursor-pointer select-none items-center gap-2 rounded-full border border-amber-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:border-amber-400 hover:bg-amber-100 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-100 has-[:checked]:font-semibold has-[:checked]:text-amber-800">
                        <input type="checkbox" :value="privilege"
                          :checked="currentUser.privileges.includes(privilege)"
                          @change="toggleCreatePrivilege(privilege, $event)"
                          class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span x-text="privilegeLabel(privilege)"></span>
                      </label>
                    </template>
                  </div>
                  <p x-show="currentUser.privileges.includes('meminjamkan') ||
                      currentUser.privileges.includes('manajemen')"
                    class="mb-3 text-xs text-amber-800">
                    Hak akses Melihat otomatis aktif jika Meminjamkan atau Manajemen dipilih.
                  </p>
                  <p x-show="currentUser.privileges.length === 0"
                    class="flex items-start gap-2 rounded-md border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700">
                    <iconify-icon icon="mdi:alert-circle" width="16" class="mt-0.5 shrink-0"
                      aria-hidden="true"></iconify-icon>
                    Role Laboran wajib memiliki minimal satu hak akses.
                  </p>
                </div>
              </div>
              <div x-show="currentUser.role === 'peminjam'" x-transition class="col-span-full">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                  <input type="checkbox" x-model="currentUser.must_change_password"
                    class="rounded border-slate-300 text-[#00A991] focus:ring-[#00A991]">
                  <span>Wajib ganti password saat login</span>
                </label>
              </div>

              <input type="hidden" name="is_active"
                :value="modalMode === 'create' ? '1' : (currentUser.is_active ? '1' : '0')">
            </div>

            <div class="akun-modal-footer flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
              <button type="button" @click="closeAccountModal()"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal</button>
              <button type="submit" :aria-busy="accountBusy.toString()"
                :disabled="accountBusy || (currentUser.role === 'laboran' && currentUser.privileges.length + (currentUser.legacyPrivileges || []).length === 0)"
                class="akun-primary-button inline-flex items-center justify-center rounded-md border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2">
                <iconify-icon x-show="accountBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
                <iconify-icon x-show="!accountBusy"
                  :icon="modalMode === 'create' ? 'mdi:content-save-plus-outline' : 'mdi:content-save-edit-outline'"
                  width="18" aria-hidden="true"></iconify-icon>
                <span x-show="!accountBusy" class="ml-2" x-text="modalMode === 'create' ? 'Simpan Akun' : 'Simpan Perubahan'"></span>
                <span x-show="accountBusy" class="sr-only">Sedang menyimpan...</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </template>
    {{-- ASVS 1.2.1: Render role and privilege values using text bindings, not HTML. --}}
    <template x-teleport="body">
      <div x-cloak x-show="roleDialogOpen" x-transition.opacity style="display: none; z-index: 90;"
        class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
        role="dialog" aria-modal="true" aria-labelledby="account-role-dialog-title" id="account-role-dialog">
        <button type="button" class="akun-modal-backdrop absolute inset-0 w-full cursor-default"
          @click="closeRoleDialog()" aria-label="Tutup pengaturan role"></button>
        <section x-show="roleDialogOpen" x-transition
          class="akun-modal-panel relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">
          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                <iconify-icon icon="mdi:shield-account-outline" width="22" aria-hidden="true"></iconify-icon>
              </span>
              <div>
                <h2 id="account-role-dialog-title" class="text-base font-semibold text-slate-800">Role & Hak Akses</h2>
                <p x-show="roleDialogSavesAccount"
                  x-text="[currentUser.first_name, currentUser.last_name].filter(Boolean).join(' ')"
                  class="mt-0.5 text-xs text-slate-500"></p>
              </div>
            </div>
            <button type="button" @click="closeRoleDialog()"
              class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
              aria-label="Tutup pengaturan role">
              <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
            </button>
          </div>

          <div class="space-y-4 px-5 py-4">
            <fieldset>
              <legend class="mb-2 block text-xs font-medium text-slate-700">Pilih Role</legend>
              <div class="grid w-full {{ auth()->user()?->canManageAllAccounts() ? 'grid-cols-3' : 'grid-cols-1' }} gap-2">
                @if (auth()->user()?->canManageAllAccounts())
                <label
                  class="flex cursor-pointer w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-sky-300 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:font-semibold has-[:checked]:text-sky-800">
                  <input type="radio" name="account-role-choice" value="admin" x-model="roleDialogDraft.role"
                    class="border-slate-300 text-sky-600 focus:ring-sky-500">
                  <span>Admin</span>
                </label>
                @endif
                <label
                  class="flex cursor-pointer w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-sky-300 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:font-semibold has-[:checked]:text-sky-800">
                  <input type="radio" name="account-role-choice" value="laboran" x-model="roleDialogDraft.role"
                    class="border-slate-300 text-sky-600 focus:ring-sky-500">
                  <span>Laboran</span>
                </label>
                @if (auth()->user()?->canManageAllAccounts())
                <label
                  class="flex cursor-pointer w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-sky-300 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:font-semibold has-[:checked]:text-sky-800">
                  <input type="radio" name="account-role-choice" value="peminjam" x-model="roleDialogDraft.role"
                    class="border-slate-300 text-sky-600 focus:ring-sky-500">
                  <span>Peminjam</span>
                </label>
                @endif
              </div>
            </fieldset>

            <div x-show="roleDialogDraft.role === 'laboran'" x-transition
              class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
              <div class="mb-3 flex items-center gap-2">
                <iconify-icon icon="mdi:shield-account" class="text-amber-600" width="20"
                  aria-hidden="true"></iconify-icon>
                <h3 class="text-sm font-bold text-amber-800">Manajemen Hak Akses Laboran</h3>
              </div>
              <div class="mb-3 flex flex-wrap gap-3">
                <template x-for="privilege in privilegeOptions" :key="privilege">
                  <label
                    class="inline-flex cursor-pointer select-none items-center gap-2 rounded-full border border-amber-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:border-amber-400 hover:bg-amber-100 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-100 has-[:checked]:font-semibold has-[:checked]:text-amber-800">
                    <input type="checkbox" :value="privilege"
                      :checked="roleDialogDraft.privileges.includes(privilege)"
                      @change="toggleRoleDialogPrivilege(privilege, $event)"
                      class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <span x-text="privilegeLabel(privilege)"></span>
                  </label>
                </template>
              </div>
              <p x-show="roleDialogDraft.privileges.includes('meminjamkan') ||
                  roleDialogDraft.privileges.includes('manajemen')"
                class="mb-3 text-xs text-amber-800">
                Hak akses Melihat otomatis aktif jika Meminjamkan atau Manajemen dipilih.
              </p>
              <div x-show="roleDialogDraft.privileges.length + (currentUser.legacyPrivileges || []).length === 0"
                class="flex items-start gap-2 rounded-md border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700">
                <iconify-icon icon="mdi:alert-circle" width="16" class="mt-0.5 shrink-0"
                  aria-hidden="true"></iconify-icon>
                <span>Role Laboran wajib memiliki minimal satu hak akses.</span>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" @click="roleDialogOpen = false"
              class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">
              Batal
            </button>
            <button type="button" @click="applyRoleDialog()" :aria-busy="accountBusy.toString()"
              :disabled="accountBusy || (roleDialogDraft.role === 'laboran' &&
                  roleDialogDraft.privileges.length + (currentUser.legacyPrivileges || []).length === 0)"
              class="akun-primary-button inline-flex items-center justify-center rounded-md border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
              <iconify-icon x-show="accountBusy" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
              <span x-show="!accountBusy" x-text="roleDialogSavesAccount ? 'Simpan Role' : 'Terapkan'"></span>
              <span x-show="accountBusy" class="sr-only">Sedang menyimpan...</span>
            </button>
          </div>
        </section>
      </div>
    </template>
    {{-- ========================================== --}}
    {{-- MODAL: Import Excel --}}
    {{-- ========================================== --}}
    @if (auth()->user()?->canManageAllAccounts())
    <template x-teleport="body">
      <div x-show="openImport" x-transition.opacity style="display: none; z-index: 90;"
        class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
        role="dialog" aria-modal="true">
        <div class="akun-modal-backdrop absolute inset-0 backdrop-blur-md" @click="openImport = false"></div>
        <div x-show="openImport" x-transition
          class="akun-modal-panel relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">

          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-800">Import Data Akun (Excel)</h2>
            <button type="button" @click="openImport = false"
              class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600">
              <iconify-icon icon="mdi:close" width="20"></iconify-icon>
            </button>
          </div>

          <form action="{{ route('admin.akun.import') }}" method="POST" enctype="multipart/form-data"
            class="px-5 py-4">
            @csrf
            <div class="space-y-4">
              <input id="akun-import-file" type="file" name="file" x-ref="accountImportFile"
                @change="setImportFile($event.target.files)" accept=".xlsx,.xls,.csv" class="hidden">
              <div role="button" tabindex="0" @click="openImportFilePicker()"
                @keydown="handleImportFileKeydown($event)" @dragover.prevent @drop.prevent="handleImportDrop($event)"
                class="group cursor-pointer rounded-lg border-2 border-dashed p-5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#08A9D6] focus-visible:ring-offset-2"
                :class="importFileName ? 'border-emerald-300 bg-emerald-50/70 hover:border-emerald-400 hover:bg-emerald-50' :
                    'border-slate-300 bg-slate-50 text-center hover:border-[#08A9D6] hover:bg-[#F0FDFA]'">
                <template x-if="!importFileName">
                  <div class="flex flex-col items-center">
                    <span
                      class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 transition group-hover:bg-emerald-200">
                      <iconify-icon icon="mdi:file-excel-outline" width="32"></iconify-icon>
                    </span>
                    <p class="text-sm font-semibold text-slate-800">Seret file Excel ke sini</p>
                    <p class="mt-1 text-sm text-slate-600">atau klik area ini untuk memilih file</p>
                    <p
                      class="mt-3 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                      .xlsx, .xls, .csv · Maks. 5 MB
                    </p>
                  </div>
                </template>
                @endif
                <template x-if="importFileName">
                  <div class="flex items-center gap-3">
                    <span
                      class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-200">
                      <iconify-icon icon="mdi:file-excel" width="26"></iconify-icon>
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-semibold text-slate-800"
                        x-text="importFileName"></span>
                      <span class="mt-1 block text-xs text-emerald-800">
                        File siap diimpor <span class="text-emerald-700" x-text="importFileSize"></span>
                      </span>
                      <span class="mt-1 block text-xs font-medium text-[#087F70]">Klik untuk mengganti file</span>
                    </span>
                    <button type="button" @click.stop="clearImportFile()"
                      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-400"
                      aria-label="Hapus file yang dipilih">
                      <iconify-icon icon="mdi:close-circle-outline" width="20"></iconify-icon>
                    </button>
                  </div>
                </template>
              </div>

              <div class="rounded-md bg-blue-50 p-3 text-xs text-blue-800 border border-blue-200">
                <div class="flex gap-2">
                  <iconify-icon icon="mdi:information" class="mt-0.5 shrink-0" width="16"></iconify-icon>
                  <div class="space-y-2">
                    <p class="font-semibold">Contoh Format Untuk Import Akun</p>
                    <a href="{{ asset('import-templates/format-import-akun.xlsx') }}" download
                      class="inline-flex items-center gap-1 font-semibold text-[#087E78] hover:text-[#065f59] focus:outline-none focus:ring-2 focus:ring-[#08A9D6]">
                      <iconify-icon icon="mdi:download" width="16" aria-hidden="true"></iconify-icon>
                      Unduh format-import-akun.xlsx
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-200 pt-4">
              <button type="button" @click="openImport = false"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal</button>
              <button type="submit"
                class="akun-primary-button rounded-md border border-[#009F91] bg-sidebar-start px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-sidebar-start focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                :disabled="!importFileName">
                <iconify-icon icon="mdi:upload" width="16"></iconify-icon> Simpan Import
              </button>
            </div>
          </form>
        </div>
      </div>
    </template>

    {{-- ========================================== --}}
    {{-- MODAL: Konfirmasi Hapus --}}
    {{-- ========================================== --}}
    <template x-teleport="body">
      <div x-show="deleteModalOpen" x-transition.opacity style="display: none; z-index: 100;"
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
        role="dialog" aria-modal="true">
        <div class="akun-modal-backdrop absolute inset-0 backdrop-blur-md" @click="if (!accountBusy) cancelDelete()"></div>
        <div x-show="deleteModalOpen" x-transition :class="deleteShaking ? 'animate-[shake_0.4s_ease-in-out]' : ''"
          class="akun-modal-panel relative z-10 my-auto w-full max-w-md overflow-hidden rounded-xl border-2 border-rose-300 bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">

          <div class="p-6 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100">
              <iconify-icon icon="mdi:alert-circle" class="text-rose-600" width="28"></iconify-icon>
            </div>
            <h3 class="text-lg font-semibold text-slate-900">Hapus Akun?</h3>
            <p class="mt-2 text-sm text-slate-600">
              Anda akan menghapus akun <span class="font-bold text-slate-800" x-text="pendingDeleteUsername"></span>.
              Tindakan ini tidak dapat dibatalkan dan semua hak akses terkait akan ikut terhapus.
            </p>
          </div>
          <div class="flex justify-center gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <button type="button" @click="cancelDelete()" :disabled="accountBusy"
              class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Batal</button>
            <button type="button" @click="submitDelete()" :disabled="accountBusy" :aria-busy="accountBusy.toString()"
              class="inline-flex items-center justify-center rounded-md border border-rose-300 bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70">
              <iconify-icon x-show="accountBusy" icon="mdi:loading" class="animate-spin text-lg"
                aria-hidden="true"></iconify-icon>
              <span x-show="!accountBusy">Ya, Hapus</span>
              <span x-show="accountBusy" class="sr-only">Sedang menghapus...</span>
            </button>
          </div>
        </div>
      </div>
    </template>

    {{-- ========================================== --}}
    {{-- MODAL: Detail Log Import --}}
    {{-- ========================================== --}}
    <template x-teleport="body">
      <div x-show="openLogDetail" x-transition.opacity style="display: none; z-index: 110;"
        class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto overscroll-contain p-4 sm:p-6"
        role="dialog" aria-modal="true">
        <div class="akun-modal-backdrop absolute inset-0 backdrop-blur-md" @click="openLogDetail = false"></div>
        <div x-show="openLogDetail" x-transition
          class="akun-modal-panel relative z-10 my-auto w-full max-w-2xl overflow-hidden rounded-xl border-2 border-[#08A9D6] bg-white shadow-[0_24px_80px_rgba(2,25,41,0.4)]">

          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-800">Detail Log Import</h2>
            <button type="button" @click="openLogDetail = false"
              class="rounded-md p-1 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600">
              <iconify-icon icon="mdi:close" width="20"></iconify-icon>
            </button>
          </div>

          <div class="px-5 py-4 max-h-[60vh] overflow-y-auto">
            <template x-if="logDetailLoading">
              <div class="flex flex-col items-center justify-center py-8 text-slate-500">
                <iconify-icon icon="mdi:loading" class="animate-spin mb-2" width="32"></iconify-icon>
                <p>Memuat detail log...</p>
              </div>
            </template>

            <template x-if="!logDetailLoading && logDetailData">
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p class="text-xs text-slate-500">Nama File</p>
                    <p class="font-medium text-slate-800" x-text="logDetailData.nama_file"></p>
                  </div>
                  <div>
                    <p class="text-xs text-slate-500">Status</p>
                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium"
                      :class="{
                          'bg-emerald-100 text-emerald-800': logDetailData.status === 'selesai',
                          'bg-rose-100 text-rose-800': logDetailData.status === 'gagal',
                          'bg-amber-100 text-amber-800': logDetailData.status === 'sebagian'
                      }"
                      x-text="logDetailData.status.charAt(0).toUpperCase() + logDetailData.status.slice(1)">
                    </span>
                  </div>
                  <div>
                    <p class="text-xs text-slate-500">Total Baris</p>
                    <p class="font-medium text-slate-800" x-text="logDetailData.total_baris"></p>
                  </div>
                  <div>
                    <p class="text-xs text-slate-500">Berhasil / Gagal</p>
                    <p class="font-medium text-slate-800">
                      <span class="text-emerald-600" x-text="logDetailData.baris_berhasil"></span> /
                      <span class="text-rose-600" x-text="logDetailData.baris_gagal"></span>
                    </p>
                  </div>
                </div>

                <template x-if="logDetailData.detail_error && logDetailData.detail_error.length > 2">
                  <div class="rounded-md bg-rose-50 border border-rose-200 p-3">
                    <p class="text-xs font-semibold text-rose-800 mb-2">Daftar Error:</p>
                    <ul class="space-y-1 max-h-40 overflow-y-auto text-xs text-rose-700">
                      <template x-for="(err, index) in logDetailData.detail_error[2]" :key="index">
                        <li class="flex gap-2">
                          <span class="font-mono font-bold shrink-0" x-text="err[0] + ':'"></span>
                          <span x-text="err[1]"></span>
                        </li>
                      </template>
                    </ul>
                  </div>
                </template>
              </div>
            </template>
          </div>

          <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" @click="openLogDetail = false"
              class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400">Tutup</button>
          </div>
        </div>
      </div>
    </template>

    {{-- ASVS 1.2.1: Render notification content as text, not HTML. --}}
    <template x-teleport="body">
      <div x-show="feedbackOpen" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-4 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="translate-x-4 opacity-0"
        class="pointer-events-none fixed right-4 top-24 z-[120] w-[calc(100%-2rem)] max-w-md sm:right-6"
        role="status" aria-live="polite" aria-atomic="true">
        <div
          class="pointer-events-auto flex items-start gap-3 rounded-xl border border-l-4 bg-white p-4 shadow-[0_16px_48px_rgba(2,25,41,0.18)]"
          :class="feedbackStatus === 'success' || feedbackStatus === 'selesai' ? 'border-emerald-200 border-l-emerald-500' : (
              feedbackStatus === 'sebagian' ? 'border-amber-200 border-l-amber-500' : 'border-rose-200 border-l-rose-500')">
          <iconify-icon
            :icon="feedbackStatus === 'success' || feedbackStatus === 'selesai' ? 'mdi:check-circle' : (
                feedbackStatus === 'sebagian' ? 'mdi:alert-circle' : 'mdi:close-circle')"
            :class="feedbackStatus === 'success' || feedbackStatus === 'selesai' ? 'text-emerald-600' : (
                feedbackStatus === 'sebagian' ? 'text-amber-600' : 'text-rose-600')"
            class="mt-0.5 shrink-0" width="22" aria-hidden="true"></iconify-icon>
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900" x-text="feedbackStatus === 'error' ? 'Gagal!' : (
                feedbackStatus === 'sebagian' ? 'Import selesai sebagian' : (
                    feedbackStatus === 'gagal' ? 'Import gagal' : 'Berhasil!'))"></p>
            <p x-show="!feedbackAccountName" class="mt-1 break-words text-sm text-slate-600" x-text="feedbackMessage"></p>
            <p x-show="feedbackAccountName" class="mt-1 break-words text-sm text-slate-600">
              <template x-if="feedbackAccountAction === 'dihapus'">
                <span>Akun <strong class="font-bold text-slate-800" x-text="feedbackAccountName"></strong>
                  berhasil dihapus.</span>
              </template>
              {{-- ASVS 1.2.1: x-text renders account names as text, not HTML. --}}
              <template x-if="feedbackAccountAction !== 'dihapus'">
                <span>Akun <strong class="font-bold text-slate-800" x-text="feedbackAccountName"></strong>
                  berhasil <span x-text="feedbackAccountAction"></span>.</span>
              </template>
            </p>
          </div>
          <button type="button" @click="dismissFeedback()"
            class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400"
            aria-label="Tutup notifikasi">
            <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
          </button>
        </div>
      </div>
    </template>

  </div>
</x-layout.admin>
