{{-- "Titik Nol coffee" wordmark. Use variant="light" on dark backgrounds (white text). --}}
@props(['variant' => 'dark'])
<img src="{{ asset($variant === 'light' ? 'images/logo-titiknol-light.png' : 'images/logo-titiknol.png') }}"
     alt="Titik Nol Coffee" width="421" height="161"
     {{ $attributes->merge(['class' => 'h-8 w-auto']) }}>
