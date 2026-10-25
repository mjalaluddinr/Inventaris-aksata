@if (session('success') === 'Login berhasil!' && auth()->check())
  <div x-data="{ visible: true }" x-init="window.setTimeout(() => visible = false, 1500)">
    <template x-teleport="body">
      <div x-show="visible" x-transition.opacity
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4"
        @click.self="visible = false" role="presentation">
        <section x-show="visible" x-transition
          class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-[0_24px_70px_rgba(0,56,50,0.25)]"
          role="dialog" aria-modal="true" aria-labelledby="login-welcome-title"
          aria-describedby="login-welcome-description">
          <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-[#00d69f] via-[#00a995] to-[#008f7e]"
            aria-hidden="true"></div>
          <div class="flex items-start gap-4 p-5 pt-6 sm:gap-5 sm:p-7 sm:pt-8">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#e5fff5] to-[#c8f7e8] text-[#008f7e] shadow-inner sm:h-16 sm:w-16">
              <iconify-icon icon="mdi:check-decagram" width="34" aria-hidden="true"></iconify-icon>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
              <p id="login-welcome-title"
                class="text-lg font-extrabold leading-6 tracking-tight text-[#064e3b] sm:text-xl">
                Login berhasil!</p>
              <p id="login-welcome-description" class="mt-1.5 text-base leading-6 text-slate-600">Selamat datang kembali,</p>
              <p class="mt-0.5 break-words text-lg leading-7 text-[#007f6b] sm:text-xl">
                {{-- ASVS 1.2.1: Blade escapes the authenticated username for its HTML text context. --}}
                <strong class="font-extrabold">{{ auth()->user()->username }}</strong>
              </p>
              <div class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold capitalize text-[#087f70]">
                <iconify-icon icon="mdi:shield-account-outline" width="15" aria-hidden="true"></iconify-icon>
                {{ auth()->user()->role }}
              </div>
            </div>
            <button type="button" @click="visible = false" aria-label="Tutup pesan"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#00a995]">
              <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
            </button>
          </div>
        </section>
      </div>
    </template>
  </div>
@endif
