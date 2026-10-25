<aside x-data="{
    open: localStorage.getItem('sidebarOpen') !== 'false'
}" x-init="$watch('open', val => localStorage.setItem('sidebarOpen', val))" :class="open ? 'w-64' : 'w-20'"
  class="admin-sidebar relative z-20 flex h-screen shrink-0 flex-col shadow-xl transition-[width] duration-350 ease-[cubic-bezier(0.22,1,0.36,1)]"
  style="background: linear-gradient(180deg, #14b8a6 0%, #0d9488 30%, #065f6e 60%, #00385A 100%);">

  <!-- Toggle Button -->
  <button @click="open = !open" :aria-expanded="open.toString()"
    :aria-label="open ? 'Ciutkan sidebar' : 'Perluas sidebar'"
    class="absolute -right-3 top-9 z-50 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-white/80 bg-white text-[#00385A] shadow-md transition duration-200 ease-out hover:scale-110 hover:bg-teal-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
    <iconify-icon :icon="open ? 'mdi:chevron-left' : 'mdi:chevron-right'" width="16"></iconify-icon>
  </button>

  <!-- Logo Section -->
  <div class="flex items-center p-5 transition-[padding] duration-300"
    :class="open ? 'justify-start gap-3' : 'justify-center'">
    <div
      class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white shadow-lg transition duration-300 hover:-rotate-3 hover:scale-105">
      <iconify-icon icon="mdi:grid" class="text-teal-500" width="28"></iconify-icon>
    </div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0 -translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
      x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-x-0"
      x-transition:leave-end="opacity-0 -translate-x-2" class="whitespace-nowrap overflow-hidden">
      <h1 class="text-white font-bold text-xl leading-tight tracking-wide">InvenTrack</h1>
      <p class="text-white/80 text-[9px] uppercase tracking-wider font-medium">Sistem Inventaris Jurusan</p>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 px-3 py-2 space-y-1" :class="open ? 'overflow-y-auto' : 'overflow-hidden'">
    @php
      $currentRouteName = request()->route()?->getName();
      $menuItems = [
          ['route' => 'admin.dashboard', 'icon' => 'ant-design:home-filled', 'label' => 'Dashboard'],
          ['route' => 'admin.barang', 'icon' => 'akar-icons:shipping-box-v2', 'label' => 'Data Barang'],
          ['route' => 'admin.kategori', 'icon' => 'mdi:tag-multiple-outline', 'label' => 'Kategori'],
          ['route' => 'admin.ruangan.index', 'icon' => 'boxicons:door-open-alt-filled', 'label' => 'Ruangan'],
          ['route' => 'admin.rak.index', 'icon' => 'material-symbols-light:shelves', 'label' => 'Rak Penyimpanan'],
          ['route' => 'admin.peminjaman', 'icon' => 'ic:baseline-manage-history', 'label' => 'Peminjaman'],
          ['route' => 'admin.akun.index', 'icon' => 'bi:person-fill', 'label' => 'Akun & Hak Akses'],
          ['route' => 'admin.log_import', 'icon' => 'clarity:note-solid', 'label' => 'Log Import'],
      ];

      $currentUser = auth()->user();

      if ($currentUser?->role !== 'admin') {
          if ($currentUser?->canManajemen()) {
              if (! $currentUser->canMeminjamkan()) {
                  $menuItems = array_filter(
                      $menuItems,
                      static fn (array $menu): bool => $menu['route'] !== 'admin.peminjaman',
                  );
              }
          } elseif ($currentUser?->canMeminjamkan()) {
              $menuItems = array_filter(
                  $menuItems,
                  static fn (array $menu): bool => $menu['route'] === 'admin.peminjaman',
              );
          } elseif ($currentUser?->canMelihat()) {
              $menuItems = array_filter(
                  $menuItems,
                  static fn (array $menu): bool => in_array(
                      $menu['route'],
                      ['admin.dashboard', 'admin.barang', 'admin.kategori', 'admin.ruangan.index', 'admin.rak.index'],
                      true,
                  ),
              );
          } else {
              $menuItems = [];
          }

          // Laboran never receives administrator-only account or import-log links.
          $menuItems = array_filter(
              $menuItems,
              static fn (array $menu): bool => ! in_array($menu['route'], ['admin.akun.index', 'admin.log_import'], true),
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
        :class="open ? 'gap-3 px-4 py-3' : 'justify-center px-0 py-3'">

        <iconify-icon icon="{{ $menu['icon'] }}"
          class="shrink-0 transition-transform duration-200 group-hover:scale-110 {{ $isActive ? 'text-[#00385A]' : 'text-white' }}"
          width="22">
        </iconify-icon>

        <span x-show="open" x-transition:enter="transition ease-out duration-200"
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
