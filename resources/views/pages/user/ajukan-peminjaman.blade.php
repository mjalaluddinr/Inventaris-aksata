<main class="mx-auto min-h-[calc(100vh-5rem)] max-w-[1600px] pb-12">
  <x-user.hero-section title="Ajukan Peminjaman"
    description="Periksa barang di keranjang, lalu lengkapi detail pengajuan." />
  <section class="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-8 sm:py-6 lg:grid-cols-[1.15fr_0.85fr]">
    <div
      class="h-fit rounded-xl border border-[#d1e6e1] bg-white p-4 shadow-[0_2px_5px_rgba(20,55,48,0.12)] sm:p-5">
      <h2 class="border-b border-slate-200 pb-3 text-base font-bold text-slate-900">Barang yang dipinjam <span
          class="font-normal text-slate-500" x-text="`(${selectedCartItems.length} jenis)`"></span></h2>
      <div x-show="selectedCartItems.length === 0" class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-500">
        Pilih barang dari keranjang untuk diajukan.</div>
      <template x-for="item in selectedCartItems" :key="item.id">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 py-3">
          <div class="min-w-0 flex-1">
            <h3 class="truncate text-sm font-semibold text-slate-800" x-text="item.name"></h3>
            <p class="mt-0.5 text-[10px] text-slate-500"><span x-text="item.category"></span> · stok <span
                x-text="item.stock"></span></p>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" @click="updateQuantity(item, item.quantity - 1)"
              class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 transition hover:bg-slate-50"
              aria-label="Kurangi jumlah">−</button>
            <input type="number" min="1" :max="item.stock" :value="item.quantity"
              @change="updateQuantity(item, $event.target.value)" :aria-label="`Jumlah ${item.name}`"
              class="h-7 w-12 rounded border border-slate-200 px-1 text-center text-xs">
            <button type="button" @click="updateQuantity(item, item.quantity + 1)"
              :disabled="item.quantity >= item.stock"
              class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Tambah jumlah">+</button>
            <button type="button" @click="removeFromCart(item.id)"
              class="inline-flex items-center gap-1 rounded-md bg-rose-600 px-2 py-1.5 text-[10px] font-semibold text-white transition hover:bg-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">
              <iconify-icon icon="mdi:trash-can-outline" aria-hidden="true"></iconify-icon>
              Hapus
            </button>
          </div>
        </div>
      </template>
      <a href="{{ route('user.catalog') }}"
        class="mt-3 inline-flex rounded-full border border-[#00a88f] px-3 py-1.5 text-[10px] font-semibold text-[#007f70] transition hover:bg-emerald-50">+
        Tambah barang lain</a>
    </div>
    <form method="POST" action="{{ route('user.peminjaman.store', absolute: false) }}" data-cart-submission
      class="rounded-xl border border-[#d1e6e1] bg-white p-4 shadow-[0_2px_5px_rgba(20,55,48,0.12)] sm:p-5"
      @submit.prevent="submitLoanForm($event)"
      :aria-busy="loanSubmitting">
      @csrf
      <input type="hidden" name="preserve_cart" value="1">
      <input type="hidden" name="clear_submitted_items" value="1">
      <h2 class="mb-4 border-b border-slate-200 pb-3 text-base font-bold text-slate-900">Ringkasan Pengajuan</h2>
      <template x-for="(item, index) in selectedCartItems" :key="item.id">
        <span>
          <input type="hidden" :name="`items[${index}][barang_id]`" :value="item.id">
          <input type="hidden" :name="`items[${index}][quantity]`" :value="item.quantity">
        </span>
      </template>
      <label class="mb-3 block text-xs font-semibold text-slate-700">Keperluan
        <input name="keperluan" value="{{ old('keperluan') }}" maxlength="255" required
          placeholder="Jelaskan keperluan peminjaman"
          class="mt-1 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2 text-sm font-normal outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
      </label>
      <label class="mb-3 block text-xs font-semibold text-slate-700">Jenis Peminjaman
        <select name="jenis_peminjaman"
          required
          class="mt-1 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2 text-sm font-normal outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
          <option value="harian" @selected(old('jenis_peminjaman', 'harian') === 'harian')>Harian</option>
          <option value="mingguan" @selected(old('jenis_peminjaman') === 'mingguan')>Mingguan</option>
          <option value="bulanan" @selected(old('jenis_peminjaman') === 'bulanan')>Bulanan</option>
          <option value="semesteran" @selected(old('jenis_peminjaman') === 'semesteran')>Semesteran</option>
          <option value="tahunan" @selected(old('jenis_peminjaman') === 'tahunan')>Tahunan</option>
        </select>
      </label>
      <label class="mb-4 block text-xs font-semibold text-slate-700">Tanggal Rencana Kembali
        <input type="date" name="tanggal_rencana_kembali" value="{{ old('tanggal_rencana_kembali') }}"
          min="{{ now()->toDateString() }}" required
          class="mt-1 block w-full rounded-lg border border-slate-200 bg-[#f7fbfa] px-3 py-2 text-sm font-normal outline-none transition focus:border-[#00a88f] focus:ring-2 focus:ring-[#00a88f]/20">
      </label>
      <div class="mt-5 border-t border-slate-200 pt-3">
        <div class="mb-3 flex items-center justify-between gap-3 font-semibold text-slate-900"><span
            class="text-sm">Total jenis barang</span><span class="text-sm"
            x-text="`${selectedCartItems.length} jenis`"></span></div>
        <p
          class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-center text-[11px] leading-relaxed text-amber-950">
          <strong>Pengembalian dilakukan sekaligus, bukan sebagian. Jika barang kembali setengah akan ada
            konsekuensi dari SARPRAS.</strong></p>
        <button type="submit" :disabled="selectedCartItems.length === 0 || loanSubmitting"
          class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#00d69f] px-4 py-2.5 text-sm font-bold text-[#004d43] shadow-[0_5px_14px_rgba(0,190,144,0.2)] transition hover:bg-[#36e9b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#008b7a] focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
          <iconify-icon x-show="!loanSubmitting" icon="mdi:send-check-outline" aria-hidden="true"></iconify-icon>
          <iconify-icon x-show="loanSubmitting" icon="mdi:loading" class="animate-spin text-lg" aria-hidden="true"></iconify-icon>
          <span x-text="loanSubmitting ? 'Mengirim pengajuan...' : 'Ajukan Peminjaman'"></span>
        </button>
      </div>
    </form>
  </section>
</main>