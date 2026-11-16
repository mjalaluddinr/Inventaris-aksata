@props([
    'monthlyLoans',
    'year',
    'chartId' => 'grafik',
    'class' => '',
])

@php
  $maxMonthlyLoans = (int) ($monthlyLoans->max('total') ?? 0);
  $chartTickStep = 10;
  $chartScale = max($chartTickStep, (int) (ceil($maxMonthlyLoans / $chartTickStep) * $chartTickStep));
  $chartTicks = range(0, $chartScale, $chartTickStep);
  $chartStep = 856 / max(1, $monthlyLoans->count());
  $chartBarWidth = 40;
  $chartPoints = $monthlyLoans->values()->map(static function (array $month, int $index) use ($chartScale, $chartStep, $chartBarWidth): array {
      $total = (int) $month['total'];
      $height = $total / $chartScale * 224;

      return [
          'x' => 72 + ($index * $chartStep) + (($chartStep - $chartBarWidth) / 2),
          'centerX' => 72 + (($index + 0.5) * $chartStep),
          'y' => 296 - $height,
          'height' => $height,
          'label' => $month['label'],
          'total' => $total,
      ];
  })->all();
@endphp

<figure {{ $attributes->merge(['class' => 'min-w-0']) }}>
  <svg class="block h-auto w-full overflow-visible {{ $class }}" viewBox="0 0 1000 370" role="img"
    aria-labelledby="{{ $chartId }}-title {{ $chartId }}-description" xmlns="http://www.w3.org/2000/svg">
    <title id="{{ $chartId }}-title">Peminjaman {{ $year }}</title>
    <desc id="{{ $chartId }}-description">Diagram batang jumlah peminjaman setiap bulan dari Januari sampai Desember.</desc>

    @foreach ($chartTicks as $tickValue)
      @php
        $tickY = 296 - ($tickValue / $chartScale * 224);
      @endphp
      <line x1="72" y1="{{ $tickY }}" x2="928" y2="{{ $tickY }}" stroke="#d9ebe5" stroke-dasharray="4 7"></line>
      <text x="54" y="{{ $tickY + 4 }}" text-anchor="end" fill="#69847c" font-size="12">{{ $tickValue }}</text>
    @endforeach

    @foreach ($chartPoints as $point)
      <rect x="{{ $point['x'] }}" y="{{ $point['y'] }}" width="{{ $chartBarWidth }}" height="{{ $point['height'] }}"
        rx="5" fill="#008b7a">
        <title>{{ $point['label'] }}: {{ $point['total'] }} peminjaman</title>
      </rect>
      @if ($point['total'] > 0)
        <text x="{{ $point['centerX'] }}" y="{{ max(20, $point['y'] - 14) }}" text-anchor="middle" fill="#14594e" font-size="12" font-weight="700">{{ $point['total'] }}</text>
      @endif
      <text x="{{ $point['centerX'] }}" y="340" text-anchor="middle" fill="#526f67" font-size="12" font-weight="600">{{ $point['label'] }}</text>
    @endforeach
  </svg>
</figure>
