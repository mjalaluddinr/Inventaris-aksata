<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00385A">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>Pengajuan Lupa Password - InvenTrack</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
  class="flex min-h-screen items-center justify-center bg-[#e8f4f2] px-5 py-10 font-sans antialiased text-slate-800">
  <main class="w-full max-w-xl overflow-hidden rounded-2xl border border-[#cbdedb] bg-white shadow-[0_24px_70px_rgba(0,56,90,0.12)]">
    <div class="h-2 bg-gradient-to-r from-[#00385a] via-[#007f78] to-[#00b19c]"></div>
    <section class="p-7 sm:p-10">
      <a href="{{ route('login') }}"
        class="mb-9 inline-flex items-center gap-2 rounded text-sm font-semibold text-[#007f78] transition hover:text-[#00385a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#008f82]">
        <iconify-icon icon="mdi:arrow-left" aria-hidden="true"></iconify-icon>
        Kembali ke login
      </a>

      <div class="mb-7 flex items-start gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#e6f5f1] text-[#007f78]">
          <iconify-icon icon="mdi:lock-question" class="text-2xl" aria-hidden="true"></iconify-icon>
        </span>
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-[#00385a]">Ajukan reset password</h1>
          <p class="mt-2 max-w-md text-sm leading-6 text-slate-600">
            Masukkan username akun. Admin akan melihat permintaan Anda dan menyiapkan password sementara.
          </p>
        </div>
      </div>

      @if (session('status'))
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
          role="status">
          <iconify-icon icon="mdi:check-circle-outline" class="mt-0.5 shrink-0 text-lg" aria-hidden="true"></iconify-icon>
          <p>{{ session('status') }}</p>
        </div>
      @endif

      <form action="{{ route('password.request.store') }}" method="POST" class="space-y-5">
        @csrf
        <div>
          <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
          <input
            type="text"
            id="username"
            name="username"
            value="{{ old('username') }}"
            maxlength="50"
            required
            autofocus
            autocomplete="username"
            aria-describedby="username-hint{{ $errors->has('username') ? ' username-error' : '' }}"
            @class([
                'w-full rounded-lg border bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-4 focus:ring-[#00b19c]/10',
                'border-rose-400 focus:border-rose-500' => $errors->has('username'),
                'border-slate-300 focus:border-[#007f78]' => ! $errors->has('username'),
            ])>
          <p id="username-hint" class="mt-2 text-xs leading-5 text-slate-500">
            Gunakan username yang sama dengan yang dipakai untuk login.
          </p>
          @error('username')
            <p id="username-error" class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</p>
          @enderror
        </div>

        <button type="submit"
          class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#007f78] px-4 py-3 font-semibold text-white transition hover:bg-[#006b70] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00385a]">
          Kirim permintaan
          <iconify-icon icon="mdi:arrow-right" aria-hidden="true"></iconify-icon>
        </button>
      </form>

      <p class="mt-6 border-t border-slate-100 pt-5 text-xs leading-5 text-slate-500">
        Setelah permintaan diproses, hubungi admin untuk menerima password sementara. Anda akan diminta menggantinya saat login.
      </p>
    </section>
  </main>
</body>

</html>
