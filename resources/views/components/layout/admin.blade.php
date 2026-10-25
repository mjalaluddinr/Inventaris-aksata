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
  <title>{{ $title }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100">
  <div class="flex h-screen">
    <x-admin.sidebar />
    <main class="min-w-0 flex-1 overflow-y-auto p-8">
      <div class="admin-page-enter min-w-0">
        <x-admin.header :title="$headerTitle" :description="$headerDescription" />
        {{-- ASVS 16.5.1: Import feedback is handled by the sanitized account-page toast. --}}
        @if (session('success') && session('success') !== 'Login berhasil!' && !session()->has('import_log_id') && !request()->routeIs('admin.barang*', 'admin.rak.*', 'admin.kategori*'))
          <div class="mb-6 flex items-center gap-3 rounded-xl border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status">
            <iconify-icon icon="mdi:check-circle-outline" class="shrink-0 text-xl" aria-hidden="true"></iconify-icon>
            <span>{{ session('success') }}</span>
          </div>
        @endif
        {{ $slot }}
      </div>
    </main>
  </div>
</body>

</html>
