@props(['title' => 'InvenTrack'])

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00b19c">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>{{ $title }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
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

<body x-data="userPortal()" x-init="initCart(); initCatalogAjax(); initHistoryAjax(); @if (session('cart_cleared')) clearCart() @elseif (session('cart_items_submitted')) removeSubmittedItems(@js(session('cart_items_submitted'))) @endif" class="min-h-screen bg-[#e9faf5] font-sans text-[#153c36] antialiased">
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
      <form method="POST" action="{{ route('user.peminjaman.store') }}" class="p-5 sm:p-7">
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
          <p class="mt-1 text-xs text-slate-500"><span x-text="selectedLoanItem?.code"></span><span class="px-1">·</span><span x-text="selectedLoanItem?.category"></span></p>
          <p class="mt-2 text-xs font-medium text-[#007b6d]">Stok tersedia: <span x-text="selectedLoanItem?.stock"></span> <span x-text="selectedLoanItem?.unit"></span></p>
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
              <option value="lainnya">Lainnya</option>
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
          <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#00d69f] px-5 py-2.5 text-sm font-bold text-[#004d43] shadow-[0_5px_14px_rgba(0,190,144,0.2)] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2">
            <iconify-icon icon="mdi:check-circle-outline" class="text-lg" aria-hidden="true"></iconify-icon>
            Ajukan Peminjaman
          </button>
        </footer>
      </form>
    </section>
  </div>
  <div x-cloak x-show="notice" x-transition class="fixed bottom-5 left-1/2 z-[70] w-[min(92vw,36rem)] -translate-x-1/2 rounded-xl bg-[#003f38] px-4 py-3 text-sm text-white shadow-xl" role="status" x-text="notice"></div>
  <script>
    function userPortal() {
      return {
        cart: [],
        selectedCartItemIds: [],
        cartOpen: false,
        loanModalOpen: false,
        selectedLoanItem: null,
        loanQuantity: 1,
        notice: '',
        noticeTimer: null,
        cartStorageKey: 'inventrack-user-cart-v1',
        catalogSearchTimer: null,
        catalogRequestController: null,
        historyPollTimer: null,
        historyRequestController: null,
        applyUrl: @js(route('user.beranda', ['page' => 'apply'])),
        initHistoryAjax() {
          if (!document.getElementById('history-content') || this.historyPollTimer !== null) {
            return;
          }

          this.historyPollTimer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
              this.refreshHistory();
            }
          }, 5000);
        },
        async refreshHistory() {
          const currentHistory = document.getElementById('history-content');
          const url = new URL(window.location.href);
          if (
            !currentHistory
            || this.historyRequestController
            || url.origin !== window.location.origin
            || url.pathname !== window.location.pathname
            || url.searchParams.get('page') !== 'history'
          ) {
            return;
          }

          const controller = new AbortController();
          this.historyRequestController = controller;
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
              || responseUrl.pathname !== window.location.pathname
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
            if (loanStates(currentHistory) === loanStates(nextHistory)) {
              return;
            }

            const scrollPosition = window.scrollY;
            const horizontalScrollPosition = window.scrollX;
            currentHistory.replaceWith(nextHistory);
            window.Alpine.initTree(nextHistory);
            window.scrollTo(horizontalScrollPosition, scrollPosition);
          } catch (error) {
            if (error.name !== 'AbortError') {
              console.error('Failed to refresh borrower loan history.', error);
            }
          } finally {
            if (this.historyRequestController === controller) {
              this.historyRequestController = null;
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
            window.clearTimeout(this.catalogSearchTimer);
            this.loadCatalog(this.catalogUrl(form));
          });
          document.addEventListener('input', event => {
            if (event.target.name !== 'q') {
              return;
            }

            const form = event.target.closest('form[data-catalog-filter]');
            if (!form) {
              return;
            }

            window.clearTimeout(this.catalogSearchTimer);
            this.catalogSearchTimer = window.setTimeout(() => {
              this.loadCatalog(this.catalogUrl(form), true, true);
            }, 350);
          });
          window.addEventListener('popstate', () => {
            if (document.getElementById('catalog-content')) {
              if (new URL(window.location.href).searchParams.get('page') === 'catalog') {
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
          url.searchParams.set('page', 'catalog');
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
          if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.searchParams.get('page') !== 'catalog') {
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
            if (!response.ok || new URL(response.url).origin !== window.location.origin) {
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
            catalog.replaceWith(nextCatalog);
            window.Alpine.initTree(nextCatalog);
            const nextCategoryStrip = nextCatalog.querySelector('[x-ref="catalogCategoryStrip"]');
            if (nextCategoryStrip) {
              nextCategoryStrip.scrollLeft = categoryStripScrollPosition;
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
          this.showNotice(`${item.name} ditambahkan ke keranjang.`);
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
          this.notice = message;
          window.clearTimeout(this.noticeTimer);
          this.noticeTimer = window.setTimeout(() => this.notice = '', 3500);
        },
        goToApply() {
          if (this.selectedCartItems.length === 0) {
            this.showNotice('Pilih setidaknya satu barang untuk diajukan.');
            return;
          }
          window.location.assign(this.applyUrl);
        },
      };
    }
  </script>
</body>

</html>
