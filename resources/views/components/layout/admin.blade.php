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
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icon.svg') }}">
  <title>{{ $title }}</title>
  <script>
    try {
      document.documentElement.dataset.adminSidebarCollapsed =
        localStorage.getItem('sidebarOpen') === 'false' ? 'true' : 'false';
    } catch (error) {
      document.documentElement.dataset.adminSidebarCollapsed = 'false';
      console.error('Unable to restore the admin sidebar state.', error);
    }

  </script>
  @fonts('poppins')
  <style>
    [x-cloak] {
      display: none !important;
    }

    @media (min-width: 1024px) {
      html[data-admin-sidebar-collapsed='true'] #admin-sidebar {
        width: 5rem;
      }

      html[data-admin-sidebar-collapsed='false'] #admin-sidebar {
        width: 16rem;
      }

      html[data-admin-sidebar-collapsed='true'] #admin-sidebar .admin-sidebar-logo-section {
        padding: 0.5rem;
      }

      html[data-admin-sidebar-collapsed='true'] #admin-sidebar .admin-sidebar-logo-card {
        width: 4rem;
        justify-content: center;
      }

      html[data-admin-sidebar-collapsed='true'] #admin-sidebar .admin-sidebar-copy,
      html[data-admin-sidebar-collapsed='true'] #admin-sidebar .admin-sidebar-collapse {
        display: none;
      }

      html[data-admin-sidebar-collapsed='true'] #admin-sidebar .admin-sidebar-expand {
        display: flex;
      }

      html[data-admin-sidebar-collapsed='false'] #admin-sidebar .admin-sidebar-expand {
        display: none;
      }

      html[data-admin-sidebar-collapsed='true'] #admin-sidebar .admin-nav-link {
        justify-content: center;
        padding-right: 0;
        padding-left: 0;
      }
    }
  </style>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100" data-admin-field-indicators>
  <x-auth.session-notice />
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
    <div class="pointer-events-auto relative flex items-start gap-3 overflow-hidden rounded-xl border p-4 pb-5 text-white shadow-[0_16px_48px_rgba(2,25,41,0.24)]"
      :class="{
        'border-rose-700 bg-rose-600': type === 'error',
        'border-emerald-700 bg-emerald-600': type === 'success',
        'border-sky-700 bg-sky-600': type === 'info',
        'border-amber-700 bg-amber-500': type === 'warning'
      }">
      <iconify-icon
        :icon="type === 'error' ? 'mdi:close-circle' : (type === 'warning' ? 'mdi:alert-circle' : (type === 'info' ? 'mdi:information' : 'mdi:check-circle'))"
        class="mt-0.5 shrink-0 text-white"
        width="22" aria-hidden="true"></iconify-icon>
      <div class="min-w-0 flex-1">
        <p class="font-semibold" x-text="title"></p>
        <p class="mt-1 break-words text-sm text-white/90">
          <template x-for="(part, index) in message" :key="index">
            <span :class="part.bold ? 'font-bold text-white' : ''" x-text="part.text"></span>
          </template>
        </p>
        <button x-cloak x-show="actionLabel && onAction" type="button" @click="activateAction()"
          class="mt-3 inline-flex items-center gap-1 rounded-md bg-white/15 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-white/25 focus:outline-none focus:ring-2 focus:ring-white/80"
          x-text="actionLabel"></button>
      </div>
      <button type="button" @click="dismiss()"
        class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-white/80 transition hover:bg-white/15 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/80"
        aria-label="Tutup notifikasi">
        <iconify-icon icon="mdi:close" width="18" aria-hidden="true"></iconify-icon>
      </button>
      <span x-ref="progress" class="absolute inset-x-0 bottom-0 h-1 origin-left bg-white/70"
        aria-hidden="true"></span>
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
        @if (!request()->routeIs('admin.barang*', 'admin.rak.*', 'admin.kategori*', 'admin.ruangan.*', 'admin.akun.*'))
          @if (session('success') && session('success') !== 'Login berhasil!')
            <span class="hidden" x-init="window.showToast(@js(session('success')), 'success')"></span>
          @elseif (session('error'))
            <span class="hidden" x-init="window.showToast(@js(session('error')), 'error')"></span>
          @endif
        @endif
        {{ $slot }}
      </div>
    </main>
  </div>
</body>

</html>
