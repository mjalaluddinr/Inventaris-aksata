<aside id="admin-sidebar" x-data="{
    open: localStorage.getItem('sidebarOpen') !== 'false'
}" x-init="$watch('open', val => localStorage.setItem('sidebarOpen', val))"
  :class="[(open ? 'lg:w-64' : 'lg:w-20'), mobileSidebarOpen ? 'translate-x-0' : '']"
  class="admin-sidebar fixed inset-y-0 left-0 z-50 flex h-dvh w-72 shrink-0 -translate-x-full flex-col shadow-xl transition-[width,transform] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] lg:relative lg:inset-auto lg:z-20 lg:h-screen lg:translate-x-0"
  style="background: linear-gradient(180deg, #14b8a6 0%, #0d9488 30%, #065f6e 60%, #00385A 100%);">

  <!-- Toggle Button -->
  <button @click="open = !open" :aria-expanded="open.toString()"
    :aria-label="open ? 'Ciutkan sidebar' : 'Perluas sidebar'"
    class="absolute -right-3 top-9 z-50 hidden h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-white/80 bg-white text-[#00385A] shadow-md transition duration-200 ease-out hover:scale-110 hover:bg-teal-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white lg:flex">
    <iconify-icon :icon="open ? 'mdi:chevron-left' : 'mdi:chevron-right'" width="16"></iconify-icon>
  </button>

  <!-- Logo Section -->
  <div class="flex items-center justify-center transition-[padding] duration-300"
    :class="(open || mobileSidebarOpen) ? 'px-3 py-4' : 'p-2'">
    <div class="flex h-16 shrink-0 items-center rounded-2xl bg-white p-1 shadow-lg transition-[width] duration-300"
      :class="(open || mobileSidebarOpen) ? 'w-full gap-3' : 'w-16 justify-center'">
      <span
        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-300 to-teal-500">
        <img src="{{ asset('assets/icon.svg') }}" alt="" class="h-10 w-10 translate-x-0.5 object-contain">
      </span>
      <div x-show="open || mobileSidebarOpen" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 -translate-x-2" class="min-w-0 overflow-hidden">
        <h1 class="whitespace-nowrap text-xl font-bold leading-tight tracking-wide text-[#00385A]">InvenTrack</h1>
        <p class="text-[9px] font-semibold uppercase leading-tight tracking-wider text-[#7f9e9a]">Sistem Inventaris
          Jurusan</p>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
    @php
      $currentRouteName = request()->route()?->getName();
      $menuItems = [
          ['route' => 'admin.dashboard', 'icon' => 'ant-design:home-filled', 'label' => 'Dashboard'],
          ['route' => 'admin.barang', 'icon' => 'akar-icons:shipping-box-v2', 'label' => 'Data Barang'],
          ['route' => 'admin.kategori', 'icon' => 'mdi:tag-multiple-outline', 'label' => 'Kategori'],
          ['route' => 'admin.ruangan.index', 'icon' => 'boxicons:door-open-alt-filled', 'label' => 'Ruangan'],
          ['route' => 'admin.rak.index', 'icon' => 'material-symbols-light:shelves', 'label' => 'Rak Penyimpanan'],
          ['route' => 'admin.log_mutasi', 'icon' => 'mdi:history', 'label' => 'Log Mutasi'],
          ['route' => 'admin.peminjaman', 'icon' => 'ic:baseline-manage-history', 'label' => 'Peminjaman'],
          ['route' => 'admin.akun.index', 'icon' => 'bi:person-fill', 'label' => 'Akun & Hak Akses'],
          ['route' => 'admin.log_import', 'icon' => 'clarity:note-solid', 'label' => 'Log Import'],
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
        $isActive = $currentRouteName === $menu['route'];
      @endphp
      <a href="{{ route($menu['route']) }}" title="{{ $menu['label'] }}"
        aria-current="{{ $isActive ? 'page' : 'false' }}" style="--nav-index: {{ $loop->index }};"
        class="admin-nav-item group relative flex items-center rounded-xl transition-[background-color,box-shadow,transform] duration-200 ease-out
              {{ $isActive ? 'bg-white text-[#00385A] shadow-md shadow-[#00385A]/10 ring-1 ring-white/70' : 'text-white hover:translate-x-1 hover:bg-white/12 hover:shadow-sm' }}"
        :class="(open || mobileSidebarOpen) ? 'gap-3 px-4 py-3' : 'justify-center px-0 py-3'"
        @click="if (window.innerWidth < 1024) mobileSidebarOpen = false">

        <iconify-icon icon="{{ $menu['icon'] }}"
          class="shrink-0 transition-transform duration-200 group-hover:scale-110 {{ $isActive ? 'text-[#00385A]' : 'text-white' }}"
          width="22">
        </iconify-icon>

        <span x-show="open || mobileSidebarOpen" x-transition:enter="transition ease-out duration-200"
          x-transition:enter-start="opacity-0 -translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
          x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-x-0"
          x-transition:leave-end="opacity-0 -translate-x-2"
          class="whitespace-nowrap overflow-hidden font-medium text-sm
                 {{ $isActive ? 'text-[#00385A] font-bold' : 'text-white' }}">
          {{ $menu['label'] }}
        </span>
      </a>
    @endforeach
  </nav>
</aside>
