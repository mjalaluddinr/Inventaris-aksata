@php
  $currentUser = auth()->user();
  $userInitial = $currentUser
      ? mb_strtoupper(mb_substr($currentUser->first_name ?: $currentUser->username, 0, 1))
      : 'A';
@endphp

<header class="sticky top-0 z-30 border-b border-white/20 bg-gradient-to-r from-[#08776b] via-[#00a88f] to-[#00d7a2] text-white shadow-sm">
  <nav class="mx-auto flex min-h-20 max-w-[1600px] items-center justify-between gap-3 px-4 py-3 sm:gap-5 sm:px-8 lg:px-10">
    <a href="#beranda" class="flex shrink-0 items-center gap-3" aria-label="InvenTrack, beranda">
      <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-white/75 to-[#00d8b0]/70 shadow-inner sm:h-[60px] sm:w-[60px]">
        <iconify-icon icon="mdi:archive-cog-outline" class="text-3xl text-white sm:text-4xl" aria-hidden="true"></iconify-icon>
      </span>
      <span class="hidden leading-tight sm:block">
        <span class="block text-xl font-bold tracking-tight sm:text-2xl">InvenTrack</span>
        <span class="block text-[10px] font-semibold uppercase leading-3 tracking-wide text-white/80 sm:text-xs">Sistem Inventaris<br>Jurusan</span>
      </span>
    </a>

    <div class="flex items-center gap-1 text-xs font-semibold sm:gap-4 sm:text-sm lg:gap-12 lg:text-lg">
      <a href="{{ route('user.beranda') }}" class="rounded-md px-2 py-2 transition hover:bg-white/10">Beranda</a>
      <a href="{{ route('user.beranda', ['page' => 'catalog']) }}" class="rounded-md px-2 py-2 transition hover:bg-white/10">Katalog</a>
      <a href="{{ route('user.beranda', ['page' => 'history', 'status' => 'active']) }}" class="hidden rounded-md px-2 py-2 transition hover:bg-white/10 sm:inline-flex">Riwayat Saya</a>
    </div>

    <div class="flex shrink-0 items-center gap-1.5 sm:gap-4">
      <button type="button" @click="cartOpen = true" class="relative flex h-9 w-9 items-center justify-center rounded-full bg-white/85 text-[#00385a] transition hover:bg-white sm:h-12 sm:w-12" aria-label="Buka keranjang">
        <iconify-icon icon="mdi:cart-outline" class="text-xl sm:text-2xl" aria-hidden="true"></iconify-icon>
        <span x-show="itemCount > 0" x-text="itemCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"></span>
      </button>
      <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/85 text-[#007f78] sm:h-12 sm:w-12" aria-label="Notifikasi">
        <iconify-icon icon="mdi:bell-outline" class="text-xl sm:text-2xl" aria-hidden="true"></iconify-icon>
      </span>
      <div class="relative" x-data="{ profileOpen: false }" @keydown.escape.window="profileOpen = false">
        <button type="button" @click.stop="profileOpen = !profileOpen" :aria-expanded="profileOpen.toString()" aria-haspopup="menu" class="flex h-9 w-9 items-center justify-center rounded-full bg-[#00aa91] text-base font-medium text-white transition hover:scale-105 hover:bg-[#008f82] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:h-12 sm:w-12 sm:text-lg" aria-label="Buka profil">
          {{ $userInitial }}
        </button>
        <div x-cloak x-show="profileOpen" @click.outside="profileOpen = false" x-transition.origin.top.right role="menu" class="absolute right-0 top-12 z-[60] w-56 rounded-xl border border-slate-200 bg-white p-2 text-slate-700 shadow-xl sm:top-14">
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
    </div>
  </nav>
</header>
