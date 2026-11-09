{{-- Event list row (public + admin). Default slot = extra chips on the meta line. --}}
@props(['event', 'href', 'showCommunity' => false])
@php $state = $event->stateLabel(); @endphp
<a href="{{ $href }}" wire:navigate {{ $attributes->class('card pressable flex items-center gap-3 p-3') }}>
    <x-date-block :date="$event->starts_at" :muted="$state !== 'Open'" />
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <h3 class="truncate font-semibold">{{ $event->title }}</h3>
            @if ($event->isInternal())
                <span class="chip shrink-0 bg-zinc-900 px-2 py-0.5 text-[10px] text-white"><flux:icon.lock-closed variant="micro" class="size-3" />Internal</span>
            @endif
        </div>
        @if ($showCommunity)
            <p class="truncate text-xs text-zinc-500">{{ $event->community->name }}</p>
        @endif
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-600">
            <span class="inline-flex items-center gap-1"><flux:icon.clock variant="micro" class="text-zinc-400" />{{ $event->starts_at->format('H:i') }}</span>
            <span class="inline-flex min-w-0 items-center gap-1"><flux:icon.map-pin variant="micro" class="shrink-0 text-zinc-400" /><span class="truncate">{{ $event->location }}</span></span>
            {{ $slot }}
        </div>
    </div>
    <div class="flex flex-col items-end gap-1.5">
        <span @class([
            'chip',
            'bg-brand-soft text-brand-dark' => $state === 'Open',
            'bg-zinc-100 text-zinc-500' => $state !== 'Open',
        ])>{{ $state }}</span>
        <flux:icon.chevron-right variant="mini" class="text-zinc-300" />
    </div>
</a>
