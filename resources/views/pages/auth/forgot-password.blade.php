<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00385A">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>Ajukan Ganti Password - InvenTrack</title>
  @fonts('poppins')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    @keyframes recovery-form-enter {
      from {
        opacity: 0;
        transform: translate3d(-24px, 0, 0);
      }

      to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
      }
    }

    @keyframes recovery-brand-enter {
      from {
        opacity: 0;
        translate: 24px 0;
      }

      to {
        opacity: 1;
        translate: 0 0;
      }
    }

    @keyframes recovery-device-float {
      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-7px);
      }
    }

    @keyframes recovery-light-drift {
      0%,
      100% {
        transform: translate3d(0, 0, 0) scale(1);
      }

      50% {
        transform: translate3d(12px, -10px, 0) scale(1.04);
      }
    }

    .recovery-story {
      background:
        radial-gradient(ellipse at 80% 12%, rgb(0 177 156 / 45%), transparent 42%),
        linear-gradient(212deg, #00b19c 0%, #007f78 42%, #00385a 100%);
      isolation: isolate;
    }

    .recovery-brand-content {
      position: relative;
      z-index: 1;
      gap: 0;
      transform: translateX(0);
      animation: recovery-brand-enter 760ms 100ms both cubic-bezier(.22, 1, .36, 1);
    }

    .recovery-form-content {
      animation: recovery-form-enter 720ms 80ms both cubic-bezier(.22, 1, .36, 1);
    }

    .recovery-watermark {
      position: absolute;
      top: calc(17% + 5px);
      right: calc(-0.08em + 5px);
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
      animation: recovery-light-drift 12s ease-in-out infinite;
    }

    .recovery-hero-icon img {
      width: clamp(12rem, 24vw, 27rem);
      max-width: 100%;
      height: auto;
      object-fit: contain;
      filter: brightness(0) invert(1) drop-shadow(0 10px 7px rgb(0 0 0 / 18%));
      animation: recovery-device-float 5.5s ease-in-out infinite;
      will-change: transform;
    }

    .recovery-hero-copy {
      position: relative;
      z-index: 2;
      max-width: clamp(20rem, 30vw, 26rem);
    }

    .recovery-hero-copy h2 {
      font-size: clamp(1.7rem, 3.4vw, 2.7rem);
      white-space: nowrap;
    }

    .recovery-divider {
      display: none;
    }

    @media (min-width: 1024px) {
      .recovery-story {
        clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);
      }

      .recovery-brand-content {
        margin-inline: auto;
        transform: translateX(clamp(1.5rem, 3vw, 2.25rem));
      }

      .recovery-hero-copy {
        margin-inline: auto;
        text-align: center;
      }

      .recovery-divider {
        position: absolute;
        z-index: 20;
        inset: -2% 0;
        display: block;
        filter: drop-shadow(0 4px 7px rgb(0 56 90 / 20%));
        pointer-events: none;
      }

      .recovery-divider::before,
      .recovery-divider::after,
      .recovery-divider-accent {
        position: absolute;
        inset: 0;
      }

      .recovery-divider::before {
        background: #00385a;
        clip-path: polygon(54.8% 0, 58.4% 0, 48.9% 100%, 45.3% 100%);
        content: "";
      }

      .recovery-divider::after {
        background: #0098b1;
        clip-path: polygon(58.4% 0, 60% 0, 50.5% 100%, 48.9% 100%);
        content: "";
      }

      .recovery-divider-accent {
        background: #00b19c;
        clip-path: polygon(53.2% 0, 54.8% 0, 45.3% 100%, 43.7% 100%);
      }
    }

    @media (max-width: 1023px) {
      .recovery-watermark {
        top: 5%;
        right: auto;
        left: 50%;
        font-size: clamp(5.5rem, 19vw, 7.5rem);
        opacity: 0.38;
        transform: translateX(-50%);
      }

      .recovery-story {
        display: flex;
        flex-direction: column;
        min-height: 100dvh;
        justify-content: center;
        padding-top: 3rem;
        padding-bottom: 3rem;
      }

      .recovery-brand-footer {
        display: none;
      }
    }

    .recovery-submit-spinner {
      display: none;
    }

    .recovery-form-control {
      width: calc(100% - 2.5rem);
    }

    .recovery-form.is-submitting .recovery-submit-spinner {
      display: inline-flex;
    }

    .recovery-form.is-submitting .recovery-submit-icon {
      display: none;
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
      }
    }
  </style>
</head>

<body class="min-h-dvh bg-white font-sans antialiased text-slate-900">
  <main class="recovery-page relative isolate grid min-h-dvh lg:h-dvh lg:grid-cols-2 lg:overflow-hidden">
    <section class="relative z-10 flex min-h-dvh items-center justify-center bg-white px-5 py-9 sm:px-10 lg:px-8 xl:px-12"
      aria-label="Formulir permintaan password">
      <div class="recovery-form-content w-full max-w-[31rem]">
        <a href="{{ route('login') }}"
          class="group mb-12 inline-flex items-center gap-2 text-sm font-semibold text-[#007f78] transition-colors duration-200 hover:text-[#00385a] focus-visible:underline focus-visible:underline-offset-4">
          <iconify-icon icon="mdi:arrow-left" class="transition-transform duration-300 group-hover:-translate-x-1"
            aria-hidden="true"></iconify-icon>
          Kembali ke login
        </a>

        <div class="mb-8 flex items-center gap-3 lg:hidden">
          <img src="{{ asset('assets/icon.svg') }}" alt="" class="h-10 w-10">
          <span class="text-xl font-bold tracking-tight text-[#00385a]">InvenTrack</span>
        </div>

        <div class="mb-8">
          <span class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#e6f5f1] text-[#007f78]">
            <iconify-icon icon="mdi:lock-reset" class="text-2xl" aria-hidden="true"></iconify-icon>
          </span>
          <h1 class="text-3xl font-bold tracking-tight text-[#00385a] sm:text-[2.15rem]">
            Pulihkan akses akun
          </h1>
          <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">
            Ajukan permintaan kepada admin. Setelah disetujui, admin akan memberikan password sementara untuk masuk kembali.
          </p>
        </div>

        <div data-recovery-status
          class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900 {{ session('status') ? '' : 'hidden' }}"
          role="status" aria-live="polite">
          <iconify-icon icon="mdi:check-circle-outline" class="mt-0.5 shrink-0 text-lg" aria-hidden="true"></iconify-icon>
          <p data-recovery-status-message>{{ session('status') }}</p>
        </div>

        @if ($errors->has('username'))
          <div data-recovery-error
            class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-800"
            role="alert" aria-live="assertive">
            <iconify-icon icon="mdi:alert-circle-outline" class="mt-0.5 shrink-0 text-lg" aria-hidden="true"></iconify-icon>
            <p data-recovery-error-message>{{ $errors->first('username') }}</p>
          </div>
        @else
          <div data-recovery-error
            class="mb-6 flex hidden items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-800"
            role="alert" aria-live="assertive">
            <iconify-icon icon="mdi:alert-circle-outline" class="mt-0.5 shrink-0 text-lg" aria-hidden="true"></iconify-icon>
            <p data-recovery-error-message></p>
          </div>
        @endif

        <form action="{{ route('password.request.store') }}" method="POST" class="recovery-form space-y-5">
          @csrf
          <div>
            <label for="username" class="mb-2 block text-sm font-semibold text-[#31504c]">Username akun</label>
            <div class="recovery-form-control group relative">
              <iconify-icon icon="mdi:account-outline"
                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 transition-colors group-focus-within:text-[#008f82]"
                aria-hidden="true"></iconify-icon>
              <input
                type="text"
                id="username"
                name="username"
                value="{{ old('username') }}"
                maxlength="50"
                required
                autofocus
                autocomplete="username"
                aria-describedby="username-hint"
                class="w-full rounded-xl border border-[#cbdedb] bg-[#fbfdfc] py-3.5 pl-12 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#007f78] focus:bg-white focus:ring-4 focus:ring-[#00b19c]/10"
                placeholder="Masukkan username">
            </div>
            <p id="username-hint" class="mt-2 text-xs leading-5 text-slate-500">
              Gunakan username yang biasa dipakai untuk login.
            </p>
          </div>

          <button type="submit"
            class="recovery-form-control group inline-flex min-h-12 items-center justify-center gap-2.5 rounded-xl bg-[#007f78] px-5 py-3 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(0,127,120,.16)] transition duration-200 hover:-translate-y-0.5 hover:bg-[#006b70] hover:shadow-[0_12px_24px_rgba(0,107,112,.24)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00385a] disabled:cursor-wait disabled:opacity-80">
            <iconify-icon icon="mdi:loading" class="recovery-submit-spinner animate-spin text-lg" aria-hidden="true"></iconify-icon>
            <span class="recovery-submit-label">Kirim permintaan</span>
            <iconify-icon icon="mdi:arrow-right" class="recovery-submit-icon text-lg transition-transform duration-200 group-hover:translate-x-1"
              aria-hidden="true"></iconify-icon>
          </button>
        </form>

        <div class="mt-8 flex items-start gap-3 border-t border-[#e4eeeb] pt-5">
          <iconify-icon icon="mdi:check-circle-outline" class="mt-0.5 shrink-0 text-lg text-[#007f78]" aria-hidden="true"></iconify-icon>
          <p class="text-xs leading-5 text-slate-500">
            Untuk melindungi akun, admin akan memeriksa permintaan sebelum password sementara diterbitkan.
          </p>
        </div>
      </div>
    </section>

    <aside class="recovery-story relative min-h-[30rem] overflow-hidden px-6 py-6 text-white sm:px-12 sm:py-8 lg:flex lg:h-dvh lg:min-h-0 lg:flex-col lg:justify-between lg:px-10 lg:py-7 xl:px-14"
      aria-label="Tentang InvenTrack">
      <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
        <span class="recovery-watermark">INVENT<br>TRACK</span>
        <span class="absolute inset-0 opacity-[0.08]"
          style="background-image: linear-gradient(rgb(255 255 255 / 35%) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / 35%) 1px, transparent 1px); background-size: 44px 44px;"></span>
      </div>

      <div class="recovery-brand-content mx-auto flex min-h-0 w-full max-w-xl flex-1 flex-col justify-center lg:mx-auto">
        <div class="recovery-hero-icon flex w-full shrink-0 items-center justify-center" aria-hidden="true">
          <img src="{{ asset('assets/icon.svg') }}" alt="">
        </div>
        <div class="recovery-hero-copy">
          <h2 class="text-4xl font-semibold tracking-tight xl:text-[clamp(2.5rem,4.2vw,3.25rem)]">Halo, Pengguna...</h2>
          <p class="mt-3 max-w-lg text-base leading-7 text-white/85 xl:text-lg xl:leading-[1.55]">
            Lupa password? Ajukan permintaan pemulihan akun. Setelah admin menyetujui, Anda bisa masuk dengan password sementara dan membuat password baru.
          </p>
        </div>
      </div>

      <p class="recovery-brand-footer relative z-10 hidden border-t border-white/25 pt-3 text-center text-xs text-white/90 sm:text-sm lg:block">
          © {{ date('Y') }} InvenTrack · Sistem Inventaris Jurusan
      </p>
    </aside>

    <div class="recovery-divider" aria-hidden="true">
      <span class="recovery-divider-accent"></span>
    </div>
  </main>

  <script>
    const recoveryForm = document.querySelector('.recovery-form');
    const recoverySubmitButton = recoveryForm?.querySelector('button[type="submit"]');
    const recoverySubmitLabel = recoveryForm?.querySelector('.recovery-submit-label');
    const recoveryStatus = document.querySelector('[data-recovery-status]');
    const recoveryStatusMessage = recoveryStatus?.querySelector('[data-recovery-status-message]');
    const recoveryError = document.querySelector('[data-recovery-error]');
    const recoveryErrorMessage = recoveryError?.querySelector('[data-recovery-error-message]');
    let recoveryStatusTimeout;

    const hideRecoveryStatus = () => {
      recoveryStatus?.classList.add('hidden');
    };

    const showRecoveryError = message => {
      recoveryStatus?.classList.add('hidden');
      recoveryErrorMessage.textContent = message;
      recoveryError.classList.remove('hidden');
    };

    const showRecoveryStatus = message => {
      window.clearTimeout(recoveryStatusTimeout);
      recoveryError?.classList.add('hidden');
      recoveryStatusMessage.textContent = message;
      recoveryStatus.classList.remove('hidden');
      recoveryStatusTimeout = window.setTimeout(hideRecoveryStatus, 2000);
    };

    if (recoveryStatusMessage?.textContent.trim()) {
      recoveryStatusTimeout = window.setTimeout(hideRecoveryStatus, 2000);
    }

    recoveryForm?.addEventListener('submit', async event => {
      event.preventDefault();
      if (!recoveryForm.reportValidity()) return;
      if (recoveryForm.classList.contains('is-submitting')) return;

      recoveryForm.classList.add('is-submitting');
      recoverySubmitButton.disabled = true;
      recoverySubmitButton.setAttribute('aria-busy', 'true');
      recoverySubmitLabel.textContent = 'Mengirim permintaan...';

      try {
        const response = await fetch(recoveryForm.action, {
          method: recoveryForm.method,
          body: new FormData(recoveryForm),
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });
        const result = await response.json();

        if (!response.ok) {
          showRecoveryError(result.errors?.username?.[0] ?? result.message ?? 'Permintaan gagal dikirim. Silakan coba kembali.');
          return;
        }

        showRecoveryStatus(result.message);
      } catch (error) {
        console.error('Password recovery request failed.', error);
        showRecoveryError('Permintaan gagal dikirim. Periksa koneksi lalu coba kembali.');
      } finally {
        recoveryForm.classList.remove('is-submitting');
        recoverySubmitButton.disabled = false;
        recoverySubmitButton.removeAttribute('aria-busy');
        recoverySubmitLabel.textContent = 'Kirim permintaan';
      }
    });

  </script>
</body>

</html>
