@props([
    'title' => 'Dashboard',
    'description' => '',
    'notificationUrl' => '#',
    'profileUrl' => '#',
])

@php
  $routeHeaders = [
      'admin.dashboard' => ['title' => 'Dashboard', 'description' => 'Selamat datang di InvenTrack.'],
      'admin.barang' => ['title' => 'Data Barang', 'description' => 'Kelola data barang, mutasi, dan pemakaian.'],
      'admin.kategori' => ['title' => 'Kategori Inventaris', 'description' => 'Kelola kategori barang inventaris.'],
      'admin.ruangan' => ['title' => 'Data Ruangan', 'description' => 'Kelola data ruangan inventaris.'],
      'admin.rak' => ['title' => 'Rak Penyimpanan', 'description' => 'Kelola lokasi rak penyimpanan barang.'],
      'admin.peminjaman' => [
          'title' => 'Data Peminjaman',
          'description' => 'Kelola peminjaman dan riwayat penggunaan barang.',
      ],
      'admin.akun.index' => [
          'title' => 'Manajemen Akun',
          'description' => 'Kelola akun, peran, dan status akses pengguna.',
      ],
      'admin.log_import' => ['title' => 'Log Import', 'description' => 'Lihat riwayat import data inventaris.'],
  ];
  $routeHeader = $routeHeaders[request()->route()?->getName()] ?? ['title' => 'Dashboard', 'description' => ''];
  $title = $title === 'Dashboard' ? $routeHeader['title'] : $title;
  $description = $description !== '' ? $description : $routeHeader['description'];
  $profileName = auth()->user()?->username ?? 'Admin';
  $profileInitial = strtoupper(substr($profileName, 0, 1));
@endphp

@php
  $segments = request()->segments();
  $slugLabels = [
      'admin' => auth()->user()?->role === 'laboran' ? 'Laboran' : 'Admin',
      'dashboard' => 'Dashboard',
      'barang' => 'Data Barang',
      'kategori' => 'Kategori',
      'ruangan' => 'Data Ruangan',
      'rak' => 'Rak Penyimpanan',
      'peminjaman' => 'Peminjaman',
      'akun' => 'Akun',
      'log-import' => 'Log Import',
  ];
  $roomDetail = request()->routeIs('admin.ruangan.show') ? request()->route('ruangan') : null;
@endphp

<header class="sticky top-0 z-30 -mx-8 mb-8 border-b border-[#D8E6E2] bg-white px-5 py-5 sm:px-8">
  <div class="flex items-center justify-between gap-6">
    <div class="min-w-0">
      <nav
        :aria-hidden="isScrolled"
        :inert="isScrolled"
        :class="isScrolled ? 'mb-0 max-h-0 -translate-y-1 opacity-0' : 'mb-1 max-h-12 translate-y-0 opacity-100'"
        aria-label="Breadcrumb"
        class="flex flex-wrap items-center gap-2 overflow-hidden text-sm transition-[max-height,margin,opacity,transform] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]">
        @foreach ($segments as $index => $segment)
          @php
            $path = implode('/', array_slice($segments, 0, $index + 1));
            $label = $roomDetail && $index === array_key_last($segments)
                ? $roomDetail->nama_ruangan
                : ($slugLabels[$segment] ?? ucfirst(str_replace(['-', '_'], ' ', $segment)));
            $href = $segment === 'admin' ? route('admin.dashboard') : url($path);
          @endphp
          @if ($index > 0)
            <span class="text-slate-300" aria-hidden="true">/</span>
          @endif
          <a href="{{ $href }}"
            class="transition {{ $roomDetail && $index === array_key_last($segments)
                ? 'font-semibold text-[#00785F]'
                : 'font-medium text-[#8AA39E] hover:text-[#007F6B]' }}">
            {{ $label }}
          </a>
        @endforeach
      </nav>
      <h1 class="truncate text-xl font-bold leading-tight text-[#064E3B] sm:text-2xl">{{ $title }}</h1>
      @if ($description !== '')
        <p class="mt-1 text-sm leading-5 text-slate-600">{{ $description }}</p>
      @endif
    </div>

    <div class="flex shrink-0 items-center gap-3">
      <button type="button" @click="$dispatch('admin-sidebar-toggle')"
        :aria-expanded="mobileSidebarOpen.toString()" aria-controls="admin-sidebar"
        aria-label="Buka navigasi" title="Menu navigasi"
        class="flex h-11 w-11 items-center justify-center rounded-full border border-[#C6DAD5] bg-[#F7FAF9] text-[#087F70] transition hover:bg-[#E8F4F1] focus:outline-none focus:ring-2 focus:ring-[#00A995] focus:ring-offset-2 lg:hidden">
        <iconify-icon :icon="mobileSidebarOpen ? 'mdi:close' : 'mdi:menu'" width="22" aria-hidden="true"></iconify-icon>
      </button>
      <a href="{{ $notificationUrl }}" aria-label="Notifikasi" title="Notifikasi"
        class="flex h-11 w-11 items-center justify-center rounded-full border border-[#C6DAD5] bg-[#F7FAF9] text-[#087F70] transition hover:bg-[#E8F4F1] focus:outline-none focus:ring-2 focus:ring-[#00A995] focus:ring-offset-2">
        <iconify-icon icon="mdi:bell" width="22" aria-hidden="true"></iconify-icon>
      </a>
      <div class="relative" x-data="{ profileMenuOpen: false }" @keydown.escape.window="profileMenuOpen = false">
        <button type="button" @click.stop="profileMenuOpen = !profileMenuOpen"
          :aria-expanded="profileMenuOpen.toString()" aria-haspopup="menu" aria-label="Buka profil" title="Profil"
          class="flex h-11 w-11 items-center justify-center rounded-full bg-[#00A995] text-lg font-semibold text-white transition hover:bg-[#008F7E] focus:outline-none focus:ring-2 focus:ring-[#00A995] focus:ring-offset-2">
          {{ $profileInitial }}
        </button>
        <div x-cloak x-show="profileMenuOpen" @click.outside="profileMenuOpen = false"
          x-transition.origin.top.right role="menu"
          class="absolute right-0 top-14 z-[60] w-56 rounded-xl border border-slate-200 bg-white p-2 text-slate-700 shadow-xl">
          <div class="border-b border-slate-100 px-3 py-2">
            <p class="truncate text-sm font-semibold text-slate-900">{{ $profileName }}</p>
            <p class="mt-0.5 text-xs capitalize text-slate-500">{{ auth()->user()?->role }}</p>
          </div>
          <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" role="menuitem"
              class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50">
              <iconify-icon icon="mdi:logout" aria-hidden="true"></iconify-icon>
              Keluar
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>
