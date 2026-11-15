@php
  $isAdminNotification = in_array(auth()->user()?->role, ['admin', 'laboran'], true);
@endphp

<x-dynamic-component
  :component="$isAdminNotification ? 'layout.admin' : 'layout.user'"
  title="Detail Notifikasi - InvenTrack"
  header-title="Detail Notifikasi"
  header-description="Riwayat semua notifikasi Anda."
>
    <main class="{{ $isAdminNotification ? '-mx-8 -mt-8 w-[calc(100%+4rem)] pb-8' : 'min-h-screen w-full bg-[#e9faf5] pb-12' }}">
      @unless ($isAdminNotification)
        <x-user.hero-section
          title="Riwayat Notifikasi"
          description="Pantau seluruh pembaruan aktivitas dan transaksi."
        />
      @endunless
      <div class="px-4 pt-6 sm:px-8 lg:px-10">
        <section class="mx-auto w-full max-w-6xl overflow-hidden rounded-2xl border border-[#d4e7e2] bg-white shadow-[0_12px_32px_rgba(0,95,82,0.10)]">
          <header class="flex flex-col gap-2 border-b border-[#e4efec] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
              <h2 class="text-base font-bold text-slate-900">Semua Notifikasi</h2>
              <p class="mt-0.5 text-xs text-slate-500">{{ $notifications->total() }} notifikasi tersimpan</p>
            </div>
            <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-[#eaf8f4] px-3 py-1.5 text-xs font-semibold text-[#007b69]">
              <iconify-icon icon="mdi:history" width="15" aria-hidden="true"></iconify-icon>
              Riwayat lengkap
            </span>
          </header>

          @if ($notifications->count() === 0)
            <div class="px-5 py-14 text-center">
              <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#edf8f5] text-[#008b7a]">
                <iconify-icon icon="mdi:bell-off-outline" class="text-3xl" aria-hidden="true"></iconify-icon>
              </span>
              <h3 class="mt-3 text-sm font-bold text-slate-800">Belum ada notifikasi</h3>
              <p class="mt-1 text-xs text-slate-500">Aktivitas baru akan muncul di sini.</p>
            </div>
          @else
            <div class="hidden overflow-x-auto md:block">
              <table class="w-full table-fixed border-collapse">
                <thead>
                  <tr class="bg-[#f4faf8] text-left">
                    <th scope="col" class="w-[72%] px-6 py-3 text-xs font-bold text-slate-600">Pesan</th>
                    <th scope="col" class="w-[20%] px-4 py-3 text-xs font-bold text-slate-600">Waktu</th>
                    <th scope="col" class="w-[8%] px-4 py-3 text-center text-xs font-bold text-slate-600">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#edf2f1]">
                  @foreach ($notifications as $notification)
                    <tr class="group transition-colors hover:bg-[#f8fcfb]">
                      <td class="px-6 py-4 align-top">
                        <div class="flex items-start gap-3">
                          <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#d7ebe6] bg-[#eff8f6] text-[#008b7a] transition group-hover:border-[#91d9cd] group-hover:bg-white">
                            <iconify-icon icon="{{ ! ($notification['read'] ?? false) ? 'mdi:bell-ring-outline' : 'mdi:bell-outline' }}" width="19" aria-hidden="true"></iconify-icon>
                          </span>
                          <div class="min-w-0">
                            <p class="text-sm font-semibold leading-6 text-slate-700">{{ $notification['message'] }}</p>
                          </div>
                        </div>
                      </td>
                      <td class="px-4 py-4 align-top">
                        <time datetime="{{ $notification['created_at']->toIso8601String() }}" class="block text-xs font-semibold text-slate-600">
                          {{ $notification['created_at']->translatedFormat('d M Y') }}
                        </time>
                        <span class="mt-1 block text-xs text-slate-400">{{ $notification['created_at']->format('H:i') }}</span>
                      </td>
                      <td class="px-4 py-4 text-center align-top">
                        @if (! ($notification['read'] ?? false))
                          <span class="inline-flex rounded-full bg-[#b8f5e9] px-2.5 py-1 text-[10px] font-bold text-[#007f70]">Baru</span>
                        @else
                          <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">Dibaca</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="divide-y divide-[#edf2f1] md:hidden">
              @foreach ($notifications as $notification)
                <article class="px-4 py-4">
                  <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eff8f6] text-[#008b7a]">
                      <iconify-icon icon="{{ ! ($notification['read'] ?? false) ? 'mdi:bell-ring-outline' : 'mdi:bell-outline' }}" width="20" aria-hidden="true"></iconify-icon>
                    </span>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold leading-6 text-slate-700">{{ $notification['message'] }}</p>
                        @if (! ($notification['read'] ?? false))
                          <span class="shrink-0 rounded-full bg-[#b8f5e9] px-2 py-1 text-[10px] font-bold text-[#007f70]">Baru</span>
                        @endif
                      </div>
                      <time datetime="{{ $notification['created_at']->toIso8601String() }}" class="mt-1 block text-xs text-slate-400">
                        {{ $notification['created_at']->translatedFormat('d M Y, H:i') }}
                      </time>
                    </div>
                  </div>
                </article>
              @endforeach
            </div>

            <footer class="border-t border-[#edf2f1] bg-[#fbfdfc] px-4 py-4 sm:px-6">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                  Menampilkan {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} dari {{ $notifications->total() }} data
                </p>
                <x-pagination :paginator="$notifications" label="Navigasi halaman notifikasi"
                  :variant="$isAdminNotification ? 'admin' : 'user'"
                  nav-class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-end" />
              </div>
            </footer>
          @endif
        </section>
      </div>
    </main>
</x-dynamic-component>
