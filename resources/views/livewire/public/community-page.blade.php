<div class="space-y-4 px-4 pb-28 pt-2">
    {{-- Installed player app reopens here (see welcome page). --}}
    <div x-data x-init="(() => { try { localStorage.setItem('sparingin:last', @js(route('community.public', $community))) } catch (e) {} })()"></div>

    {{-- Community --}}
    <section class="card p-4">
        <div class="flex items-center gap-3">
            <x-avatar :name="$community->name" :src="$community->logo_path ? asset('storage/'.$community->logo_path) : null" size="lg" tone="brand" />
            <div class="min-w-0">
                <h1 class="display truncate text-2xl font-bold">{{ $community->name }}</h1>
                @if ($community->city)
                    <p class="mt-0.5 inline-flex items-center gap-1 text-sm text-zinc-500">
                        <flux:icon.map-pin variant="micro" />{{ $community->city }}
                    </p>
                @endif
            </div>
        </div>
        @if ($community->description)
            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-zinc-600">{{ $community->description }}</p>
        @endif
    </section>

    {{-- Calendar --}}
    <section class="card p-4" aria-label="Kalender event">
        <div class="flex items-center justify-between">
            <h2 class="display text-xl font-bold">{{ $first->translatedFormat('F Y') }}</h2>
            <div class="flex gap-2">
                <button wire:click="shiftMonth(-1)" class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Bulan sebelumnya"><flux:icon.chevron-left variant="mini" /></button>
                <button wire:click="shiftMonth(1)" class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Bulan berikutnya"><flux:icon.chevron-right variant="mini" /></button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-7 text-center text-[11px] font-semibold uppercase text-zinc-400">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)
                <div class="pb-2">{{ $d }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-y-1">
            @foreach ($days as $day)
                @php
                    $key = $day->toDateString();
                    $has = $eventDays->has($key);
                    $inMonth = $day->month === $first->month;
                    $isSelected = $day->isSameDay($selected);
                @endphp
                <button wire:key="d-{{ $key }}" wire:click="selectDate('{{ $key }}')"
                        @if ($isSelected) aria-current="date" @endif
                        aria-label="{{ $day->translatedFormat('l, j F') }}{{ $has ? ', ada event' : '' }}"
                        class="flex h-11 items-center justify-center">
                    <span @class([
                        'relative flex size-10 items-center justify-center rounded-full text-sm tabular-nums transition-colors',
                        'bg-zinc-950 font-bold text-white' => $isSelected,
                        'font-bold text-brand-dark ring-1 ring-brand/40' => ! $isSelected && $day->isToday(),
                        'text-zinc-300' => ! $isSelected && ! $inMonth,
                        'hover:bg-zinc-100' => ! $isSelected,
                    ])>
                        {{ $day->day }}
                        @if ($has)
                            <span @class(['absolute bottom-1 size-1.5 rounded-full', 'bg-brand' => ! $isSelected, 'bg-white' => $isSelected])></span>
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    </section>

    {{-- Selected day --}}
    <section class="space-y-2">
        <h2 class="px-1 text-sm font-semibold text-zinc-500">{{ $selected->translatedFormat('l, j F Y') }}</h2>
        @forelse ($dayEvents as $event)
            <x-event-row :event="$event" :href="route('event.public', $event)" wire:key="day-{{ $event->id }}">
                <span class="inline-flex items-center gap-1"><flux:icon.banknotes variant="micro" class="text-zinc-400" />{{ $event->priceLabel() }}</span>
            </x-event-row>
        @empty
            <div class="card flex items-center gap-3 p-4 text-sm text-zinc-500">
                <flux:icon.calendar class="text-zinc-300" />
                Tidak ada acara di tanggal ini.
            </div>
        @endforelse
    </section>

    @if ($upcoming->isNotEmpty())
        <section class="space-y-2">
            <h2 class="px-1 text-sm font-semibold text-zinc-500">Event mendatang</h2>
            @foreach ($upcoming as $event)
                <x-event-row :event="$event" :href="route('event.public', $event)" wire:key="up-{{ $event->id }}">
                    <span class="inline-flex items-center gap-1"><flux:icon.banknotes variant="micro" class="text-zinc-400" />{{ $event->priceLabel() }}</span>
                </x-event-row>
            @endforeach
        </section>
    @endif

    @if ($community->whatsapp)
        <div class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-md bg-gradient-to-t from-canvas via-canvas/95 to-transparent px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-6">
            <a href="https://wa.me/{{ $community->whatsapp }}" target="_blank" rel="noopener" class="btn w-full bg-green-600 text-white hover:bg-green-700">
                <x-icon-whatsapp class="size-5" />
                Chat Pengurus
            </a>
        </div>
    @endif
</div>
