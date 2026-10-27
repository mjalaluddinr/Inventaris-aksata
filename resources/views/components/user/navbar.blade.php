@php
  $currentUser = auth()->user();
  $userInitial = $currentUser
      ? mb_strtoupper(mb_substr($currentUser->first_name ?: $currentUser->username, 0, 1))
      : 'A';
@endphp

<header
  x-data="{
    isScrolled: window.scrollY > 10,
    mobileMenuOpen: false,
    init() {
      this.handleScroll();
    },
    handleScroll() {
      this.isScrolled = window.scrollY > 10;
    }
  }"
  @scroll.window.passive="handleScroll()"
  @keydown.escape.window="mobileMenuOpen = false"
  :class="[
    isScrolled ? 'border-b border-white/15 text-white shadow-md' : 'border-b border-transparent text-white shadow-none',
  ]"
  :style="isScrolled ? 'background: linear-gradient(90deg, #008b7a, #00d5a0)' : 'background: transparent'"
  class="fixed inset-x-0 top-0 z-50 transition-[height,transform,background-color,border-color,box-shadow] duration-300 ease-out"
>
  <nav class="mx-auto flex max-w-[1600px] items-center justify-between gap-2 px-3 transition-[height] duration-300 sm:gap-5 sm:px-8 lg:px-10"
    :class="isScrolled ? 'h-20' : 'h-[88px]'">
    <a href="{{ route('user.beranda') }}" class="flex min-w-0 shrink items-center gap-2 sm:gap-3" aria-label="InvenTrack, Sistem Inventaris Jurusan">
      <span class="flex shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-white/75 to-[#00d8b0]/70 shadow-inner transition-[width,height] duration-300"
        :class="isScrolled ? 'h-12 w-12' : 'h-14 w-14'">
        <iconify-icon icon="mdi:archive-cog-outline" class="text-2xl text-white sm:text-3xl" aria-hidden="true"></iconify-icon>
      </span>
      <span class="min-w-0 leading-tight">
        <span class="block truncate text-[11px] font-bold tracking-wide min-[380px]:text-xs sm:text-sm md:text-base">InvenTrack</span>
        <span class="block max-w-[9rem] text-[8px] font-medium leading-tight text-white/85 min-[380px]:max-w-[12rem] min-[380px]:text-[9px] sm:max-w-none sm:text-[10px] md:text-xs">SISTEM INVENTARIS JURUSAN</span>
      </span>
    </a>

    <div class="hidden items-center gap-1 text-sm font-semibold md:flex lg:gap-6 lg:text-base">
      <a href="{{ route('user.beranda') }}" class="rounded-md px-2 py-2 transition hover:bg-white/10">Beranda</a>
      <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="rounded-md px-2 py-2 transition hover:bg-white/10">Katalog</a>
      <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'active']) }}" class="rounded-md px-2 py-2 transition hover:bg-white/10">Riwayat Saya</a>
    </div>

    <div class="flex shrink-0 items-center gap-1 min-[380px]:gap-1.5 sm:gap-3">
      <button type="button" @click="cartOpen = true" class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[#c6e7f2] text-[#064765] transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:h-12 sm:w-12" aria-label="Buka keranjang">
        <iconify-icon icon="mdi:cart" class="text-xl sm:text-2xl" aria-hidden="true"></iconify-icon>
        <span x-show="itemCount > 0" x-text="itemCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"></span>
      </button>
      <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#008b7a] shadow-sm sm:h-12 sm:w-12" aria-label="Notifikasi">
        <iconify-icon icon="mdi:bell" class="text-xl sm:text-2xl" aria-hidden="true"></iconify-icon>
      </span>
      <div class="relative" x-data="{ profileOpen: false }" @keydown.escape.window="profileOpen = false">
        <button type="button" @click.stop="profileOpen = !profileOpen" :aria-expanded="profileOpen.toString()" aria-haspopup="menu" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-sm font-medium text-white transition hover:bg-white/25 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:h-10 sm:w-10 sm:text-base" aria-label="Buka profil">
          {{ $userInitial }}
        </button>
        <div x-cloak x-show="profileOpen" @click.outside="profileOpen = false" x-transition.origin.top.right role="menu" class="absolute right-0 top-10 z-[60] w-[min(14rem,calc(100vw-1.5rem))] rounded-xl border border-slate-200 bg-white p-2 text-slate-700 shadow-xl sm:top-12">
          <div class="border-b border-slate-100 px-3 py-2">
            <p class="truncate text-sm font-semibold text-slate-900">{{ $currentUser?->first_name ? trim($currentUser->first_name.' '.$currentUser->last_name) : ($currentUser?->username ?? 'Pengguna') }}</p>
            <p class="mt-0.5 text-xs capitalize text-slate-500">{{ $currentUser?->role ?? 'Pengunjung' }}</p>
          </div>
          @auth
            <a href="{{ route('password.change') }}" class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition hover:bg-slate-50"><iconify-icon icon="mdi:cog-outline" aria-hidden="true"></iconify-icon> Pengaturan akun</a>
            <form action="{{ route('logout') }}" method="POST">
              @csrf
              <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50"><iconify-icon icon="mdi:logout" aria-hidden="true"></iconify-icon> Keluar</button>
            </form>
          @else
            <a href="{{ route('login.index') }}" class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition hover:bg-slate-50">Masuk</a>
          @endauth
        </div>
      </div>
      <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-controls="user-mobile-menu" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/40 bg-white/15 text-white transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white md:hidden" aria-label="Buka menu navigasi">
        <iconify-icon :icon="mobileMenuOpen ? 'mdi:close' : 'mdi:menu'" class="text-xl" aria-hidden="true"></iconify-icon>
      </button>
    </div>
  </nav>
  <div id="user-mobile-menu" x-cloak x-show="mobileMenuOpen" x-transition.origin.top class="absolute inset-x-0 top-full border-b border-slate-200 bg-white/95 px-4 pb-4 pt-2 text-[#153c36] shadow-lg backdrop-blur-md md:hidden">
    <nav class="mx-auto flex max-w-[1600px] flex-col gap-1" aria-label="Navigasi utama">
      <a href="{{ route('user.beranda') }}" @click="mobileMenuOpen = false" class="rounded-lg px-4 py-3 text-sm font-semibold transition hover:bg-[#e6f8f3]">Beranda</a>
      <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" @click="mobileMenuOpen = false" class="rounded-lg px-4 py-3 text-sm font-semibold transition hover:bg-[#e6f8f3]">Katalog</a>
      <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'active']) }}" @click="mobileMenuOpen = false" class="rounded-lg px-4 py-3 text-sm font-semibold transition hover:bg-[#e6f8f3]">Riwayat Saya</a>
    </nav>
  </div>
</header>
