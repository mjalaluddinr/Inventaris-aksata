@props([
    'title',
    'description',
])

<section {{ $attributes->merge(['class' => 'relative isolate flex min-h-44 flex-col items-center justify-center overflow-hidden bg-gradient-to-r from-[#004e48] via-[#008b7a] to-[#00d5a0] px-4 pb-10 pt-[calc(88px+1.5rem)] text-center text-white sm:min-h-56 sm:px-10 sm:pb-14 sm:pt-[calc(88px+1.75rem)] lg:px-16']) }}>
  <span class="pointer-events-none absolute -right-10 -top-20 -z-10 h-64 w-64 rounded-full bg-[#00e3aa]/20 sm:right-[10%] sm:h-80 sm:w-80" aria-hidden="true"></span>
  <span class="pointer-events-none absolute right-[8%] top-[56%] -z-10 h-20 w-20 rounded-full bg-[#00e3aa]/20" aria-hidden="true"></span>
  <div class="relative z-10 flex w-full max-w-5xl flex-col items-center justify-center">
    <h1 class="max-w-full break-words text-[clamp(2rem,5vw,3rem)] font-bold leading-tight tracking-tight">{{ $title }}</h1>
    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-white/90 sm:text-base">{{ $description }}</p>
  </div>
</section>
