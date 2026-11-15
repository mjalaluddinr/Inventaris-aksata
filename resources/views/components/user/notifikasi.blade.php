@php
  $notificationUser = request()->user();
  $notificationHistoryUrl = in_array($notificationUser?->role, ['admin', 'laboran'], true)
      ? route('admin.notifications.detail')
      : route('notifications.detail');
@endphp
<div
  x-data="notificationDropdown"
  data-count-url="{{ route('notifications.count') }}"
  data-read-url="{{ route('notifications.read') }}"
  data-history-url="{{ $notificationHistoryUrl }}"
  data-csrf-token="{{ csrf_token() }}"
  class="relative"
  @keydown.escape.window="open = false"
>
  <button
    type="button"
    @click.stop="toggle()"
    :aria-expanded="open.toString()"
    aria-haspopup="dialog"
    :aria-label="count > 0 ? `Notifikasi, ${count} belum dibaca` : 'Notifikasi'"
    class="relative flex h-11 w-11 items-center justify-center rounded-full border border-[#8FAAA4] bg-[#F4F6F5] text-[#00785F] transition hover:bg-[#E9F3F0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00785F]"
  >
    <iconify-icon icon="famicons:notifications" width="23" aria-hidden="true"></iconify-icon>
    <span
      x-cloak
      x-show="notificationAvailable && count > 0"
      x-text="count > 99 ? '99+' : count"
      aria-live="polite"
      class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#B7EADD] px-1 text-[10px] font-bold leading-none text-[#006B55] ring-2 ring-white"
    ></span>
  </button>

  <section
    x-cloak
    x-show="open"
    @click.outside="open = false"
    x-transition.origin.top.right
    role="dialog"
    aria-label="Notifikasi"
    class="fixed left-2 right-2 top-[5.5rem] z-[70] max-h-[calc(100dvh-6rem)] overflow-hidden rounded-xl border border-[#dce9e6] bg-white shadow-[0_18px_48px_rgba(13,57,48,0.2)] sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-3 sm:max-h-[min(32rem,calc(100vh-7rem))] sm:w-[min(22rem,calc(100vw-1.5rem))]"
  >
    <div class="flex items-center justify-between border-b border-[#e7efed] px-4 py-3">
      <h2 class="text-sm font-bold text-[#064E3B]">Notifikasi</h2>
      <span class="text-xs text-slate-500">Terbaru</span>
    </div>

    <div class="max-h-[calc(100dvh-12rem)] overflow-y-auto sm:max-h-[min(24rem,calc(100vh-14rem))]">
      <div x-show="loading" role="status" aria-label="Status notifikasi" aria-busy="true"
        class="flex justify-center px-4 py-6">
        <iconify-icon icon="mdi:loading" class="animate-spin text-xl text-[#008b7a]" aria-hidden="true"></iconify-icon>
      </div>
      <p x-show="error" x-text="error" role="status" class="px-4 py-6 text-center text-sm text-rose-600"></p>
      <div x-show="!loading && !error">
        <p
          x-show="notifications.length === 0"
          class="px-4 py-6 text-center text-sm text-slate-500"
        >Tidak ada notifikasi baru.</p>

        <ul x-show="notifications.length > 0" class="divide-y divide-[#edf2f1]">
          <template x-for="notification in notifications" :key="notification.id">
            <li>
              <a data-user-navigation-link :href="historyUrl" @click="open = false" class="block w-full px-4 py-3 text-left transition hover:bg-[#f7fbfa]">
                <p x-text="notification.message" class="break-words text-sm leading-5 text-slate-700 [overflow-wrap:anywhere]"></p>
                <time
                  :datetime="notification.created_at"
                  x-text="formattedDate(notification.created_at)"
                  class="mt-1 block text-xs text-slate-400"
                ></time>
              </a>
            </li>
          </template>
        </ul>
      </div>
    </div>
    <a
      x-show="!loading && !error"
      data-user-navigation-link
      :href="historyUrl"
      @click="open = false"
      class="block shrink-0 border-t border-[#edf2f1] px-4 py-3 text-center text-xs font-bold text-[#007f70] transition hover:bg-[#f3faf8]"
    >Lihat detail semua notifikasi</a>
  </section>
</div>
