<aside id="admin-sidebar" x-data="{
    open: document.documentElement.dataset.adminSidebarCollapsed !== 'true'
}" x-init="$watch('open', val => {
    localStorage.setItem('sidebarOpen', val);
    document.documentElement.dataset.adminSidebarCollapsed = val ? 'false' : 'true';
})"
  :class="[(open ? 'lg:w-64' : 'lg:w-20'), mobileSidebarOpen ? 'translate-x-0' : '']"
  class="admin-sidebar fixed inset-y-0 left-0 z-50 flex h-dvh w-72 shrink-0 -translate-x-full flex-col shadow-xl transition-[width,transform] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] lg:relative lg:inset-auto lg:z-20 lg:h-screen lg:translate-x-0"
  style="background: linear-gradient(180deg, #14b8a6 0%, #0d9488 30%, #065f6e 60%, #00385A 100%);">

  <!-- Logo Section -->
  <div class="admin-sidebar-logo-section flex items-center justify-center transition-[padding] duration-300"
    :class="(open || mobileSidebarOpen) ? 'px-3 py-4' : 'p-2'">
    <div
      class="admin-sidebar-logo-card group relative flex h-16 shrink-0 items-center rounded-2xl bg-white p-1 shadow-lg transition-[width] duration-300"
      :class="(open || mobileSidebarOpen) ? 'w-full gap-3' : 'w-16 justify-center'">
      <span
        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-300 to-teal-500">
        <img src="{{ asset('assets/icon.svg') }}" alt="" class="h-10 w-10 translate-x-0.5 object-contain">
      </span>
      <div x-cloak x-show="open || mobileSidebarOpen" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 -translate-x-2" class="admin-sidebar-copy min-w-0 overflow-hidden">
        <h1 class="whitespace-nowrap text-xl font-bold leading-tight tracking-wide text-[#00385A]">InvenTrack</h1>
        <p class="text-[9px] font-semibold uppercase leading-tight tracking-wider text-[#7f9e9a]">Sistem Inventaris
          Jurusan</p>
      </div>
      <button x-cloak x-show="open" @click.stop="open = false" :aria-expanded="open.toString()"
        aria-label="Ciutkan sidebar" title="Ciutkan sidebar"
        class="admin-sidebar-collapse group/collapse hidden h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-white/15 bg-gradient-to-br from-[#008b7a] to-[#00385A] text-white shadow-md shadow-[#00385A]/20 transition-[transform,box-shadow] duration-200 hover:-translate-x-0.5 hover:shadow-lg hover:shadow-[#00385A]/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00bfa5] lg:flex">
        <iconify-icon icon="mdi:chevron-double-left" width="20"
          class="transition-transform duration-200 group-hover/collapse:-translate-x-0.5"
          aria-hidden="true"></iconify-icon>
      </button>
      <button x-cloak x-show="!open" @click.stop="open = true" aria-expanded="false" aria-label="Perluas sidebar"
        title="Perluas sidebar"
        class="admin-sidebar-expand absolute inset-1 z-10 hidden cursor-pointer items-center justify-center rounded-xl border border-white/70 bg-gradient-to-br from-white/95 to-[#d7f7ee]/95 text-[#00505B] opacity-0 shadow-inner transition-[opacity,transform,background-color] duration-200 group-hover:scale-[1.03] group-hover:from-[#b8f5e5] group-hover:to-[#69dfbd] group-hover:opacity-100 group-focus-within:from-[#b8f5e5] group-focus-within:to-[#69dfbd] group-focus-within:opacity-100 focus-visible:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 lg:flex">
        <iconify-icon icon="mdi:chevron-double-right" width="23" class="text-[#008b7a]" aria-hidden="true"></iconify-icon>
      </button>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
    @php
      $currentRouteName = request()->route()?->getName();
      $menuItems = [
          ['route' => 'admin.dashboard', 'icon' => 'mdi:home', 'label' => 'Dashboard'],
          ['route' => 'admin.barang', 'icon' => 'mdi:package-variant-closed', 'label' => 'Data Barang'],
          ['route' => 'admin.kategori', 'icon' => 'mdi:tag-multiple-outline', 'label' => 'Kategori'],
          ['route' => 'admin.ruangan.index', 'icon' => 'boxicons:door-open-alt-filled', 'label' => 'Ruangan'],
          ['route' => 'admin.rak.index', 'icon' => 'material-symbols:shelves', 'label' => 'Rak Penyimpanan'],
          ['route' => 'admin.log_mutasi', 'icon' => 'mdi:history', 'label' => 'Log Mutasi'],
          ['route' => 'admin.peminjaman', 'icon' => 'mdi:clipboard-text-clock-outline', 'label' => 'Peminjaman'],
          ['route' => 'admin.akun.index', 'icon' => 'mdi:account-multiple', 'label' => 'Akun & Hak Akses'],
          ['route' => 'admin.log_import', 'icon' => 'mdi:file-import-outline', 'label' => 'Log Import'],
      ];

      $currentUser = auth()->user();

      if ($currentUser?->role !== 'admin') {
          if ($currentUser?->canManajemen()) {
              if (!$currentUser->canMeminjamkan()) {
                  $menuItems = array_filter(
                      $menuItems,
                      static fn(array $menu): bool => $menu['route'] !== 'admin.peminjaman',
                  );
              }
          } elseif ($currentUser?->canMeminjamkan()) {
              $menuItems = array_filter(
                  $menuItems,
                  static fn(array $menu): bool => $menu['route'] === 'admin.peminjaman',
              );
          } elseif ($currentUser?->canMelihat()) {
              $menuItems = array_filter(
                  $menuItems,
                  static fn(array $menu): bool => in_array(
                      $menu['route'],
                      ['admin.dashboard', 'admin.barang', 'admin.kategori', 'admin.ruangan.index', 'admin.rak.index'],
                      true,
                  ),
              );
          } else {
              $menuItems = [];
          }

          // Account management and logs are administrator-only.
          $menuItems = array_filter(
              $menuItems,
              static fn(array $menu): bool => !in_array(
                  $menu['route'],
                  ['admin.akun.index', 'admin.log_import', 'admin.log_mutasi'],
                  true,
              ),
          );
      }
    @endphp

    @foreach ($menuItems as $menu)
      @php
        $isActive =
            $currentRouteName === $menu['route'] ||
            ($menu['route'] === 'admin.ruangan.index' && request()->routeIs('admin.ruangan.*')) ||
            ($menu['route'] === 'admin.rak.index' && request()->routeIs('admin.rak.*'));
      @endphp
      <a href="{{ route($menu['route']) }}" title="{{ $menu['label'] }}"
        aria-current="{{ $isActive ? 'page' : 'false' }}"
        class="admin-nav-link group relative flex items-center rounded-xl transition-[background-color,box-shadow,transform] duration-200 ease-out
              {{ $isActive ? 'bg-white text-[#00785F] shadow-md shadow-[#00385A]/10 ring-1 ring-white/70' : 'text-white hover:translate-x-1 hover:bg-white/12 hover:shadow-sm' }}"
        :class="(open || mobileSidebarOpen) ? 'gap-3 px-4 py-3' : 'justify-center px-0 py-3'"
        style="--nav-index: {{ $loop->index }}" @click="if (window.innerWidth < 1024) mobileSidebarOpen = false">

        <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-visible">
          <iconify-icon icon="{{ $menu['icon'] }}"
            class="block h-[22px] w-[22px] shrink-0 overflow-visible transition-transform duration-200 group-hover:scale-110 {{ $isActive ? 'text-[#00785F]' : 'text-white' }}"
            width="22" height="22" aria-hidden="true">
          </iconify-icon>
        </span>

        <span x-cloak x-show="open || mobileSidebarOpen" x-transition:enter="transition ease-out duration-300"
          x-transition:enter-start="opacity-0 -translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
          x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-x-0"
          x-transition:leave-end="opacity-0 -translate-x-2"
          class="admin-sidebar-copy whitespace-nowrap overflow-hidden font-medium text-sm
                 {{ $isActive ? 'text-[#00785F] font-bold' : 'text-white' }}">
          {{ $menu['label'] }}
        </span>
      </a>
    @endforeach
  </nav>
</aside>
