<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00385A">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>Ganti Password - InvenTrack</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
  class="min-h-screen flex items-center justify-center p-5 relative overflow-hidden font-sans antialiased bg-gradient-to-br from-[#00385A] via-[#005a8c] to-[#00B19C]">
  <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
    <div class="absolute -top-32 -right-32 h-96 w-96 rounded-full bg-[#00B19C]/40 blur-3xl"></div>
    <div class="absolute -bottom-40 -left-32 h-[30rem] w-[30rem] rounded-full bg-[#5EEAD4]/30 blur-3xl"></div>
  </div>

  <main class="relative z-10 w-full max-w-lg rounded-3xl border border-white/30 bg-white/95 p-7 shadow-2xl sm:p-10">
    <header class="mb-8 text-center">
      <div
        class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#00385A] to-[#00B19C] text-white shadow-lg">
        <iconify-icon icon="mdi:lock-reset" class="text-4xl" aria-hidden="true"></iconify-icon>
      </div>
      <h1 class="text-2xl font-extrabold tracking-tight text-[#00385A] sm:text-3xl">Buat password baru</h1>
      <p class="mx-auto mt-3 max-w-sm text-sm leading-6 text-slate-600">
        Untuk melanjutkan, ganti password sementara Anda dengan password baru.
      </p>
    </header>

    @if (session('info'))
      <div class="mb-5 flex items-start gap-3 rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        role="status">
        <iconify-icon icon="mdi:information-outline" class="mt-0.5 shrink-0 text-lg" aria-hidden="true"></iconify-icon>
        <p>{{ session('info') }}</p>
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-5 flex items-start gap-3 rounded-xl border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm text-red-800"
        role="alert">
        <iconify-icon icon="mdi:alert-circle-outline" class="mt-0.5 shrink-0 text-lg" aria-hidden="true"></iconify-icon>
        <ul class="space-y-1">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
      @csrf
      @method('PUT')

      <div>
        <label for="current_password" class="mb-2 block text-sm font-semibold text-slate-700">Password saat ini</label>
        <input
          type="password"
          id="current_password"
          name="current_password"
          required
          autocomplete="current-password"
          autofocus
          class="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-[#00B19C] focus:bg-white focus:ring-4 focus:ring-[#00B19C]/10"
          placeholder="Masukkan password saat ini">
        @error('current_password')
          <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label for="new_password" class="mb-2 block text-sm font-semibold text-slate-700">Password baru</label>
        <input
          type="password"
          id="new_password"
          name="new_password"
          required
          minlength="8"
          autocomplete="new-password"
          class="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-[#00B19C] focus:bg-white focus:ring-4 focus:ring-[#00B19C]/10"
          placeholder="Minimal 8 karakter">
        <p class="mt-2 text-xs text-slate-500">Gunakan minimal 8 karakter dan jangan gunakan password lama.</p>
        @error('new_password')
          <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label for="new_password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">
          Konfirmasi password baru
        </label>
        <input
          type="password"
          id="new_password_confirmation"
          name="new_password_confirmation"
          required
          minlength="8"
          autocomplete="new-password"
          class="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-[#00B19C] focus:bg-white focus:ring-4 focus:ring-[#00B19C]/10"
          placeholder="Ketik ulang password baru">
      </div>

      <button
        type="submit"
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#00B19C] to-[#009a87] px-4 py-3.5 font-semibold text-white shadow-lg shadow-emerald-900/15 transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00385A]">
        <span>Simpan password baru</span>
        <iconify-icon icon="mdi:arrow-right" aria-hidden="true"></iconify-icon>
      </button>
    </form>

    <p class="mt-6 text-center text-xs leading-5 text-slate-500">
      Password baru harus berbeda dari password saat ini.
    </p>
  </main>
</body>

</html>
