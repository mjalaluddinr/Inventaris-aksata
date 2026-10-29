@props([
    'monthlyLoans',
    'year',
    'chartId' => 'grafik',
    'class' => '',
])

@php
  $maxMonthlyLoans = max(1, $monthlyLoans->max('total'));
  $chartPoints = $monthlyLoans->values()->map(static function (array $month, int $index) use ($maxMonthlyLoans): array {
      return [
          'x' => 72 + ($index * 856 / 11),
          'y' => 296 - ($month['total'] / $maxMonthlyLoans * 224),
          'label' => $month['label'],
          'total' => $month['total'],
      ];
  })->all();
  $linePath = 'M '.$chartPoints[0]['x'].' '.$chartPoints[0]['y'];

  for ($index = 1; $index < count($chartPoints); $index++) {
      $previousPoint = $chartPoints[$index - 1];
      $currentPoint = $chartPoints[$index];
      $previousPreviousPoint = $chartPoints[max(0, $index - 2)];
      $nextPoint = $chartPoints[min(count($chartPoints) - 1, $index + 1)];
      $controlOneX = $previousPoint['x'] + (($currentPoint['x'] - $previousPreviousPoint['x']) / 6);
      $controlOneY = $previousPoint['y'] + (($currentPoint['y'] - $previousPreviousPoint['y']) / 6);
      $controlTwoX = $currentPoint['x'] - (($nextPoint['x'] - $previousPoint['x']) / 6);
      $controlTwoY = $currentPoint['y'] - (($nextPoint['y'] - $previousPoint['y']) / 6);

      $linePath .= " C {$controlOneX} {$controlOneY}, {$controlTwoX} {$controlTwoY}, {$currentPoint['x']} {$currentPoint['y']}";
  }

  $firstPoint = $chartPoints[0];
  $lastPoint = $chartPoints[array_key_last($chartPoints)];
  $areaPath = $linePath." L {$lastPoint['x']} 308 L {$firstPoint['x']} 308 Z";
@endphp

<figure {{ $attributes->merge(['class' => 'min-w-0']) }}>
  <svg class="block h-auto w-full overflow-visible {{ $class }}" viewBox="0 0 1000 370" role="img"
    aria-labelledby="{{ $chartId }}-title {{ $chartId }}-description" xmlns="http://www.w3.org/2000/svg">
    <title id="{{ $chartId }}-title">Peminjaman {{ $year }}</title>
    <desc id="{{ $chartId }}-description">Jumlah peminjaman setiap bulan dari Januari sampai Desember.</desc>
    <defs>
      <linearGradient id="{{ $chartId }}-fill" x1="0" x2="0" y1="0" y2="1">
        <stop offset="0%" stop-color="#00a88f" stop-opacity=".28"></stop>
        <stop offset="100%" stop-color="#00a88f" stop-opacity=".025"></stop>
      </linearGradient>
    </defs>

    @foreach ([0, 0.25, 0.5, 0.75, 1] as $tick)
      @php
        $tickY = 296 - ($tick * 224);
        $tickValue = (int) round($maxMonthlyLoans * $tick);
      @endphp
      <line x1="72" y1="{{ $tickY }}" x2="928" y2="{{ $tickY }}" stroke="#d9ebe5" stroke-dasharray="4 7"></line>
      <text x="54" y="{{ $tickY + 4 }}" text-anchor="end" fill="#69847c" font-size="12">{{ $tickValue }}</text>
    @endforeach

    <path d="{{ $areaPath }}" fill="url(#{{ $chartId }}-fill)"></path>
    <path d="{{ $linePath }}" fill="none" stroke="#008b7a" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"></path>

    @foreach ($chartPoints as $point)
      <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="6" fill="#fff" stroke="#008b7a" stroke-width="3">
        <title>{{ $point['label'] }}: {{ $point['total'] }} peminjaman</title>
      </circle>
      @if ($point['total'] > 0)
        <text x="{{ $point['x'] }}" y="{{ max(20, $point['y'] - 14) }}" text-anchor="middle" fill="#14594e" font-size="12" font-weight="700">{{ $point['total'] }}</text>
      @endif
      <text x="{{ $point['x'] }}" y="340" text-anchor="middle" fill="#526f67" font-size="12" font-weight="600">{{ $point['label'] }}</text>
    @endforeach
  </svg>
</figure>
