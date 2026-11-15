<div x-cloak x-show="cartOpen" x-transition.opacity.duration.200ms class="fixed inset-0 z-[100] flex justify-end" role="presentation" @keydown.escape.window="cartOpen = false">
  <button type="button" class="absolute inset-0 bg-slate-950/20 transition-colors duration-200" @click="cartOpen = false" aria-label="Tutup keranjang"></button>
  <aside x-show="cartOpen" x-transition:enter="transition ease-out duration-[240ms]" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="relative my-0.5 mr-0.5 flex h-[calc(100dvh-0.25rem)] w-full max-w-[33rem] flex-col rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="cart-title">
    <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
      <h2 id="cart-title" class="text-lg font-bold text-slate-900 sm:text-xl">Keranjang Peminjaman</h2>
      <button type="button" @click="cartOpen = false" class="rounded-md p-2 text-slate-500 transition hover:bg-slate-100" aria-label="Tutup"><iconify-icon icon="mdi:close" aria-hidden="true"></iconify-icon></button>
    </header>
    <div class="flex-1 overflow-y-auto px-5">
      <template x-if="cart.length === 0">
        <div class="flex min-h-full flex-col items-center justify-center gap-3 py-12 text-center">
          <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#e6f8f3] text-[#008b7a]" aria-hidden="true">
            <iconify-icon icon="mdi:cart-outline" class="text-3xl"></iconify-icon>
          </span>
          <div class="flex flex-col gap-1">
            <p class="text-base font-bold text-slate-800">Tidak ada barang di keranjang</p>
            <p class="text-sm text-slate-500">Jelajahi katalog untuk memilih barang yang ingin dipinjam.</p>
          </div>
          <a data-user-navigation-link href="{{ route('user.catalog') }}" @click="cartOpen = false"
            class="mt-1 inline-flex rounded-lg bg-[#00d69f] px-4 py-2.5 text-sm font-bold text-[#004d43] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008b7a]">
            Jelajahi Katalog
          </a>
        </div>
      </template>
      <template x-for="item in cart" :key="item.id">
        <article class="border-b border-slate-200 py-3.5 transition-colors duration-200" :class="isCartItemSelected(item.id) ? 'bg-emerald-50/50' : ''">
          <div class="flex items-start justify-between gap-3">
            <label class="flex min-w-0 flex-1 items-start gap-2.5">
              <input type="checkbox" :checked="isCartItemSelected(item.id)" @change="toggleCartItem(item.id, $event.target.checked)" :disabled="item.stock === 0" :aria-label="`Pilih ${item.name} untuk diajukan`" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-[#008f7a] focus:ring-[#008f7a] disabled:cursor-not-allowed disabled:opacity-40">
              <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-slate-900" x-text="item.name"></span>
                <span class="mt-1 block text-xs text-slate-500" x-text="item.category"></span>
                <span x-show="item.stock === 0" class="mt-1 block text-xs font-semibold text-rose-600">Stok tidak tersedia</span>
              </span>
            </label>
            <button type="button" @click="removeFromCart(item.id)" class="inline-flex shrink-0 items-center gap-1 rounded-md bg-rose-600 px-2 py-1 text-[10px] font-semibold text-white transition hover:bg-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">
              <iconify-icon icon="mdi:trash-can-outline" aria-hidden="true"></iconify-icon>
              Hapus
            </button>
          </div>
          <div class="mt-2 flex items-center gap-1.5">
            <button type="button" @click="updateQuantity(item, item.quantity - 1)" :disabled="item.stock === 0" class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Kurangi jumlah">−</button>
            <input type="number" min="1" :max="item.stock" :value="item.quantity" @change="updateQuantity(item, $event.target.value)" :disabled="item.stock === 0" class="h-7 w-12 rounded border border-slate-200 px-1 text-center text-xs disabled:bg-slate-100">
            <button type="button" @click="updateQuantity(item, item.quantity + 1)" :disabled="item.quantity >= item.stock" class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 disabled:opacity-40" aria-label="Tambah jumlah">+</button>
          </div>
        </article>
      </template>
    </div>
    <footer class="border-t border-slate-200 px-5 py-4">
      <div class="mb-3 flex justify-between text-base font-bold text-slate-900"><span>Barang dipilih</span><span x-text="`${selectedCartItems.length} item`"></span></div>
      <button type="button" @click="goToApply()"
        :disabled="selectedCartItems.length === 0 || isOpeningApply"
        :aria-busy="isOpeningApply"
        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#00d69f] px-4 py-3 text-sm font-bold text-[#004d43] transition hover:bg-[#36e9b3] disabled:cursor-wait disabled:opacity-60">
        <iconify-icon x-show="!isOpeningApply" icon="mdi:send-check-outline" aria-hidden="true"></iconify-icon>
        <iconify-icon x-show="isOpeningApply" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
        <span x-text="isOpeningApply ? 'Membuka formulir...' : 'Ajukan Peminjaman'"></span>
      </button>
    </footer>
  </aside>
</div>
