@php
  $isLoginNotice = auth()->check() && session('success') === 'Login berhasil!';
  $isLogoutNotice = ! auth()->check() && session('success') === 'Berhasil logout.';
@endphp

@if ($isLoginNotice || $isLogoutNotice)
  <style>
    @keyframes session-notice-countdown {
      to {
        transform: scaleX(0);
      }
    }

    .session-notice-card {
      background:
        radial-gradient(ellipse at 100% 0%, rgb(0 169 149 / 12%), transparent 46%),
        radial-gradient(ellipse at 0% 100%, rgb(0 214 159 / 7%), transparent 38%),
        linear-gradient(145deg, #ffffff 0%, #ffffff 58%, #effcf8 100%);
    }

    @media (prefers-reduced-motion: reduce) {
      .session-notice-countdown {
        animation: none !important;
      }

      .session-notice-countdown {
        transform: scaleX(1);
      }
    }
  </style>
  <div x-data="{ visible: true }" x-init="window.setTimeout(() => visible = false, 4000)">
    <template x-teleport="body">
      <div x-show="visible"
        x-transition:enter="transition ease-out duration-500"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm"
        role="presentation">
        <section x-show="visible"
          x-transition:enter="transition ease-out duration-500"
          x-transition:enter-start="translate-y-6 scale-95 opacity-0"
          x-transition:enter-end="translate-y-0 scale-100 opacity-100"
          x-transition:leave="transition ease-in duration-300"
          x-transition:leave-start="translate-y-0 scale-100 opacity-100"
          x-transition:leave-end="translate-y-4 scale-95 opacity-0"
          class="session-notice-card relative w-full max-w-lg overflow-hidden rounded-3xl border border-emerald-100 shadow-[0_24px_70px_rgba(0,56,50,0.25)]"
          role="dialog" aria-modal="true" aria-labelledby="session-notice-title"
          aria-describedby="session-notice-description">
          <div class="h-1.5 bg-emerald-100/80" aria-hidden="true">
            <span class="session-notice-countdown block h-full origin-left bg-gradient-to-r from-[#7cdb83] via-[#00c99a] to-[#007f78]"
              style="animation: session-notice-countdown 4000ms linear forwards"></span>
          </div>
          <div class="flex items-start gap-4 p-5 sm:gap-5 sm:p-7">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#00c99a] via-[#00a995] to-[#007f78] text-white shadow-[inset_0_1px_3px_rgb(255_255_255_/_35%),0_9px_22px_rgb(0_127_107_/_24%)] sm:h-16 sm:w-16">
              <iconify-icon icon="{{ $isLoginNotice ? 'mdi:check-decagram' : 'mdi:logout' }}" width="34"
                aria-hidden="true"></iconify-icon>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
              <p id="session-notice-title"
                class="text-lg font-extrabold leading-6 tracking-tight text-[#064e3b] sm:text-xl">
                {{ $isLoginNotice ? 'Login berhasil!' : 'Berhasil keluar' }}
              </p>
              @if ($isLoginNotice)
                <p id="session-notice-description" class="mt-1.5 text-base leading-6 text-slate-600">
                  Selamat datang kembali,
                </p>
                <p class="mt-0.5 break-words text-lg leading-7 text-[#007f6b] sm:text-xl">
                  {{-- ASVS 1.2.1: Blade escapes the authenticated username for its HTML text context. --}}
                  <strong class="font-extrabold">{{ auth()->user()->username }}</strong>
                </p>
              @else
                <p id="session-notice-description" class="mt-1.5 text-base leading-6 text-slate-600">
                  Sesi Anda sudah diakhiri dengan aman. Sampai jumpa kembali!
                </p>
              @endif
            </div>
            <button type="button" @click="visible = false" aria-label="Tutup pesan"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-emerald-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#00a995]">
              <iconify-icon icon="mdi:close" width="20" aria-hidden="true"></iconify-icon>
            </button>
          </div>
        </section>
      </div>
    </template>
  </div>
@endif
