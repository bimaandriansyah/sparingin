@php
    $rp = fn (int $v) => 'Rp'.number_format($v, 0, ',', '.');
    $pct = $target ? min(100, (int) round($collected / $target * 100)) : 0;
@endphp
<div class="space-y-5" wire:poll.15s>
    <a href="{{ route('admin.communities.show', $event->community) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-zinc-500">
        <flux:icon.chevron-left variant="mini" /> {{ $event->community->name }}
    </a>

    <header>
        <div class="flex items-center gap-2">
            <span @class(['chip', 'bg-brand-soft text-brand-dark' => $event->isOpen(), 'bg-zinc-200 text-zinc-600' => ! $event->isOpen()])>{{ $event->stateLabel() }}</span>
            @if ($event->isInternal())
                <span class="chip bg-zinc-900 text-white"><flux:icon.lock-closed variant="micro" class="size-3" />Internal</span>
            @endif
            <span class="text-xs text-zinc-500">{{ \App\Models\Event::SPORTS[$event->sport] }} · {{ $teams->count() }} tim × {{ $event->players_per_team }} pemain</span>
        </div>
        <h1 class="display mt-2 text-3xl font-bold">{{ $event->title }}</h1>
        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-600">
            <span class="inline-flex items-center gap-1"><flux:icon.calendar-days variant="micro" class="text-zinc-400" />{{ $event->starts_at->translatedFormat('D, d M Y · H:i') }}</span>
            <span class="inline-flex items-center gap-1"><flux:icon.map-pin variant="micro" class="text-zinc-400" />{{ $event->location }}</span>
        </div>
    </header>

    {{-- Funds hero --}}
    <section class="hero-card p-5">
        <p class="text-sm font-medium text-white/80">Dana terkumpul (lunas)</p>
        <div class="display mt-1 text-4xl font-bold normal-case tabular-nums">{{ $rp($collected) }}</div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/25"><div class="h-full rounded-full bg-white" style="width: {{ $pct }}%"></div></div>
        <p class="mt-2 text-xs font-medium text-white/80 tabular-nums">{{ $pct }}% dari target {{ $rp($target) }}</p>
    </section>

    {{-- Slots per team --}}
    @php $byTeam = $positions->groupBy('event_team_id'); @endphp
    <section class="card divide-y divide-zinc-100 px-4">
        @foreach ($teams as $team)
            @php $teamSlots = $byTeam[$team->id] ?? collect(); $filled = $teamSlots->sum('taken'); @endphp
            @php $isOpen = $openTeam === $team->id; @endphp
            <div wire:key="t-{{ $team->id }}">
                <button type="button" wire:click="toggleTeam({{ $team->id }})" aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                        class="flex w-full items-center gap-3 py-3 text-left">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ $team->name }}</div>
                        <div class="text-xs text-zinc-500 tabular-nums">Formasi {{ $team->formation }}</div>
                    </div>
                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-100">
                        <div class="h-full rounded-full bg-zinc-950" style="width: {{ $teamSlots->count() ? round($filled / $teamSlots->count() * 100) : 0 }}%"></div>
                    </div>
                    <div class="stat-value w-10 text-right text-sm">{{ $filled }}<span class="font-medium text-zinc-400">/{{ $teamSlots->count() }}</span></div>
                    <flux:icon.chevron-down variant="mini" @class(['shrink-0 text-zinc-400 transition-transform', 'rotate-180' => $isOpen]) />
                </button>

                @if ($isOpen)
                    <div class="space-y-3 pb-4">
                        <x-lineup :positions="$lineup" />
                        @php $players = $lineup->filter(fn ($p) => $p->registrations->isNotEmpty()); @endphp
                        @forelse ($players as $p)
                            @php $reg = $p->registrations->first(); @endphp
                            <div class="flex items-center gap-3" wire:key="lp-{{ $reg->id }}">
                                <x-avatar :name="$reg->name" :src="$reg->photoUrl()" :tone="$reg->isPaid() ? 'dark' : 'soft'" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">{{ $reg->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $p->name }} · {{ $reg->status === 'pending' ? 'Menunggu' : 'Diterima' }}</div>
                                </div>
                                @if ($p->price > 0)
                                    <span @class(['chip', 'bg-green-50 text-green-700' => $reg->isPaid(), 'bg-orange-50 text-orange-700' => ! $reg->isPaid()])>{{ $reg->isPaid() ? 'Lunas' : 'Belum lunas' }}</span>
                                @endif
                                <a href="https://wa.me/{{ $reg->phone }}" target="_blank" rel="noopener" class="btn btn-secondary btn-icon min-h-10 w-10" aria-label="Chat {{ $reg->name }} via WhatsApp">
                                    <x-icon-whatsapp class="size-5 text-green-600" />
                                </a>
                            </div>
                        @empty
                            <p class="text-center text-sm text-zinc-500">Belum ada pemain di tim ini.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    {{-- Actions --}}
    <section class="card space-y-3 p-4" x-data="{ copied: false }">
        <div class="flex items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-dark"><flux:icon.link variant="mini" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-zinc-500">Link pendaftaran</p>
                <p class="truncate text-sm font-medium">{{ route('event.public', $event) }}</p>
            </div>
            <button type="button" class="btn btn-secondary min-h-10 px-4 text-sm"
                    x-on:click="navigator.clipboard.writeText(@js(route('event.public', $event))); copied = true; setTimeout(() => copied = false, 1500)">
                <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" wire:click="toggleOpen" class="btn btn-secondary text-sm">
                <flux:icon :name="$event->status === 'open' ? 'lock-closed' : 'lock-open'" variant="micro" />
                {{ $event->status === 'open' ? 'Tutup daftar' : 'Buka daftar' }}
            </button>
            <a href="{{ route('admin.events.edit', $event) }}" wire:navigate class="btn btn-secondary text-sm"><flux:icon.pencil-square variant="micro" /> Edit event</a>
        </div>
        {{-- In-page confirm (native confirm() is blocked in some webviews). --}}
        <div x-data="{ confirming: false }">
            <button type="button" x-show="! confirming" x-on:click="confirming = true" class="btn w-full bg-red-50 text-sm text-red-700 hover:bg-red-100">
                <flux:icon.trash variant="micro" /> Hapus event
            </button>
            <div x-show="confirming" x-cloak class="space-y-2 rounded-2xl bg-red-50 p-3" role="alertdialog" aria-label="Konfirmasi hapus event">
                <p class="text-sm text-red-800"><b>Hapus event ini?</b> Semua pendaftar, foto, dan bukti transfernya ikut terhapus permanen.</p>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary min-h-11 flex-1 text-sm" x-on:click="confirming = false">Batal</button>
                    <button type="button" class="btn min-h-11 flex-1 bg-red-600 text-sm text-white hover:bg-red-700" wire:click="deleteEvent" wire:loading.attr="disabled">Ya, hapus</button>
                </div>
            </div>
        </div>
    </section>

    {{-- Registrations --}}
    <section class="space-y-3">
        <h2 class="px-1 font-semibold">Pendaftar</h2>
        <div class="segmented text-xs" role="group" aria-label="Filter pendaftar">
            @foreach (['pending' => 'Menunggu', 'accepted' => 'Diterima', 'unpaid' => 'Belum lunas', 'rejected' => 'Ditolak'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                    {{ $label }} <span class="tabular-nums opacity-60">{{ $counts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        @forelse ($registrations as $r)
            <x-registration-card :r="$r" wire:key="reg-{{ $r->id }}" />
        @empty
            <div class="card p-6 text-center text-sm text-zinc-500">Tidak ada pendaftar di kategori ini.</div>
        @endforelse
    </section>
</div>
