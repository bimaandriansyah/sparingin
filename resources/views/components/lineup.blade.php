{{--
    Pitch with one card per position.
    Taken = white player card (photo or initials, name, position, "Lunas" tag); empty = dashed slot ("+" when selectable).
    Parent Livewire component must have pick(id) when selectable.
--}}
@props(['positions', 'selectable' => false, 'selected' => null])
@php
    $short = fn (int $v) => $v === 0 ? 'Gratis' : ($v % 1000 ? 'Rp'.number_format($v, 0, ',', '.') : ($v / 1000).'K');
    $initials = fn (string $name) => \Illuminate\Support\Str::of($name)->squish()->explode(' ')->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
{{-- 2:3 keeps 5 rows of cards (GK + 4 lines) from overlapping on a phone. --}}
<x-pitch {{ $attributes->class('aspect-[2/3]') }}>
    @foreach ($positions as $p)
        @php $reg = $p->registrations->first(); @endphp
        <div wire:key="pos-{{ $p->id }}" class="absolute w-[3.75rem] -translate-1/2" style="left: {{ $p->x }}%; top: {{ $p->y }}%">
            @if ($reg)
                <figure class="relative rounded-xl bg-white p-1 text-center shadow-md ring-1 ring-black/5">
                    @if ($reg->photoUrl())
                        <img src="{{ $reg->photoUrl() }}" alt="" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                    @else
                        <div class="flex aspect-square w-full items-center justify-center rounded-lg bg-zinc-100 font-display text-lg font-bold text-zinc-500">{{ $initials($reg->name) }}</div>
                    @endif
                    @if ($reg->isPaid())
                        <span class="absolute -right-1 -top-1.5 rounded-md bg-green-600 px-1 text-[9px] font-bold leading-4 text-white shadow">Lunas</span>
                    @endif
                    <figcaption class="px-0.5 pt-1">
                        <span class="block truncate text-[10px] font-bold leading-tight text-zinc-950">{{ $reg->name }}</span>
                        <span class="block text-[9px] font-semibold leading-tight text-zinc-500">{{ $p->name }}</span>
                    </figcaption>
                </figure>
            @elseif ($selectable)
                @php $isSelected = $selected === $p->id; @endphp
                <button type="button" wire:click="pick({{ $p->id }})" aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        aria-label="Pilih posisi {{ $p->name }}, {{ $short($p->price) }}"
                        @class([
                            'block w-full rounded-xl p-1 text-center transition-transform active:scale-95',
                            'bg-brand text-white shadow-lg ring-2 ring-white' => $isSelected,
                            'border-2 border-dashed border-white/80 bg-white/10 text-white hover:bg-white/25' => ! $isSelected,
                        ])>
                    <span class="flex aspect-square w-full items-center justify-center">
                        @if ($isSelected) <flux:icon.check /> @else <flux:icon.plus /> @endif
                    </span>
                    <span class="block text-[10px] font-bold leading-tight">{{ $p->name }}</span>
                    <span class="block text-[9px] font-semibold leading-tight opacity-80">{{ $short($p->price) }}</span>
                </button>
            @else
                <div class="rounded-xl border-2 border-dashed border-white/40 p-1 text-center text-white/70">
                    <span class="block aspect-square w-full"></span>
                    <span class="block text-[10px] font-bold leading-tight">{{ $p->name }}</span>
                    <span class="block text-[9px] leading-tight">&nbsp;</span>
                </div>
            @endif
        </div>
    @endforeach
</x-pitch>
