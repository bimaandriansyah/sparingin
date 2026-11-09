{{-- Round avatar: image when given, otherwise initials. Sizes: sm 36, md 44, lg 56. --}}
@props(['name' => '', 'src' => null, 'size' => 'md', 'tone' => 'soft'])
@php
    $initials = \Illuminate\Support\Str::of($name)->squish()->explode(' ')->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $sizes = ['sm' => 'size-9 text-xs', 'md' => 'size-11 text-sm', 'lg' => 'size-14 text-lg'];
    $tones = ['soft' => 'bg-brand-soft text-brand-dark', 'brand' => 'bg-brand text-white', 'dark' => 'bg-zinc-950 text-white'];
@endphp
@if ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->class([$sizes[$size], 'shrink-0 rounded-full object-cover ring-2 ring-white']) }}>
@else
    <span aria-hidden="true" {{ $attributes->class([$sizes[$size], $tones[$tone], 'inline-flex shrink-0 items-center justify-center rounded-full font-bold']) }}>{{ $initials }}</span>
@endif
