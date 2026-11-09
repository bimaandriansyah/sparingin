<div class="space-y-5">
    <header class="flex items-end justify-between gap-3">
        <div>
            <p class="text-sm text-zinc-500">Halo, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</p>
            <h1 class="display mt-1 text-3xl font-bold">Komunitas kamu</h1>
        </div>
        @if ($communities->isNotEmpty())
            <a href="{{ route('admin.communities.create') }}" wire:navigate class="btn btn-primary min-h-10 px-4 text-sm"><flux:icon.plus variant="micro" /> Buat</a>
        @endif
    </header>

    @if ($communities->isNotEmpty())
        <section class="space-y-2">
            @foreach ($communities as $community)
                <a href="{{ route('admin.communities.show', $community) }}" wire:navigate wire:key="c-{{ $community->id }}"
                   class="card pressable flex items-center gap-3 p-3">
                    <x-avatar :name="$community->name" :src="$community->logo_path ? asset('storage/'.$community->logo_path) : null" size="lg" tone="brand" />
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-semibold">{{ $community->name }}</h2>
                        <p class="mt-0.5 inline-flex items-center gap-1 text-xs text-zinc-500">
                            <flux:icon.map-pin variant="micro" />{{ $community->city ?? 'Kota belum diisi' }}
                        </p>
                    </div>
                    <span class="chip bg-zinc-100 text-zinc-700 tabular-nums">{{ $community->events_count }} event</span>
                    <flux:icon.chevron-right variant="mini" class="text-zinc-300" />
                </a>
            @endforeach
        </section>
    @else
        <section class="hero-card p-5">
            <flux:icon.user-group class="size-8" />
            <h2 class="display mt-3 text-2xl font-bold">Mulai dari komunitas</h2>
            <p class="mt-1 text-sm text-white/85">Buat komunitas dulu, lalu tambahkan event dan bagikan link-nya ke grup WhatsApp.</p>
            <a href="{{ route('admin.communities.create') }}" wire:navigate class="btn mt-4 w-full bg-white text-zinc-950 hover:bg-zinc-100">
                <flux:icon.plus variant="mini" /> Buat komunitas
            </a>
        </section>
    @endif
</div>
