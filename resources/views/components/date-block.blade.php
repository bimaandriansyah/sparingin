{{-- Calendar-style date tile: weekday, day number, month. --}}
@props(['date', 'muted' => false])
<div {{ $attributes->class([
    'flex w-14 shrink-0 flex-col items-center justify-center rounded-2xl py-2 leading-none',
    'bg-zinc-950 text-white' => ! $muted,
    'bg-zinc-100 text-zinc-500' => $muted,
]) }}>
    <span class="text-[10px] font-semibold uppercase opacity-70">{{ $date->translatedFormat('D') }}</span>
    <span class="display mt-1 text-2xl font-bold">{{ $date->format('d') }}</span>
    <span class="mt-0.5 text-[10px] font-semibold uppercase opacity-70">{{ $date->translatedFormat('M') }}</span>
</div>
