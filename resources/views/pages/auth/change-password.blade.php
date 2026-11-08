<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#006b70">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>Buat Password Baru - InvenTrack</title>
  @fonts('poppins')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    @keyframes change-copy-enter {
      from {
        opacity: 0;
        transform: translateY(18px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .change-brand-panel {
      isolation: isolate;
      background:
        radial-gradient(ellipse at 20% 12%, rgb(0 177 156 / 45%), transparent 42%),
        linear-gradient(148deg, #00b19c 0%, #007f78 42%, #00385a 100%);
    }

    .change-watermark {
      position: absolute;
      top: calc(17% + 5px);
      left: calc(-0.08em + 5px);
      z-index: 0;
      width: max-content;
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
      animation: change-light-drift 12s ease-in-out infinite;
      pointer-events: none;
      user-select: none;
    }

    @keyframes change-light-drift {
      0%,
      100% {
        transform: translate3d(0, 0, 0) scale(1);
      }

      50% {
        transform: translate3d(12px, -10px, 0) scale(1.04);
      }
    }

    .change-brand-content {
      position: relative;
      z-index: 1;
    }

    .change-brand-mark img {
      width: clamp(12rem, 24vw, 27rem);
      max-width: 100%;
      height: auto;
      object-fit: contain;
      filter: brightness(0) invert(1) drop-shadow(0 10px 7px rgb(0 0 0 / 18%));
      animation: change-device-float 5.5s ease-in-out infinite;
      will-change: transform;
    }

    @keyframes change-device-float {
      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-7px);
      }
    }

    .change-hero-copy {
      position: relative;
      z-index: 2;
      max-width: clamp(20rem, 30vw, 26rem);
      animation: change-copy-enter 900ms 120ms both cubic-bezier(0.2, 0.7, 0.2, 1);
    }

    .change-hero-copy h1 {
      font-size: clamp(1.7rem, 3.4vw, 2.7rem);
      white-space: nowrap;
    }

    .change-divider {
      position: absolute;
      z-index: 5;
      inset: -2% 0;
      filter: drop-shadow(0 4px 7px rgb(0 56 90 / 20%));
      pointer-events: none;
    }

    .change-divider::before,
    .change-divider::after,
    .change-divider-accent {
      position: absolute;
      inset: 0;
    }

    .change-divider::before {
      background: #00385a;
      clip-path: polygon(51.3% 0, 54.9% 0, 45.4% 100%, 41.8% 100%);
      content: "";
    }

    .change-divider::after {
      background: #0098b1;
      clip-path: polygon(54.9% 0, 56.5% 0, 47% 100%, 45.4% 100%);
      content: "";
    }

    .change-divider-accent {
      background: #00b19c;
      clip-path: polygon(49.7% 0, 51.3% 0, 41.8% 100%, 40.2% 100%);
    }

    .change-form-card {
      animation: change-copy-enter 760ms 80ms both cubic-bezier(.22, 1, .36, 1);
    }

    @media (max-width: 1023px) {
      .change-password-page {
        min-height: 100dvh;
        overflow-x: hidden;
        overflow-y: auto;
        background: #00385a;
      }

      .change-brand-panel {
        position: absolute;
        inset: 0;
        min-height: 100%;
        border-radius: 0;
      }

      .change-watermark {
        top: 5%;
        left: 50%;
        font-size: clamp(5.5rem, 19vw, 7.5rem);
        opacity: 0.38;
        transform: translateX(-50%);
      }

      .change-brand-content,
      .change-brand-footer,
      .change-divider {
        display: none;
      }

      .change-mobile-brand {
        display: flex;
      }

      .change-form-section {
        position: relative;
        z-index: 1;
        min-height: 100dvh;
        flex-direction: column;
        justify-content: center;
        gap: clamp(1.5rem, 5dvh, 2.5rem);
        padding: clamp(2rem, 7dvh, 4rem) 1rem 2rem;
      }

      .change-form-card {
        border-top: 3px solid #00b19c;
        background: rgb(255 255 255 / 98%);
        box-shadow: 0 24px 70px rgb(0 24 48 / 30%);
      }
    }

    @media (min-width: 1024px) {
      .change-brand-panel {
        clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);
      }

      .change-brand-content {
        padding-top: 0;
        padding-bottom: 0;
        transform: translateX(clamp(1rem, 2.5vw, 2.5rem));
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        will-change: auto !important;
      }
    }
  </style>
</head>

<body class="min-h-dvh bg-white font-sans antialiased text-slate-900">
  <main
    class="change-password-page relative isolate min-h-dvh overflow-hidden bg-white lg:grid lg:grid-cols-2">
    <section
      class="change-brand-panel absolute inset-0 z-0 flex min-h-full flex-col justify-between overflow-hidden px-6 py-6 text-white sm:px-12 sm:py-8 lg:relative lg:inset-auto lg:z-auto lg:min-h-dvh lg:px-10 lg:py-7 xl:px-14"
      aria-label="Tentang InvenTrack">
      <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
        <span class="change-watermark">INVENT<br>TRACK</span>
        <span class="absolute inset-0 opacity-[0.08]"
          style="background-image: linear-gradient(rgb(255 255 255 / 35%) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / 35%) 1px, transparent 1px); background-size: 44px 44px;"></span>
      </div>

      <div class="change-brand-content mx-auto flex min-h-0 w-full max-w-xl flex-1 flex-col justify-center lg:mx-0">
        <div class="change-brand-mark flex w-full shrink-0 items-center justify-center" aria-hidden="true">
          <img src="{{ asset('assets/icon.svg') }}" alt="">
        </div>
        <div class="change-hero-copy">
          <h1 class="text-4xl font-semibold tracking-tight xl:text-[clamp(2.5rem,4.2vw,3.25rem)]">Halo, Pengguna...</h1>
          <p class="mt-3 max-w-lg text-base leading-7 text-white/85 xl:text-lg xl:leading-[1.55]">
            Masuk ke InvenTrack untuk mencatat barang, ruangan, rak, dan peminjaman jurusan secara terpusat dan
            tertelusuri.
          </p>
        </div>
      </div>

      <footer
        class="change-brand-footer relative z-10 hidden border-t border-white/25 pt-3 text-center text-xs text-white/90 sm:text-sm lg:block">
        © {{ date('Y') }} InvenTrack · Sistem Inventaris Jurusan
      </footer>
    </section>

    <div class="change-divider hidden lg:block" aria-hidden="true">
      <span class="change-divider-accent"></span>
    </div>

    <section
      class="change-form-section relative z-10 flex min-h-dvh items-center justify-center overflow-y-auto px-4 py-6 sm:px-8 lg:px-8 lg:py-8 xl:px-12"
      aria-label="Form password baru">
      <div class="change-mobile-brand hidden flex-col items-center gap-3 lg:hidden">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/40 bg-[#00b19c] shadow-lg">
          <img src="{{ asset('assets/icon.svg') }}" alt="" class="h-10 w-10 object-contain brightness-0 invert">
        </span>
        <span class="text-3xl font-extrabold tracking-tight text-white">InvenTrack</span>
      </div>

      <div
        class="change-form-card w-full max-w-[27rem] rounded-[1.6rem] border border-slate-100 bg-white px-5 py-6 shadow-[0_18px_60px_rgba(0,56,90,0.12)] sm:px-8 sm:py-8 lg:border-0 lg:bg-transparent lg:shadow-none">
        <header class="mb-6 flex items-center gap-4 border-b border-[#d8e2e0] pb-5">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#00385a] to-[#00b19c] text-white shadow-md">
            <iconify-icon icon="mdi:lock-reset" class="text-2xl" aria-hidden="true"></iconify-icon>
          </span>
          <div>
            <h2 class="text-2xl font-bold leading-tight tracking-tight text-[#00385a] sm:text-[1.7rem]">
              Buat password baru
            </h2>
          </div>
        </header>

        <form action="{{ route('password.update') }}" method="POST" class="space-y-5" id="changePasswordForm">
          @csrf
          @method('PUT')

          <div>
            <label for="new_password" class="mb-2 block text-sm font-semibold text-[#607471]">Password baru</label>
            <div class="group relative">
              <iconify-icon icon="mdi:lock-outline"
                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 transition-colors group-focus-within:text-[#008f82]"
                aria-hidden="true"></iconify-icon>
              <input
                type="password"
                id="new_password"
                name="new_password"
                required
                minlength="8"
                autocomplete="new-password"
                autofocus
                placeholder="Minimal 8 karakter"
                @class([
                    'w-full rounded-xl border bg-white py-3 pl-11 pr-12 text-slate-900 outline-none transition placeholder:text-[#83a4a2] focus:ring-2',
                    'border-rose-300 focus:border-rose-400 focus:ring-rose-500/10' => $errors->has('new_password'),
                    'border-[#c9d8d6] focus:border-[#00a995] focus:ring-[#00a995]/10' => ! $errors->has('new_password'),
                ])
                @if ($errors->has('new_password')) aria-invalid="true" aria-describedby="new-password-error" @endif>
              <button type="button" data-password-toggle="new_password"
                class="absolute inset-y-0 right-1 my-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:text-[#00385a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#008f82]"
                aria-label="Tampilkan password baru">
                <iconify-icon icon="mdi:eye-outline" class="text-xl" aria-hidden="true"></iconify-icon>
              </button>
            </div>
            <p class="mt-2 text-xs text-slate-500">Gunakan minimal 8 karakter.</p>
            @error('new_password')
              <p id="new-password-error" class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</p>
            @enderror
          </div>

          <div>
            <label for="new_password_confirmation" class="mb-2 block text-sm font-semibold text-[#607471]">
              Konfirmasi password baru
            </label>
            <div class="group relative">
              <iconify-icon icon="mdi:check-circle-outline"
                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 transition-colors group-focus-within:text-[#008f82]"
                aria-hidden="true"></iconify-icon>
              <input
                type="password"
                id="new_password_confirmation"
                name="new_password_confirmation"
                required
                minlength="8"
                autocomplete="new-password"
                placeholder="Ketik ulang password baru"
                class="w-full rounded-xl border border-[#c9d8d6] bg-white py-3 pl-11 pr-12 text-slate-900 outline-none transition placeholder:text-[#83a4a2] focus:border-[#00a995] focus:ring-2 focus:ring-[#00a995]/10">
              <button type="button" data-password-toggle="new_password_confirmation"
                class="absolute inset-y-0 right-1 my-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:text-[#00385a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#008f82]"
                aria-label="Tampilkan konfirmasi password baru">
                <iconify-icon icon="mdi:eye-outline" class="text-xl" aria-hidden="true"></iconify-icon>
              </button>
            </div>
            @error('new_password_confirmation')
              <p class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</p>
            @enderror
          </div>

          <button
            type="submit"
            id="changePasswordButton"
            aria-busy="false"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#00b19c] to-[#009a87] px-4 py-3.5 font-semibold text-white shadow-lg shadow-emerald-900/15 transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00385a] disabled:cursor-wait disabled:opacity-80">
            <iconify-icon icon="mdi:loading" class="change-password-spinner hidden animate-spin text-lg" aria-hidden="true"></iconify-icon>
            <iconify-icon icon="mdi:check-circle-outline" class="change-password-submit-icon text-lg" aria-hidden="true"></iconify-icon>
            <span class="change-password-submit-label">Simpan password baru</span>
          </button>
        </form>
      </div>
    </section>
  </main>

  <script>
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const passwordInput = document.getElementById(button.dataset.passwordToggle);
        const icon = button.querySelector('iconify-icon');
        const isVisible = passwordInput.type === 'text';

        passwordInput.type = isVisible ? 'password' : 'text';
        button.setAttribute('aria-label', isVisible ? 'Tampilkan password' : 'Sembunyikan password');
        icon.setAttribute('icon', isVisible ? 'mdi:eye-outline' : 'mdi:eye-off-outline');
      });
    });

    const changePasswordForm = document.getElementById('changePasswordForm');
    const changePasswordButton = document.getElementById('changePasswordButton');

    changePasswordForm.addEventListener('submit', (event) => {
      changePasswordButton.disabled = true;
      changePasswordButton.setAttribute('aria-busy', 'true');
      changePasswordButton.querySelector('.change-password-submit-icon').classList.add('hidden');
      changePasswordButton.querySelector('.change-password-spinner').classList.remove('hidden');
      changePasswordButton.querySelector('.change-password-submit-label').textContent = 'Menyimpan...';

      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
      }

    });
  </script>
</body>

</html>
