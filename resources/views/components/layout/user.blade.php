@props(['title' => 'InvenTrack'])

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00b19c">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>{{ $title }}</title>
  @fonts('poppins')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    [x-cloak] {
      display: none !important;
    }

    @view-transition {
      navigation: none;
    }

    ::view-transition {
      background-color: #e9faf5;
    }

    ::view-transition-old(root),
    ::view-transition-new(root) {
      animation: none;
    }

    .user-navbar {
      view-transition-name: user-navbar;
    }

    ::view-transition-group(user-navbar) {
      animation: none;
    }

    ::view-transition-old(user-navbar) {
      visibility: hidden;
    }

    ::view-transition-new(user-navbar) {
      animation: none;
      mix-blend-mode: normal;
    }

    html {
      scroll-padding-top: 5rem;
    }

    @keyframes user-card-enter {
      from {
        opacity: 0;
        transform: translateY(18px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes category-scroll {
      from {
        transform: translateX(0);
      }

      to {
        transform: translateX(-50%);
      }
    }

    .user-card-enter {
      animation: user-card-enter 180ms both cubic-bezier(0.2, 0.7, 0.2, 1);
      transition: box-shadow 220ms ease, filter 220ms ease;
    }

    .user-card-enter:hover {
      filter: saturate(1.04);
      box-shadow: 0 12px 28px rgb(20 55 48 / 13%);
    }

    .category-track {
      display: flex;
      width: max-content;
      animation: category-scroll 36s linear infinite;
    }

    .category-marquee:hover .category-track,
    .category-marquee:focus-within .category-track {
      animation-play-state: paused;
    }

    .category-marquee {
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
    }

    .category-marquee::-webkit-scrollbar {
      display: none;
    }

    @media (max-width: 639px) {
      .category-marquee:active .category-track {
        animation-play-state: paused;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }
  </style>
</head>

<body x-data="userPortal()" x-init="initCart(); initCatalogAjax(); initHistoryAjax(); @if(session('success')) window.showToast(@js(session('success')), 'success'); @elseif(session('error')) window.showToast(@js(session('error')), 'error'); @endif @foreach($errors->all() as $error) window.showToast(@js($error), 'error'); @endforeach @if (session('cart_cleared')) clearCart() @elseif (session('cart_items_submitted')) removeSubmittedItems(@js(session('cart_items_submitted'))) @endif" class="min-h-screen bg-[#e9faf5] font-sans text-[#153c36] antialiased">
  <x-auth.session-notice />
  <div x-data="globalToast" x-cloak x-show="visible"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-x-4 opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="translate-x-4 opacity-0"
    class="pointer-events-none fixed right-4 top-24 z-[120] w-[calc(100%-2rem)] max-w-md sm:right-6"
    :role="type === 'error' ? 'alert' : 'status'"
    :aria-live="type === 'error' ? 'assertive' : 'polite'" aria-atomic="true">
    <div class="pointer-events-auto relative flex items-start gap-3 overflow-hidden rounded-xl border p-4 pb-5 text-white shadow-[0_16px_48px_rgba(2,25,41,0.24)]"
      :class="{
        'border-rose-700 bg-rose-600': type === 'error',
        'border-emerald-700 bg-emerald-600': type === 'success',
        'border-sky-700 bg-sky-600': type === 'info',
        'border-amber-700 bg-amber-500': type === 'warning'
      }">
      <iconify-icon
        :icon="type === 'error' ? 'mdi:close-circle' : (type === 'warning' ? 'mdi:alert-circle' : (type === 'info' ? 'mdi:information' : 'mdi:check-circle'))"
        class="mt-0.5 shrink-0 text-white"
        width="22" aria-hidden="true"></iconify-icon>
      <div class="min-w-0 flex-1">
        <p class="font-semibold" x-text="title"></p>
        <p class="mt-1 break-words text-sm text-white/90">
          <template x-for="(part, index) in message" :key="index">
            <span :class="part.bold ? 'font-bold text-white' : ''" x-text="part.text"></span>
          </template>
        </p>
        <button x-cloak x-show="actionLabel && onAction" type="button" @click="activateAction()"
          class="mt-3 inline-flex items-center gap-1 rounded-md bg-white/15 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-white/25 focus:outline-none focus:ring-2 focus:ring-white/80"
          x-text="actionLabel"></button>
      </div>
      <button type="button" @click="dismiss()"
        class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-white/80 transition hover:bg-white/15 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/80"
        aria-label="Tutup notifikasi">
        <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
      </button>
      <span x-ref="progress" class="absolute inset-x-0 bottom-0 h-1 origin-left bg-white/70"
        aria-hidden="true"></span>
    </div>
  </div>
  <x-user.navbar />
  {{ $slot }}
  <x-user.keranjang />
  <div
    x-cloak
    x-show="loanModalOpen"
    x-transition.opacity
    class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-slate-950/55 p-4"
    role="presentation"
    @click.self="loanModalOpen = false"
    @keydown.escape.window="loanModalOpen = false"
  >
    <section
      x-show="loanModalOpen"
      x-transition
      class="relative my-auto w-full max-w-xl overflow-hidden rounded-2xl border border-[#00c99c] bg-white shadow-[0_28px_90px_rgba(0,30,26,0.35)]"
      role="dialog"
      aria-modal="true"
      aria-labelledby="quick-loan-title"
    >
      <div class="h-1.5 bg-gradient-to-r from-[#007b6d] via-[#00b99a] to-[#00e3a8]" aria-hidden="true"></div>
      <form method="POST" action="{{ route('user.peminjaman.store', absolute: false) }}" class="p-5 sm:p-7"
        @submit.prevent="submitLoanForm($event)"
        :aria-busy="loanSubmitting">
        @csrf
        <input type="hidden" name="items[0][barang_id]" :value="selectedLoanItem?.id">
        <input type="hidden" name="items[0][quantity]" :value="loanQuantity">
        <input type="hidden" name="preserve_cart" value="1">

        <header class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
          <div>
            <p class="text-xs font-semibold text-[#008b7a]">Pengajuan barang</p>
            <h2 id="quick-loan-title" class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Pinjam sekarang</h2>
          </div>
          <button type="button" @click="loanModalOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a]" aria-label="Tutup formulir pengajuan">
            <iconify-icon icon="mdi:close" class="text-xl" aria-hidden="true"></iconify-icon>
          </button>
        </header>

        <div class="mt-4 rounded-xl border border-slate-200 bg-[#f7fbfa] px-4 py-3">
          <p class="text-sm font-semibold text-slate-900" x-text="selectedLoanItem?.name"></p>
          <p class="mt-1 text-xs text-slate-500" x-text="selectedLoanItem?.category"></p>
          <p class="mt-2 text-sm font-semibold text-[#007b6d]">Stok tersedia: <span x-text="selectedLoanItem?.stock"></span></p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="block text-xs font-semibold text-slate-700">
            Jumlah
            <input
              type="number"
              min="1"
              :max="selectedLoanItem?.stock"
              x-model.number="loanQuantity"
              required
              class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20"
            >
          </label>
          <label class="block text-xs font-semibold text-slate-700">
            Tanggal rencana kembali
            <input
              type="date"
              name="tanggal_rencana_kembali"
              min="{{ now()->toDateString() }}"
              value="{{ now()->toDateString() }}"
              required
              class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2.5 text-sm font-normal text-slate-700 outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20"
            >
          </label>
          <label class="block text-xs font-semibold text-slate-700">
            Jenis peminjaman
            <select
              name="jenis_peminjaman"
              required
              class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20"
            >
              <option value="harian">Harian</option>
              <option value="praktik">Praktik</option>
              <option value="mingguan">Mingguan</option>
              <option value="bulanan">Bulanan</option>
              <option value="semesteran">Semesteran</option>
              <option value="tahunan">Tahunan</option>
            </select>
          </label>
        </div>

        <label class="mt-4 block text-xs font-semibold text-slate-700">
          Keperluan
          <textarea
            name="keperluan"
            maxlength="255"
            required
            rows="2"
            placeholder="Jelaskan keperluan peminjaman"
            class="mt-1.5 block w-full resize-y rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20"
          ></textarea>
        </label>
        <footer class="mt-5 flex flex-col-reverse justify-end gap-2 sm:flex-row">
          <button type="button" @click="loanModalOpen = false" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Batal</button>
          <button type="submit" :disabled="loanSubmitting || !selectedLoanItem?.stock"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#00d69f] px-5 py-2.5 text-sm font-bold text-[#004d43] shadow-[0_5px_14px_rgba(0,190,144,0.2)] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70">
            <iconify-icon x-show="!loanSubmitting" icon="mdi:check-circle-outline" class="text-lg" aria-hidden="true"></iconify-icon>
            <iconify-icon x-show="loanSubmitting" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
            <span x-text="loanSubmitting ? 'Mengirim pengajuan...' : 'Ajukan Peminjaman'"></span>
          </button>
        </footer>
      </form>
    </section>
  </div>
  <script>
    function userPortal() {
      return {
        cart: [],
        selectedCartItemIds: [],
        cartOpen: false,
        loanModalOpen: false,
        selectedLoanItem: null,
        loanQuantity: 1,
        loanSubmitting: false,
        isOpeningApply: false,
        notice: '',
        noticeTimer: null,
        cartStorageKey: 'inventrack-user-cart-v1',
        catalogRequestController: null,
        historyPollTimer: null,
        historyRequestController: null,
        applyUrl: @js(route('user.apply')),
        catalogPath: @js(parse_url(route('user.catalog'), PHP_URL_PATH)),
        historyPath: @js(parse_url(route('user.history'), PHP_URL_PATH)),
        async cancelLoan(event) {
          const form = event.currentTarget;
          const submitButton = form.querySelector('[type="submit"]');

          if (submitButton) {
            submitButton.disabled = true;
          }

          try {
            const response = await fetch(form.action, {
              method: 'POST',
              body: new FormData(form),
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
              },
              credentials: 'same-origin',
              cache: 'no-store',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
              const validationMessage = Object.values(payload.errors || {})
                .flatMap(messages => Array.isArray(messages) ? messages : [])
                .find(message => typeof message === 'string');
              window.showToast(validationMessage || payload.message || 'Peminjaman gagal dibatalkan.', 'error');
              return;
            }

            const card = form.closest('[data-loan-state]');
            if (card) {
              card.classList.add('opacity-0', 'scale-[0.99]', 'transition', 'duration-300');
              window.setTimeout(() => card.remove(), 280);
            }
            window.showToast(payload.message || 'Peminjaman berhasil dibatalkan.', 'success');
          } catch (error) {
            console.error('Failed to cancel borrower loan.', error);
            window.showToast('Peminjaman gagal dibatalkan. Periksa koneksi lalu coba lagi.', 'error');
          } finally {
            if (submitButton) {
              submitButton.disabled = false;
            }
          }
        },
        async submitLoanForm(event) {
          if (this.loanSubmitting) {
            return;
          }

          const form = event.currentTarget;
          const formUrl = new URL(form.action, window.location.href);
          if (formUrl.origin !== window.location.origin) {
            this.showNotice('Alamat pengajuan tidak valid. Muat ulang halaman lalu coba lagi.');
            return;
          }

          this.loanSubmitting = true;
          let redirecting = false;
          const submittedItems = this.selectedCartItems;
          try {
            // ASVS V2.2.2: AJAX improves feedback; server-side validation remains authoritative.
            const response = await fetch(formUrl, {
              method: 'POST',
              body: new FormData(form),
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
              },
              credentials: 'same-origin',
              cache: 'no-store',
            });
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
              throw new Error(`Loan request returned an unexpected content type (${response.status}).`);
            }

            const payload = await response.json();
            if (response.status === 409 && payload.stock && typeof payload.stock === 'object' && !Array.isArray(payload.stock)) {
              this.syncCartStock(payload.stock);
              this.showNotice(payload.message || 'Stok barang berubah. Periksa jumlah dan kirim ulang pengajuan.');
              return;
            }

            if (!response.ok) {
              const invalidItemIndexes = Object.keys(payload.errors || {})
                .flatMap(field => {
                  const match = field.match(/^items\.(\d+)\.barang_id$/);
                  const messages = payload.errors[field];
                  const isInvalidId = Array.isArray(messages) && messages.some(message => [
                    'ID barang tidak valid. Muat ulang katalog dan pilih kembali barang tersebut.',
                    'Barang yang dipilih sudah tidak tersedia di katalog. Hapus lalu pilih kembali dari katalog.',
                  ].includes(message));
                  return match && isInvalidId ? [Number(match[1])] : [];
                });
              const invalidItems = [...new Map(invalidItemIndexes
                .map(index => submittedItems[index])
                .filter(item => item && typeof item.id === 'string')
                .map(item => [item.id, item])).values()];

              if (invalidItems.length > 0) {
                invalidItems.forEach(item => this.removeFromCart(item.id));
                const itemNames = invalidItems.map(item => item.name).join(', ');
                this.showNotice(`${itemNames} sudah tidak tercatat di katalog dan dihapus dari pengajuan. Jika stok masih ada, pilih kembali barangnya dari katalog.`);
                return;
              }

              const validationMessage = Object.values(payload.errors || {})
                .flatMap(messages => Array.isArray(messages) ? messages : [])
                .find(message => typeof message === 'string');
              this.showNotice(validationMessage || payload.message || 'Pengajuan gagal diproses. Periksa kembali data Anda.');
              return;
            }

            if (typeof payload.redirect_url !== 'string' || !Array.isArray(payload.submitted_item_ids)) {
              throw new Error('Loan response did not contain the expected success data.');
            }

            const destination = new URL(payload.redirect_url, window.location.href);
            // ASVS V3.7.2: only follow a server-provided redirect on the current origin.
            if (destination.origin !== window.location.origin) {
              throw new Error('Loan response contained a cross-origin redirect.');
            }

            this.removeSubmittedItems(payload.submitted_item_ids);
            window.location.assign(destination.href);
            redirecting = true;
          } catch (error) {
            console.error('Failed to submit borrower loan.', error);
            this.showNotice('Pengajuan gagal dikirim. Periksa koneksi lalu coba lagi.');
          } finally {
            if (!redirecting) {
              this.loanSubmitting = false;
            }
          }
        },
        syncCartStock(stockById) {
          const syncedCart = [];
          for (const item of this.cart) {
            const currentStock = stockById[item.id];
            if (!Number.isInteger(currentStock) || currentStock < 0) {
              syncedCart.push(item);
              continue;
            }
            if (currentStock === 0) {
              continue;
            }

            item.stock = currentStock;
            item.quantity = Math.min(item.quantity, currentStock);
            syncedCart.push(item);
          }

          this.cart = syncedCart;
          this.selectedCartItemIds = this.selectedCartItemIds.filter(itemId => this.cart.some(item => item.id === itemId));
          this.persistCart();
          this.persistCartSelection();

          if (this.selectedLoanItem && Number.isInteger(stockById[this.selectedLoanItem.id])) {
            this.selectedLoanItem.stock = stockById[this.selectedLoanItem.id];
            this.loanQuantity = this.selectedLoanItem.stock > 0
              ? Math.min(this.loanQuantity, this.selectedLoanItem.stock)
              : 1;
          }
        },
        initHistoryAjax() {
          if (!document.getElementById('history-content') || this.historyPollTimer !== null) {
            return;
          }

          document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            const history = document.getElementById('history-content');
            if (
              !link
              || !history?.contains(link)
              || (!link.hasAttribute('data-history-ajax') && !link.closest('nav[role="navigation"]'))
              || event.button !== 0
              || event.metaKey
              || event.ctrlKey
              || event.shiftKey
              || event.altKey
              || link.target
            ) {
              return;
            }

            const destination = new URL(link.href, window.location.href);
            if (
              destination.origin !== window.location.origin
              || destination.pathname !== this.historyPath
            ) {
              return;
            }

            event.preventDefault();
            this.loadHistory(destination, { updateHistory: true, animate: true });
          });
          window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);
            if (
              document.getElementById('history-content')
              && url.origin === window.location.origin
              && url.pathname === this.historyPath
            ) {
              this.loadHistory(url, { animate: true, abortPrevious: true });
            }
          });

          this.historyPollTimer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
              this.refreshHistory();
            }
          }, 30000);
        },
        async refreshHistory() {
          const url = new URL(window.location.href);
          if (
            !document.getElementById('history-content')
            || this.historyRequestController
            || url.origin !== window.location.origin
            || url.pathname !== this.historyPath
          ) {
            return;
          }

          await this.loadHistory(url, { onlyIfChanged: true });
        },
        async loadHistory(destination, { updateHistory = false, animate = false, onlyIfChanged = false, abortPrevious = false } = {}) {
          const currentHistory = document.getElementById('history-content');
          const url = new URL(destination, window.location.href);
          if (
            !currentHistory
            || url.origin !== window.location.origin
            || url.pathname !== this.historyPath
          ) {
            return;
          }

          if (this.historyRequestController) {
            if (!updateHistory && !abortPrevious) {
              return;
            }
            this.historyRequestController.abort();
          }

          const controller = new AbortController();
          this.historyRequestController = controller;
          currentHistory.setAttribute('aria-busy', 'true');
          try {
            // ASVS V8.2.2: refresh the authenticated borrower page so its owner-scoped query remains authoritative.
            const response = await fetch(url, {
              headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
              credentials: 'same-origin',
              cache: 'no-store',
              signal: controller.signal,
            });
            const responseUrl = new URL(response.url);
            if (
              !response.ok
              || responseUrl.origin !== window.location.origin
              || responseUrl.pathname !== this.historyPath
              || !response.headers.get('content-type')?.includes('text/html')
            ) {
              throw new Error(`History refresh failed with status ${response.status}.`);
            }

            const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextHistory = responseDocument.querySelector('#history-content');
            if (!nextHistory) {
              throw new Error('History response did not contain the expected content.');
            }

            const loanStates = history => Array.from(history.querySelectorAll('[data-loan-state]'), loan => loan.dataset.loanState).join('|');
            if (onlyIfChanged && loanStates(currentHistory) === loanStates(nextHistory)) {
              return;
            }

            const scrollPosition = window.scrollY;
            const horizontalScrollPosition = window.scrollX;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (animate && !reduceMotion) {
              const exitAnimation = currentHistory.animate(
                [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0.82, transform: 'translateY(4px)' }],
                { duration: 100, easing: 'ease-in', fill: 'forwards' },
              );
              await exitAnimation.finished;
              exitAnimation.cancel();
              if (controller.signal.aborted) {
                return;
              }
            }

            currentHistory.replaceWith(nextHistory);
            window.Alpine.initTree(nextHistory);
            window.scrollTo(horizontalScrollPosition, scrollPosition);
            if (updateHistory) {
              window.history.pushState({ history: true }, '', url);
            }

            if (animate && !reduceMotion) {
              const entranceAnimation = nextHistory.animate(
                [{ opacity: 0.82, transform: 'translateY(4px)' }, { opacity: 1, transform: 'translateY(0)' }],
                { duration: 180, easing: 'cubic-bezier(0.2, 0.7, 0.2, 1)' },
              );
              await entranceAnimation.finished;
              entranceAnimation.cancel();
            }
          } catch (error) {
            if (error.name !== 'AbortError') {
              console.error('Failed to load borrower loan history.', error);
              if (updateHistory) {
                this.showNotice('Riwayat gagal dimuat. Periksa koneksi lalu coba lagi.');
              }
            }
          } finally {
            if (this.historyRequestController === controller) {
              this.historyRequestController = null;
              document.getElementById('history-content')?.removeAttribute('aria-busy');
            }
          }
        },
        initCatalogAjax() {
          document.addEventListener('click', event => {
            const link = event.target.closest('a[data-catalog-ajax]');
            if (!link || !document.getElementById('catalog-content')?.contains(link)) {
              return;
            }

            event.preventDefault();
            this.loadCatalog(link.href);
          });
          document.addEventListener('submit', event => {
            const form = event.target.closest('form[data-catalog-filter]');
            if (!form) {
              return;
            }

            event.preventDefault();
            this.loadCatalog(this.catalogUrl(form));
          });
          window.addEventListener('popstate', () => {
            if (document.getElementById('catalog-content')) {
              if (new URL(window.location.href).pathname === this.catalogPath) {
                this.loadCatalog(window.location.href, false);
              } else {
                window.location.reload();
              }
            }
          });
        },
        catalogUrl(form) {
          const url = new URL(form.action, window.location.href);
          for (const [key, value] of new FormData(form).entries()) {
            if (value !== '') {
              url.searchParams.set(key, value);
            } else {
              url.searchParams.delete(key);
            }
          }
          url.searchParams.delete('page');
          url.searchParams.delete('catalog_page');
          return url;
        },
        async loadCatalog(destination, updateHistory = true, replaceHistory = false) {
          const url = new URL(destination, window.location.href);
          const catalog = document.getElementById('catalog-content');
          if (!catalog) {
            return;
          }

          // ASVS 1.2.2: only request same-origin catalog URLs.
          if (url.origin !== window.location.origin || url.pathname !== this.catalogPath) {
            this.showNotice('Alamat katalog tidak valid. Muat ulang halaman dan coba lagi.');
            return;
          }

          this.catalogRequestController?.abort();
          const controller = new AbortController();
          this.catalogRequestController = controller;
          catalog.setAttribute('aria-busy', 'true');
          try {
            const response = await fetch(url, {
              headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
              credentials: 'same-origin',
              signal: controller.signal,
            });
            const responseUrl = new URL(response.url);
            if (!response.ok || responseUrl.origin !== window.location.origin || responseUrl.pathname !== this.catalogPath) {
              throw new Error(`Catalog request failed with status ${response.status}.`);
            }

            const documentContent = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextCatalog = documentContent.querySelector('#catalog-content');
            if (!nextCatalog) {
              throw new Error('Catalog response did not contain the expected content.');
            }

            const activeSearch = document.activeElement?.matches('input[name="q"]') === true;
            const searchSelection = activeSearch
              ? [document.activeElement.selectionStart, document.activeElement.selectionEnd]
              : null;
            const scrollPosition = window.scrollY;
            const horizontalScrollPosition = window.scrollX;
            const categoryStripScrollPosition = catalog.querySelector('[x-ref="catalogCategoryStrip"]')?.scrollLeft ?? 0;
            const replaceCatalog = () => {
              catalog.replaceWith(nextCatalog);
              window.Alpine.initTree(nextCatalog);
              window.dispatchEvent(new CustomEvent('catalog:updated', { detail: { root: nextCatalog } }));
              const nextCategoryStrip = nextCatalog.querySelector('[x-ref="catalogCategoryStrip"]');
              if (nextCategoryStrip) {
                nextCategoryStrip.scrollLeft = categoryStripScrollPosition;
              }
            };
            const supportsViewTransition = typeof document.startViewTransition === 'function'
              && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (supportsViewTransition) {
              await document.startViewTransition(replaceCatalog).updateCallbackDone;
            } else {
              replaceCatalog();
            }
            if (updateHistory) {
              const method = replaceHistory ? 'replaceState' : 'pushState';
              window.history[method]({ catalog: true }, '', url);
            }
            if (activeSearch) {
              const nextSearch = nextCatalog.querySelector('input[name="q"]');
              nextSearch?.focus({ preventScroll: true });
              if (nextSearch && searchSelection[0] !== null && searchSelection[1] !== null) {
                nextSearch.setSelectionRange(searchSelection[0], searchSelection[1]);
              }
            }
            window.scrollTo(horizontalScrollPosition, scrollPosition);
          } catch (error) {
            if (error.name !== 'AbortError') {
              console.error('Failed to update the catalog.', error);
              this.showNotice('Katalog gagal diperbarui. Periksa koneksi lalu coba lagi.');
            }
          } finally {
            if (this.catalogRequestController === controller) {
              this.catalogRequestController = null;
              document.getElementById('catalog-content')?.removeAttribute('aria-busy');
            }
          }
        },
        initCart() {
          try {
            const savedCart = JSON.parse(localStorage.getItem(this.cartStorageKey) || '[]');
            this.cart = Array.isArray(savedCart) ? savedCart.filter(item => item && typeof item.id === 'string' && Number.isInteger(item.quantity) && item.quantity > 0) : [];
          } catch {
            this.cart = [];
          }
          try {
            const savedSelection = JSON.parse(sessionStorage.getItem(`${this.cartStorageKey}-selection`) || 'null');
            this.selectedCartItemIds = Array.isArray(savedSelection)
              ? savedSelection.filter(id => typeof id === 'string' && this.cart.some(item => item.id === id))
              : this.cart.map(item => item.id);
          } catch {
            this.selectedCartItemIds = this.cart.map(item => item.id);
          }
        },
        persistCart() {
          localStorage.setItem(this.cartStorageKey, JSON.stringify(this.cart));
        },
        addToCart(item) {
          const existingItem = this.cart.find(cartItem => cartItem.id === item.id);
          if (existingItem) {
            if (existingItem.quantity >= item.stock) {
              this.showNotice('Jumlah di keranjang sudah mencapai stok yang tersedia.');
              return;
            }
            existingItem.quantity += 1;
          } else {
            this.cart.push({ ...item, quantity: 1 });
          }
          if (!this.selectedCartItemIds.includes(item.id)) {
            this.selectedCartItemIds.push(item.id);
            this.persistCartSelection();
          }
          this.persistCart();
          window.showToast(`${item.name} ditambahkan ke keranjang.`, 'success', 2500, 'Ditambahkan ke keranjang');
        },
        openLoanModal(item) {
          this.selectedLoanItem = item;
          this.loanQuantity = 1;
          this.loanModalOpen = true;
        },
        updateQuantity(item, quantity) {
          const nextQuantity = Number.parseInt(quantity, 10);
          if (!Number.isInteger(nextQuantity) || nextQuantity < 1) {
            this.removeFromCart(item.id);
            return;
          }
          item.quantity = Math.min(nextQuantity, item.stock);
          this.persistCart();
        },
        removeFromCart(itemId) {
          this.cart = this.cart.filter(item => item.id !== itemId);
          this.selectedCartItemIds = this.selectedCartItemIds.filter(id => id !== itemId);
          this.persistCart();
          this.persistCartSelection();
        },
        toggleCartItem(itemId, selected) {
          this.selectedCartItemIds = selected
            ? [...new Set([...this.selectedCartItemIds, itemId])]
            : this.selectedCartItemIds.filter(id => id !== itemId);
          this.persistCartSelection();
        },
        isCartItemSelected(itemId) {
          return this.selectedCartItemIds.includes(itemId);
        },
        persistCartSelection() {
          // ASVS V14.3.3: session storage contains only selected inventory IDs, not user or loan data.
          sessionStorage.setItem(`${this.cartStorageKey}-selection`, JSON.stringify(this.selectedCartItemIds));
        },
        removeSubmittedItems(itemIds) {
          const submittedIds = Array.isArray(itemIds) ? itemIds.filter(id => typeof id === 'string') : [];
          this.cart = this.cart.filter(item => !submittedIds.includes(item.id));
          this.selectedCartItemIds = this.selectedCartItemIds.filter(id => !submittedIds.includes(id));
          this.persistCart();
          this.persistCartSelection();
        },
        clearCart() {
          this.cart = [];
          this.selectedCartItemIds = [];
          localStorage.removeItem(this.cartStorageKey);
          sessionStorage.removeItem(`${this.cartStorageKey}-selection`);
        },
        get itemCount() {
          return this.cart.length;
        },
        get selectedCartItems() {
          return this.cart.filter(item => this.selectedCartItemIds.includes(item.id));
        },
        showNotice(message) {
          window.showToast(message, 'error');
        },
        goToApply() {
          if (this.isOpeningApply) {
            return;
          }
          if (this.selectedCartItems.length === 0) {
            this.showNotice('Pilih setidaknya satu barang untuk diajukan.');
            return;
          }
          this.isOpeningApply = true;
          window.location.assign(this.applyUrl);
        },
      };
    }
  </script>
</body>

</html>
