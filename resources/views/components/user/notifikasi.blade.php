@props(['notifications'])

<div class="relative" x-data="{ notificationsOpen: false }" @keydown.escape.window="notificationsOpen = false">
  <button type="button" @click.stop="notificationsOpen = !notificationsOpen"
    :aria-expanded="notificationsOpen.toString()" aria-haspopup="dialog" aria-label="Buka notifikasi"
    class="relative flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#008b7a] shadow-sm transition hover:bg-[#e6f8f3] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:h-12 sm:w-12">
    <iconify-icon icon="mdi:bell-outline" class="text-xl sm:text-2xl" aria-hidden="true"></iconify-icon>
    @if ($notifications->isNotEmpty())
      <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"
        aria-hidden="true">
        {{ $notifications->count() }}
      </span>
    @endif
  </button>
  <section x-cloak x-show="notificationsOpen" @click.outside="notificationsOpen = false"
    x-transition.origin.top.right role="dialog" aria-label="Notifikasi"
    class="absolute right-0 top-full z-[70] mt-3 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-[#dce9e6] bg-white text-[#153c36] shadow-[0_18px_48px_rgba(13,57,48,0.2)]">
    <header class="flex items-center justify-between border-b border-[#dce9e6] px-4 py-3.5">
      <h2 class="text-sm font-bold text-[#153c36]">Notifikasi</h2>
      <button type="button" @click="notificationsOpen = false"
        class="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#008b7a]"
        aria-label="Tutup notifikasi">
        <iconify-icon icon="mdi:close" class="text-lg" aria-hidden="true"></iconify-icon>
      </button>
    </header>
    @if ($notifications->isNotEmpty())
      <ul class="max-h-[min(65vh,24rem)] overflow-y-auto px-4">
        @foreach ($notifications as $notification)
          @php
            $notificationStyle = match ($notification['type']) {
                'confirmed' => 'bg-emerald-100 text-emerald-700',
                'rejected' => 'bg-rose-100 text-rose-700',
                'due' => 'bg-amber-100 text-amber-800',
                default => 'bg-sky-100 text-sky-700',
            };
          @endphp
          <li class="border-b border-[#e5eeeb] last:border-b-0">
            {{-- ASVS 1.2.1: escaped Blade output keeps persisted notification text in its HTML context. --}}
            <a href="{{ $notification['url'] }}"
              class="flex items-start gap-3 py-3.5 transition hover:bg-[#f7fbfa] focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-[#008b7a]">
              <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $notificationStyle }}">
                <iconify-icon icon="{{ $notification['icon'] }}" class="text-base" aria-hidden="true"></iconify-icon>
              </span>
              <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold leading-5 text-slate-900">{{ $notification['title'] }}</span>
                <span class="mt-1 block text-xs font-medium text-[#66928a]">{{ $notification['time'] }}</span>
              </span>
            </a>
          </li>
        @endforeach
      </ul>
    @else
      <div class="px-5 py-8 text-center">
        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#e9faf5] text-[#008b7a]">
          <iconify-icon icon="mdi:bell-check-outline" class="text-xl" aria-hidden="true"></iconify-icon>
        </span>
        <p class="mt-3 text-sm font-semibold text-slate-800">Belum ada notifikasi</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">Pembaruan pengajuan dan pengingat pengembalian akan muncul di sini.</p>
      </div>
    @endif
  </section>
</div>