@php $teamPlayers = $positions->filter(fn ($p) => $p->registrations->isNotEmpty()); @endphp
<div class="space-y-4 px-4 pb-10 pt-2">
    <header class="flex items-center gap-2">
        <a href="{{ route('event.public', $event) }}" wire:navigate class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Kembali ke detail event">
            <flux:icon.chevron-left variant="mini" />
        </a>
        <div class="min-w-0">
            <h1 class="display truncate text-2xl font-bold">Daftar pemain</h1>
            <p class="truncate text-xs text-zinc-500">{{ $event->title }}</p>
        </div>
    </header>

    @if ($teams->count() > 1)
        <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Pilih tim">
            @foreach ($teams as $i => $t)
                <button type="button" wire:click="$set('team', {{ $i }})" aria-pressed="{{ $team === $i ? 'true' : 'false' }}"
                        @class([
                            'min-h-10 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors',
                            'bg-zinc-950 text-white' => $team === $i,
                            'bg-white text-zinc-600 shadow-card hover:bg-zinc-50' => $team !== $i,
                        ])>{{ $t->name }}</button>
            @endforeach
        </div>
    @endif

    <section class="card space-y-4 p-4" wire:poll.30s>
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ $current?->name }}</h2>
            <span class="text-xs text-zinc-500 tabular-nums">Formasi {{ $current?->formation }} · {{ $teamPlayers->count() }}/{{ $positions->count() }}</span>
        </div>

        <x-lineup :positions="$positions" />

        @if ($teamPlayers->isNotEmpty())
            <ul class="divide-y divide-zinc-100" aria-label="Pemain {{ $current?->name }}">
                @foreach ($teamPlayers as $p)
                    @php $reg = $p->registrations->first(); @endphp
                    <li class="flex items-center gap-3 py-2.5" wire:key="pl-{{ $p->id }}">
                        <x-avatar :name="$reg->name" :src="$reg->photoUrl()" size="sm" :tone="$reg->isPaid() ? 'dark' : 'soft'" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $reg->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $p->name }}</div>
                        </div>
                        @if ($reg->isPaid())
                            <span class="chip bg-green-50 text-green-700"><flux:icon.check-circle variant="micro" /> Lunas</span>
                        @elseif ($reg->status === 'accepted')
                            <span class="chip bg-blue-50 text-blue-700"><flux:icon.user-plus variant="micro" /> Terdaftar</span>
                        @else
                            <span class="chip bg-amber-50 text-amber-700"><flux:icon.clock variant="micro" /> Menunggu</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="py-2 text-center text-sm text-zinc-500">Belum ada pemain di tim ini.</p>
        @endif
    </section>

    @if ($event->isOpen())
        <a href="{{ route('event.join', $event) }}" wire:navigate class="btn btn-primary w-full">Bergabung</a>
    @endif
</div>
