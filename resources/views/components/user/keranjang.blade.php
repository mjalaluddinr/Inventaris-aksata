<div x-cloak x-show="cartOpen" x-transition.opacity class="fixed inset-0 z-[60] flex justify-end" role="dialog" aria-modal="true" aria-labelledby="cart-title" @keydown.escape.window="cartOpen = false">
  <button type="button" class="absolute inset-0 bg-slate-950/45" @click="cartOpen = false" aria-label="Tutup keranjang"></button>
  <aside x-show="cartOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="relative flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
    <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
      <h2 id="cart-title" class="text-lg font-bold text-slate-900">Keranjang Peminjaman</h2>
      <button type="button" @click="cartOpen = false" class="rounded-md p-2 text-slate-500 transition hover:bg-slate-100" aria-label="Tutup"><iconify-icon icon="mdi:close" aria-hidden="true"></iconify-icon></button>
    </header>
    <div class="flex-1 overflow-y-auto px-5">
      <template x-if="cart.length === 0">
        <div class="py-12 text-center text-sm text-slate-500">Keranjang masih kosong.<br><a href="{{ route('user.beranda', ['page' => 'catalog']) }}" @click="cartOpen = false" class="mt-3 inline-block font-semibold text-[#008f82] underline">Jelajahi katalog</a></div>
      </template>
      <template x-for="item in cart" :key="item.id">
        <article class="border-b border-slate-100 py-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0"><h3 class="truncate text-sm font-semibold text-slate-900" x-text="item.name"></h3><p class="mt-1 text-xs text-slate-500"><span x-text="item.code"></span> · <span x-text="item.category"></span></p></div>
            <button type="button" @click="removeFromCart(item.id)" class="rounded px-2 py-1 text-xs text-rose-600 hover:bg-rose-50">Hapus</button>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <button type="button" @click="updateQuantity(item, item.quantity - 1)" class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700" aria-label="Kurangi jumlah">−</button>
            <input type="number" min="1" :max="item.stock" :value="item.quantity" @change="updateQuantity(item, $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-center text-xs">
            <button type="button" @click="updateQuantity(item, item.quantity + 1)" :disabled="item.quantity >= item.stock" class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 disabled:opacity-40" aria-label="Tambah jumlah">+</button>
            <span class="ml-auto text-xs text-slate-500">Stok tersedia: <span x-text="item.stock"></span></span>
          </div>
        </article>
      </template>
    </div>
    <footer class="border-t border-slate-200 px-5 py-4">
      <div class="mb-3 flex justify-between text-sm font-semibold text-slate-800"><span>Total barang</span><span x-text="`${cart.length} item`"></span></div>
      <button type="button" @click="goToApply()" :disabled="cart.length === 0" class="w-full rounded-lg bg-[#00d69f] px-4 py-3 text-sm font-bold text-[#004d43] transition hover:bg-[#36e9b3] disabled:cursor-not-allowed disabled:opacity-50">Ajukan Peminjaman</button>
    </footer>
  </aside>
</div>
