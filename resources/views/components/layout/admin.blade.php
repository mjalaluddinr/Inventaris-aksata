@props([
    'title' => 'InvenTrack',
    'headerTitle' => 'Dashboard',
    'headerDescription' => '',
])

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100">
  <div x-data="globalToast" x-cloak x-show="visible"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-x-4 opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="translate-x-4 opacity-0"
    class="pointer-events-none fixed right-4 top-24 z-[120] w-[calc(100%-2rem)] max-w-md sm:right-6"
    :role="type === 'error' ? 'alert' : 'status'"
    :aria-live="type === 'error' ? 'assertive' : 'polite'" aria-atomic="true">
    <div class="pointer-events-auto flex items-start gap-3 rounded-xl border border-l-4 bg-white p-4 shadow-[0_16px_48px_rgba(2,25,41,0.18)]"
      :class="{
        'border-rose-200 border-l-rose-500': type === 'error',
        'border-emerald-200 border-l-emerald-500': type === 'success',
        'border-sky-200 border-l-sky-500': type === 'info',
        'border-amber-200 border-l-amber-500': type === 'warning'
      }">
      <iconify-icon
        :icon="type === 'error' ? 'mdi:close-circle' : (type === 'warning' ? 'mdi:alert-circle' : (type === 'info' ? 'mdi:information' : 'mdi:check-circle'))"
        class="mt-0.5 shrink-0"
        :class="{
          'text-rose-600': type === 'error',
          'text-emerald-600': type === 'success',
          'text-sky-600': type === 'info',
          'text-amber-600': type === 'warning'
        }"
        width="22" aria-hidden="true"></iconify-icon>
      <div class="min-w-0 flex-1">
        <p class="font-semibold text-slate-900" x-text="title"></p>
        <p class="mt-1 break-words text-sm text-slate-600">
          <template x-for="(part, index) in message" :key="index">
            <span :class="part.bold ? 'font-bold text-slate-800' : ''" x-text="part.text"></span>
          </template>
        </p>
      </div>
      <button type="button" @click="dismiss()"
        class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400"
        aria-label="Tutup notifikasi">
        <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
      </button>
    </div>
  </div>
  <div x-data="{ mobileSidebarOpen: false }"
    @admin-sidebar-toggle="mobileSidebarOpen = !mobileSidebarOpen"
    @keydown.escape.window="mobileSidebarOpen = false"
    @resize.window="if (window.innerWidth >= 1024) mobileSidebarOpen = false"
    class="flex h-screen overflow-hidden">
    <div x-cloak x-show="mobileSidebarOpen" x-transition.opacity
      @click="mobileSidebarOpen = false"
      class="fixed inset-0 z-40 bg-slate-950/45 lg:hidden"
      aria-hidden="true"></div>
    <x-admin.sidebar />
    <main x-data="{ isScrolled: false }" @scroll.passive="isScrolled = $event.currentTarget.scrollTop > 10"
      class="min-w-0 flex-1 overflow-y-auto px-8 pb-8 pt-0">
      <div class="admin-page-enter min-w-0">
        <x-admin.header :title="$headerTitle" :description="$headerDescription" />
        {{-- ASVS 16.5.1: Import feedback is handled by the sanitized account-page toast. --}}
        @if (session('success') && session('success') !== 'Login berhasil!' && !session()->has('import_log_id') && !request()->routeIs('admin.barang*', 'admin.rak.*', 'admin.kategori*', 'admin.peminjaman*', 'admin.ruangan.*', 'admin.akun.*'))
          <div class="mb-6 flex items-center gap-3 rounded-xl border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status">
            <iconify-icon icon="mdi:check-circle-outline" class="shrink-0 text-xl" aria-hidden="true"></iconify-icon>
            <span>{{ session('success') }}</span>
          </div>
        @endif
        {{ $slot }}
      </div>
    </main>
  </div>
</body>

</html>
