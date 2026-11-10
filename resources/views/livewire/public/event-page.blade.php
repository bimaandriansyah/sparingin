@php
    $rp = fn (int $v) => 'Rp'.number_format($v, 0, ',', '.');
    $pct = $target ? min(100, (int) round($collected / $target * 100)) : 0;
    $full = $players->count() >= $total;
    $daysLeft = (int) now()->startOfDay()->diffInDays($event->starts_at->copy()->startOfDay(), false);
    $countdown = match (true) {
        $event->starts_at->isPast() => 'Selesai',
        $daysLeft === 0 => 'Hari ini',
        default => $daysLeft.' hari lagi',
    };
    $community = $event->community;
    $canJoin = $event->isOpen() && ! $full;
@endphp

<div class="pb-28">
    {{-- Installed player app reopens here (see welcome page). --}}
    <div x-data x-init="(() => { try { localStorage.setItem('sparingin:last', @js(route('community.public', $community))) } catch (e) {} })()"></div>

    {{-- Cover --}}
    <div class="px-4 pt-2">
        @if ($event->poster_path)
            <img src="{{ asset('storage/'.$event->poster_path) }}" alt="Poster {{ $event->title }}" class="aspect-[16/10] w-full rounded-3xl object-cover shadow-card">
        @else
            <div class="relative flex aspect-[16/9] w-full items-end overflow-hidden rounded-3xl bg-zinc-950 p-5 shadow-card">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" class="absolute -right-6 -top-4 h-48 rotate-6 opacity-90">
                <span class="chip bg-white/15 text-white backdrop-blur">{{ \App\Models\Event::SPORTS[$event->sport] }}</span>
                @if ($event->isInternal())
                    <span class="chip ml-1 bg-white text-zinc-900"><flux:icon.lock-closed variant="micro" class="size-3" />Internal</span>
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-4 px-4 pt-4">
        {{-- Title --}}
        <section>
            <a href="{{ route('community.public', $community) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-600">
                <x-avatar :name="$community->name" :src="$community->logo_path ? asset('storage/'.$community->logo_path) : null" size="sm" tone="brand" />
                {{ $community->name }}
            </a>
            <h1 class="display mt-2 text-3xl font-bold">{{ $event->title }}</h1>
            <div class="mt-2 flex items-center justify-between gap-3">
                <p class="inline-flex min-w-0 items-center gap-1 text-sm text-zinc-600">
                    <flux:icon.map-pin variant="micro" class="shrink-0 text-zinc-400" /><span class="truncate">{{ $event->location }}</span>
                </p>
                @if ($event->maps_url)
                    <a href="{{ $event->maps_url }}" target="_blank" rel="noopener" class="btn btn-secondary min-h-9 shrink-0 px-3 text-sm">
                        Maps <flux:icon.arrow-up-right variant="micro" />
                    </a>
                @endif
            </div>
        </section>

        {{-- Funds hero --}}
        <section class="hero-card p-5" wire:poll.30s>
            <div class="flex items-center justify-between text-sm font-medium text-white/80">
                <span>Dana terkumpul</span>
                <span class="chip bg-white/20 text-white">{{ $countdown }}</span>
            </div>
            <div class="display mt-1 text-4xl font-bold normal-case tabular-nums">{{ $rp($collected) }}</div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/25" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Dana terkumpul">
                <div class="h-full rounded-full bg-white" style="width: {{ $pct }}%"></div>
            </div>
            <div class="mt-2 flex justify-between text-xs font-medium text-white/80 tabular-nums">
                <span>{{ $pct }}% dari {{ $rp($target) }}</span>
                <span>{{ $players->count() }}/{{ $total }} slot terisi</span>
            </div>
        </section>

        {{-- Stats --}}
        <section class="card grid grid-cols-3 divide-x divide-zinc-100 py-4 text-center">
            <div class="px-2">
                <div class="stat-label">Tanggal</div>
                <div class="stat-value mt-1 text-sm">{{ $event->starts_at->translatedFormat('D, d M') }}</div>
            </div>
            <div class="px-2">
                <div class="stat-label">Kick-off</div>
                <div class="stat-value mt-1 text-sm">{{ $event->starts_at->format('H:i') }} WIB</div>
            </div>
            <div class="px-2">
                <div class="stat-label">Iuran</div>
                <div class="stat-value mt-1 text-sm">{{ $event->priceLabel() }}</div>
            </div>
        </section>

        @if ($event->team_price !== null || $event->group_discounts)
            @php $k = fn (int $v) => $v % 1000 ? 'Rp'.number_format($v, 0, ',', '.') : 'Rp'.($v / 1000).'K'; @endphp
            <section class="flex items-start gap-3 rounded-2xl bg-brand-soft p-4 text-sm text-brand-dark">
                <flux:icon.tag variant="mini" class="mt-0.5 shrink-0" />
                <ul class="space-y-0.5">
                    @if ($event->team_price !== null)
                        <li>Booking 1 tim cukup <b>{{ $k($event->team_price) }}</b></li>
                    @endif
                    @foreach ($event->group_discounts ?? [] as $d)
                        <li>Daftar <b>{{ $d['min'] }}+ orang</b> potong {{ $k($d['off']) }}/orang</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($event->description)
            <section class="card p-4" x-data="{ open: false }">
                <h2 class="font-semibold">Deskripsi acara</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-zinc-600" :class="open || 'line-clamp-3'">{{ $event->description }}</p>
                <button type="button" x-show="! open" x-on:click="open = true" class="mt-1 min-h-11 text-sm font-semibold text-brand-dark">Selengkapnya</button>
            </section>
        @endif

        {{-- Players --}}
        <a href="{{ route('event.players', $event) }}" wire:navigate class="card pressable flex items-center gap-3 p-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100"><flux:icon.list-bullet variant="mini" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold">Lihat daftar pemain</h2>
                <p class="text-xs text-zinc-500">{{ $event->teams->count() }} tim · {{ $event->players_per_team }} pemain per tim</p>
            </div>
            @if ($players->isNotEmpty())
                <div class="flex -space-x-2">
                    @foreach ($players->take(4) as $pl)
                        <x-avatar :name="$pl->name" :src="$pl->photoUrl()" size="sm" :tone="$pl->isPaid() ? 'dark' : 'soft'" class="ring-2 ring-white" />
                    @endforeach
                    @if ($players->count() > 4)
                        <span class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-xs font-bold ring-2 ring-white">+{{ $players->count() - 4 }}</span>
                    @endif
                </div>
            @endif
            <flux:icon.chevron-right variant="mini" class="text-zinc-300" />
        </a>
    </div>

    {{-- Bottom action bar --}}
    <div class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-md bg-gradient-to-t from-canvas via-canvas/95 to-transparent px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-6">
        @if (! $canJoin)
            <p class="mb-2 flex items-center justify-center gap-1 text-xs font-medium text-zinc-500">
                <flux:icon.information-circle variant="micro" />
                {{ $event->starts_at->isPast() ? 'Acara sudah selesai' : ($full ? 'Kuota pemain sudah penuh' : 'Pendaftaran ditutup') }}
            </p>
        @endif
        <div class="flex gap-2">
            <a href="{{ route('community.public', $community) }}" wire:navigate class="btn btn-secondary btn-icon" aria-label="Kembali ke agenda">
                <flux:icon.chevron-left variant="mini" />
            </a>
            <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText(@js(route('event.public', $event))); copied = true; setTimeout(() => copied = false, 1500)"
                    class="btn btn-secondary btn-icon" aria-label="Salin link event">
                <flux:icon.check variant="mini" x-show="copied" class="text-green-600" />
                <flux:icon.link variant="mini" x-show="! copied" />
            </button>
            @if ($canJoin)
                <a href="{{ route('event.join', $event) }}" wire:navigate class="btn btn-primary flex-1">Bergabung</a>
            @else
                <span class="btn flex-1 bg-zinc-200 text-zinc-500" aria-disabled="true">{{ $full && ! $event->starts_at->isPast() ? 'Penuh' : 'Ditutup' }}</span>
            @endif
            @if ($community->whatsapp)
                <a href="https://wa.me/{{ $community->whatsapp }}?text={{ rawurlencode('Halo, saya mau tanya soal '.$event->title) }}" target="_blank" rel="noopener"
                   class="btn btn-icon bg-green-600 text-white hover:bg-green-700" aria-label="Chat pengurus via WhatsApp">
                    <x-icon-whatsapp class="size-5" />
                </a>
            @endif
        </div>
    </div>
</div>
