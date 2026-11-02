@props(['variant' => 'neutral'])
<span {{ $attributes->class('badge badge-'.$variant) }}>{{ $slot }}</span>
