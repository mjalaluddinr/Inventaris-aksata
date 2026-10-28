<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#006b70">
  <title>Login - InvenTrack</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    @keyframes login-content-arrive {
      from {
        opacity: 0;
        transform: translateY(18px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes login-device-float {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-7px);
      }
    }

    @keyframes login-light-drift {

      0%,
      100% {
        transform: translate3d(0, 0, 0) scale(1);
      }

      50% {
        transform: translate3d(12px, -10px, 0) scale(1.04);
      }
    }

    .login-brand-panel {
      background:
        radial-gradient(ellipse at 20% 12%, rgb(0 177 156 / 45%), transparent 42%),
        linear-gradient(148deg, #00b19c 0%, #007f78 42%, #00385a 100%);
      isolation: isolate;
    }

    .login-brand-content {
      position: relative;
      z-index: 1;
      gap: 0;
    }

    .login-watermark {
      position: absolute;
      top: calc(17% + 5px);
      left: calc(-0.08em + 5px);
      z-index: 0;
      width: max-content;
      pointer-events: none;
      user-select: none;
      color: transparent;
      background: linear-gradient(115deg, rgb(255 255 255 / 20%), rgb(255 255 255 / 4%) 48%, rgb(255 255 255 / 16%));
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-stroke: 1px rgb(255 255 255 / 27%);
      filter: drop-shadow(0 4px 8.8px rgb(0 56 90 / 22%));
      font-size: calc(clamp(7.5rem, 16vw, 14rem) + 2px);
      font-weight: 700;
      letter-spacing: -0.09em;
      line-height: calc(0.72em + 5px);
      animation: login-light-drift 12s ease-in-out infinite;
    }

    .login-hero-copy {
      position: relative;
      z-index: 2;
      max-width: clamp(20rem, 30vw, 26rem);
      animation: login-content-arrive 900ms 120ms both cubic-bezier(0.2, 0.7, 0.2, 1);
    }

    .login-hero-copy h1 {
      font-size: clamp(1.7rem, 3.4vw, 2.7rem);
      white-space: nowrap;
    }

    .login-hero-icon img {
      width: clamp(12rem, 24vw, 27rem);
      max-width: 100%;
      height: auto;
      object-fit: contain;
      animation: login-device-float 5.5s ease-in-out infinite;
      will-change: transform;
    }

    .login-form-card {
      animation: login-content-arrive 760ms 80ms both cubic-bezier(0.2, 0.7, 0.2, 1);
    }

    .login-mobile-logo {
      display: none;
    }

    .login-mobile-brand {
      display: none;
    }

    .login-heading-mobile {
      display: none;
    }

    @media (max-width: 1023px) {
      .login-page {
        display: block;
        height: 100dvh;
        min-height: 100dvh;
        overflow-x: hidden;
        overflow-y: auto;
        background: #00385a;
      }

      .login-brand-panel {
        position: absolute;
        inset: 0;
        min-height: 100%;
        height: 100%;
        justify-content: center;
        border-radius: 0;
      }

      .login-watermark {
        top: 5%;
        left: 50%;
        font-size: clamp(5.5rem, 19vw, 7.5rem);
        opacity: 0.38;
        transform: translateX(-50%);
      }

      .login-brand-content {
        display: none;
      }

      .login-divider {
        display: none;
      }

      .login-form-section {
        position: relative;
        inset: auto;
        z-index: 1;
        flex-direction: column;
        justify-content: center;
        min-height: 100dvh;
        align-items: center;
        gap: clamp(1.5rem, 5dvh, 2.5rem);
        overflow: visible;
        padding: clamp(2rem, 7dvh, 4rem) 1rem 2rem;
      }

      .login-mobile-brand {
        display: flex;
        flex: 0 0 auto;
        gap: 0.75rem;
      }

      .login-mobile-brand h1 {
        color: #fff;
        font-size: clamp(2.5rem, 11vw, 3.1rem);
        font-weight: 800;
        letter-spacing: -0.045em;
        line-height: 1;
        text-shadow: 0 3px 14px rgb(0 24 48 / 38%);
      }

      .login-mobile-brand-mark {
        height: 3.25rem;
        width: 3.25rem;
        border: 1px solid rgb(255 255 255 / 45%);
        border-radius: 1rem;
        background: #00B19C;
        box-shadow: 0 8px 22px rgb(0 24 48 / 25%);
      }

      .login-form-card {
        max-height: none;
        overflow: visible;
        border-color: rgb(255 255 255 / 82%);
        border-top: 3px solid #00B19C;
        border-radius: 1.5rem;
        background: rgb(255 255 255 / 98%);
        padding: clamp(1.35rem, 6vw, 1.75rem);
        box-shadow: 0 24px 70px rgb(0 24 48 / 30%);
      }

      .login-form-heading {
        margin-bottom: 1.25rem;
        border-bottom-color: #e2ece9;
        text-align: left;
      }

      .login-form-heading h2 {
        display: block;
        color: #00385a;
        font-size: 1.5rem;
        font-weight: 750;
        letter-spacing: -0.025em;
      }

      .login-heading-desktop {
        display: none;
      }

      .login-heading-mobile {
        display: inline;
      }

      .login-form-heading p {
        display: none;
      }

      .login-mobile-logo {
        display: none;
      }

      .login-form-fields {
        gap: 1.1rem;
      }
    }

    @media (min-width: 1024px) {
      .login-brand-panel {
        clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);
      }

      .login-brand-content {
        padding-top: 0;
        padding-bottom: 0;
        transform: translateX(clamp(1rem, 2.5vw, 2.5rem));
      }

      .login-divider {
        position: absolute;
        z-index: 5;
        top: -2%;
        bottom: -2%;
        left: 0;
        width: 100%;
        filter: drop-shadow(0 4px 7px rgb(0 56 90 / 20%));
        pointer-events: none;
      }

      .login-divider::before {
        position: absolute;
        inset: 0;
        background: #00385a;
        clip-path: polygon(51.3% 0, 54.9% 0, 45.4% 100%, 41.8% 100%);
        content: "";
      }

      .login-divider::after {
        position: absolute;
        inset: 0;
        background: #0098b1;
        clip-path: polygon(54.9% 0, 56.5% 0, 47% 100%, 45.4% 100%);
        content: "";
      }

      .login-divider-accent {
        position: absolute;
        inset: 0;
        background: #00B19C;
        clip-path: polygon(49.7% 0, 51.3% 0, 41.8% 100%, 40.2% 100%);
      }
    }

    .login-modal {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 180ms ease, visibility 180ms ease;
    }

    .login-modal.active {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }

    .login-modal-card {
      transform: translateY(8px);
      transition: transform 180ms ease;
    }

    .login-modal.active .login-modal-card {
      transform: translateY(0);
    }

    @media (max-height: 680px) {
      .login-form-card {
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
      }

      .login-form-heading {
        margin-bottom: 1.25rem;
      }

      .login-form-fields {
        gap: 1rem;
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
        will-change: auto !important;
      }
    }
  </style>
</head>

<body class="h-dvh min-h-dvh overflow-hidden bg-white font-sans antialiased text-slate-900">
  <main class="login-page relative isolate h-dvh min-h-dvh overflow-hidden lg:grid lg:grid-cols-2">
    <section
      class="login-brand-panel absolute inset-0 z-0 flex h-full min-h-0 flex-col justify-between overflow-hidden px-6 py-6 text-white sm:px-12 sm:py-8 lg:relative lg:inset-auto lg:z-auto lg:h-dvh lg:px-10 lg:py-7 xl:px-14"
      aria-label="Tentang InvenTrack">
      <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
        <span class="login-watermark">INVENT<br>TRACK</span>
        <span class="absolute inset-0 opacity-[0.08]"
          style="background-image: linear-gradient(rgb(255 255 255 / 35%) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / 35%) 1px, transparent 1px); background-size: 44px 44px;"></span>
      </div>

      <div class="login-brand-content mx-auto flex min-h-0 w-full max-w-xl flex-1 flex-col justify-center lg:mx-0">
        <div class="login-hero-icon flex w-full shrink-0 items-center justify-center"
          aria-hidden="true">
          <img src="{{ asset('assets/icon.svg') }}" alt=""
            class="drop-shadow-[0_10px_7px_rgba(0,0,0,0.18)]">
        </div>
        <div class="login-hero-copy">
          <h1 class="text-4xl font-semibold tracking-tight xl:text-[clamp(2.5rem,4.2vw,3.25rem)]">Halo, Pengguna...</h1>
          <p class="mt-3 max-w-lg text-base leading-7 text-white/85 xl:text-lg xl:leading-[1.55]">
            Masuk ke InvenTrack untuk mencatat barang, ruangan, rak, dan peminjaman jurusan secara terpusat dan
            tertelusuri.
          </p>
        </div>
      </div>

      <footer
        class="relative z-10 hidden border-t border-white/25 pt-3 text-center text-xs text-white/90 sm:text-sm lg:block">
        © {{ date('Y') }} InvenTrack · Sistem Inventaris Jurusan
      </footer>
    </section>

    <div class="login-divider hidden lg:block" aria-hidden="true">
      <span class="login-divider-accent"></span>
    </div>

    <section
      class="login-form-section absolute inset-0 z-10 flex h-full items-center justify-center overflow-y-auto px-4 py-4 sm:px-8 lg:relative lg:inset-auto lg:z-10 lg:h-dvh lg:px-8 lg:py-6 xl:px-12"
      aria-label="Form masuk">
      <div class="login-mobile-brand flex-col items-center lg:hidden">
        <span class="login-mobile-brand-mark flex items-center justify-center"
          aria-hidden="true">
          <img src="{{ asset('assets/icon.svg') }}" alt="" class="h-9 w-9 object-contain">
        </span>
        <h1>InvenTrack</h1>
      </div>
      <div
        class="login-form-card relative z-10 max-h-full w-full max-w-[27rem] overflow-y-auto rounded-[1.6rem] border border-slate-100 bg-white px-5 py-6 shadow-[0_18px_60px_rgba(0,56,90,0.12)] sm:rounded-none sm:border-0 sm:bg-transparent sm:px-8 sm:py-7 sm:shadow-none sm:backdrop-blur-none lg:max-h-[calc(100dvh-3rem)]">
        <header class="login-form-heading mb-5 border-b border-[#d8e2e0] pb-4 text-center">
          <h2 class="text-[2.4rem] font-bold leading-none tracking-tight text-[#00385a] sm:text-[2.6rem]">
            <span class="login-heading-desktop">InvenTrack</span>
            <span class="login-heading-mobile">Masuk ke akun</span>
          </h2>
          <p class="mt-2 text-sm font-semibold tracking-[0.04em] text-[#83a4a2]">MASUK KE AKUN ANDA</p>
        </header>

        @if (session('success'))
          <div x-data="{ visible: true }" x-init="window.setTimeout(() => visible = false, 1500)">
            <template x-teleport="body">
              <div x-show="visible" x-transition.opacity
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4"
                @click.self="visible = false" role="presentation">
            <section x-show="visible" x-transition
              class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-white via-emerald-50 to-teal-50 p-5 shadow-[0_24px_70px_rgba(0,127,107,0.22)] sm:p-6"
              role="dialog" aria-modal="true" aria-labelledby="login-success-title">
              <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#00c99a] to-[#008f7e]"
                aria-hidden="true"></div>
              <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-[#008f7e]">
                  <iconify-icon icon="mdi:shield-check" width="25" aria-hidden="true"></iconify-icon>
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                  <p id="login-success-title" class="text-base font-extrabold text-[#064e3b]">
                    {{ session('success') === 'Berhasil logout.' ? 'Berhasil keluar' : 'Berhasil!' }}
                  </p>
                  <p class="mt-1 text-sm leading-6 text-emerald-900/80">
                    @if (session('success') === 'Berhasil logout.')
                      Sesi Anda sudah diakhiri dengan aman. Sampai jumpa kembali!
                    @else
                      {{ session('success') }}
                    @endif
                  </p>
                </div>
                <button type="button" @click="visible = false" aria-label="Tutup pesan berhasil"
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-emerald-700/60 transition hover:bg-emerald-100 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                  <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
                </button>
              </div>
            </section>
          </div>
            </template>
          </div>
        @endif

        @if (session('info'))
          <div x-data="{ visible: true }">
            <template x-teleport="body">
              <div x-show="visible" x-transition.opacity
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4"
                @click.self="visible = false" role="presentation">
            <section x-show="visible" x-transition
              class="w-full max-w-lg rounded-2xl border border-amber-200 bg-gradient-to-br from-white to-amber-50 p-5 shadow-[0_24px_70px_rgba(180,83,9,0.18)] sm:p-6"
              role="dialog" aria-modal="true" aria-labelledby="login-info-title">
              <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                  <iconify-icon icon="mdi:information-variant-circle" width="25" aria-hidden="true"></iconify-icon>
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                  <p id="login-info-title" class="text-base font-extrabold text-amber-950">Informasi akun</p>
                  <p class="mt-1 text-sm leading-6 text-amber-900">{{ session('info') }}</p>
                </div>
                <button type="button" @click="visible = false" aria-label="Tutup informasi"
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-amber-700/60 transition hover:bg-amber-100 hover:text-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                  <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
                </button>
              </div>
            </section>
          </div>
            </template>
          </div>
        @endif

        @if ($errors->any())
          <div x-data="{ visible: true }">
            <template x-teleport="body">
              <div x-show="visible" x-transition.opacity
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4"
                @click.self="visible = false" role="presentation">
            <section x-show="visible" x-transition
              class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-rose-200 bg-gradient-to-br from-white via-rose-50 to-orange-50 p-5 shadow-[0_24px_70px_rgba(190,24,93,0.18)] sm:p-6"
              role="dialog" aria-modal="true" aria-labelledby="login-error-title">
              <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 to-orange-400"
                aria-hidden="true"></div>
              <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                  <iconify-icon icon="mdi:alert-circle" width="25" aria-hidden="true"></iconify-icon>
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                  <p id="login-error-title" class="text-base font-extrabold text-rose-950">
                    {{ $errors->has('role') ? 'Akses akun tidak tersedia' : 'Periksa username dan password' }}
                  </p>
                  <p class="mt-1 text-sm leading-6 text-rose-900/80">
                    @if ($errors->has('role'))
                      Silakan hubungi administrator untuk memastikan hak akses akun Anda.
                    @else
                      Informasi login belum cocok. Periksa kembali username dan password Anda.
                    @endif
                  </p>
                  @if ($errors->has('role'))
                    <ul class="mt-3 space-y-2">
                      @foreach ($errors->get('role') as $error)
                        <li class="flex items-start gap-2 rounded-xl border border-rose-100 bg-white/80 px-3 py-2 text-sm leading-5 text-rose-800">
                          <iconify-icon icon="mdi:alert-circle-outline" class="mt-0.5 shrink-0" width="16"
                            aria-hidden="true"></iconify-icon>
                          <span>{{ $error }}</span>
                        </li>
                      @endforeach
                    </ul>
                  @endif
                </div>
                <button type="button" @click="visible = false" aria-label="Tutup pesan kesalahan"
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-rose-700/60 transition hover:bg-rose-100 hover:text-rose-900 focus:outline-none focus:ring-2 focus:ring-rose-500">
                  <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
                </button>
              </div>
            </section>
          </div>
            </template>
          </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="login-form-fields space-y-4" id="loginForm">
          @csrf

          <div>
            <label for="username" class="mb-2 block text-sm font-semibold text-[#607471]">Username</label>
            <div class="group relative">
              <iconify-icon icon="mdi:account-outline"
                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 transition-colors group-focus-within:text-[#008f82]"
                aria-hidden="true"></iconify-icon>
              <input type="text" id="username" name="username" value="{{ old('username') }}" required
                maxlength="255" autocomplete="username" autofocus placeholder="Masukkan Username"
                @class([
                    'w-full rounded-xl border bg-white py-2.5 pl-11 pr-4 text-slate-900 outline-none transition placeholder:text-[#83a4a2] focus:ring-2',
                    'border-rose-300 focus:border-rose-400 focus:ring-rose-500/10' => $errors->has('username'),
                    'border-[#c9d8d6] focus:border-[#00a995] focus:ring-[#00a995]/10' => ! $errors->has('username'),
                ])
                @if ($errors->has('username')) aria-invalid="true" aria-describedby="username-error" @endif>
            </div>
            @error('username')
              <p id="username-error" class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>
            @enderror
          </div>

          <div>
            <label for="password" class="mb-2 block text-sm font-semibold text-[#607471]">Password</label>
            <div class="group relative">
              <iconify-icon icon="mdi:lock-outline"
                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 transition-colors group-focus-within:text-[#008f82]"
                aria-hidden="true"></iconify-icon>
              <input type="password" id="password" name="password" required autocomplete="current-password"
                placeholder="Masukkan Password"
                @class([
                    'w-full rounded-xl border bg-white py-2.5 pl-11 pr-12 text-slate-900 outline-none transition placeholder:text-[#83a4a2] focus:ring-2',
                    'border-rose-300 focus:border-rose-400 focus:ring-rose-500/10' => $errors->has('password'),
                    'border-[#c9d8d6] focus:border-[#00a995] focus:ring-[#00a995]/10' => ! $errors->has('password'),
                ])
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
              <button type="button" id="togglePassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400 transition hover:text-[#00385a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#008f82]"
                aria-label="Tampilkan password">
                <iconify-icon icon="mdi:eye-outline" id="eyeIcon" class="text-xl" aria-hidden="true"></iconify-icon>
              </button>
            </div>
            @error('password')
              <p id="password-error" class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>
            @enderror
          </div>

          <div class="flex justify-end">
            <a href="{{ route('password.request') }}" id="forgotPasswordLink"
              class="rounded text-sm font-semibold text-[#007f78] underline-offset-4 transition hover:text-[#00385a] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008f82]">
              Lupa password?
            </a>
          </div>

          <button type="submit" id="loginBtn" aria-busy="false"
            class="relative mx-auto flex w-full max-w-44 items-center justify-center rounded-lg bg-[#00d9a1] px-4 py-2.5 text-sm font-bold uppercase text-[#003f38] shadow-[0_3px_8px_rgba(0,63,56,0.2)] transition hover:bg-[#00c894] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00385a] disabled:cursor-wait disabled:opacity-70">
            <span class="btn-text">Masuk</span>
            <span class="spinner absolute left-1/2 top-1/2 hidden h-5 w-5 -translate-x-1/2 -translate-y-1/2 animate-spin rounded-full border-2 border-[#003f38]/25 border-t-[#003f38]"
              aria-hidden="true"></span>
            <span class="sr-only spinner-label hidden">Sedang masuk...</span>
          </button>
        </form>
      </div>
    </section>
  </main>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePassword.addEventListener('click', () => {
      const isVisible = passwordInput.type === 'text';
      passwordInput.type = isVisible ? 'password' : 'text';
      togglePassword.setAttribute('aria-label', isVisible ? 'Tampilkan password' : 'Sembunyikan password');
      eyeIcon.setAttribute('icon', isVisible ? 'mdi:eye-outline' : 'mdi:eye-off-outline');
    });

    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    loginForm.addEventListener('submit', () => {
      loginBtn.disabled = true;
      loginBtn.setAttribute('aria-busy', 'true');
      loginBtn.querySelector('.btn-text').classList.add('invisible');
      loginBtn.querySelector('.spinner').classList.remove('hidden');
      loginBtn.querySelector('.spinner-label').classList.remove('hidden');
    });

  </script>
</body>

</html>
