<div class="space-y-5" wire:poll.30s>
    <header>
        <h1 class="display text-3xl font-bold">Konfirmasi</h1>
        <p class="mt-1 text-sm text-zinc-500">
            {{ $tab === 'lunas' ? 'Pemain yang sudah diterima tapi belum bayar, misalnya bayar tunai setelah main.' : 'Cek pendaftar, lalu terima atau tolak.' }}
        </p>
    </header>

    <div class="segmented" role="group" aria-label="Kategori konfirmasi">
        <button type="button" wire:click="$set('tab', 'cek')" aria-pressed="{{ $tab === 'cek' ? 'true' : 'false' }}">
            Perlu dicek <span class="tabular-nums opacity-60">{{ $pendingCount }}</span>
        </button>
        <button type="button" wire:click="$set('tab', 'lunas')" aria-pressed="{{ $tab === 'lunas' ? 'true' : 'false' }}">
            Belum lunas <span class="tabular-nums opacity-60">{{ $unpaidCount }}</span>
        </button>
    </div>

    @if ($registrations->isNotEmpty())
        <section class="hero-card flex items-center justify-between p-5">
            <div>
                <p class="text-sm font-medium text-white/80">{{ $tab === 'lunas' ? 'Belum lunas' : 'Menunggu dicek' }}</p>
                <p class="display mt-1 text-4xl font-bold tabular-nums">{{ $registrations->count() }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-medium text-white/80">{{ $tab === 'lunas' ? 'Belum dibayar' : 'Total iuran' }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums">Rp{{ number_format($registrations->sum(fn ($r) => $r->slot->price), 0, ',', '.') }}</p>
            </div>
        </section>
    @endif

    <section class="space-y-3">
        @forelse ($registrations as $r)
            <x-registration-card :r="$r" show-event wire:key="reg-{{ $r->id }}" />
        @empty
            <div class="card flex flex-col items-center p-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-green-50 text-green-600"><flux:icon.check-badge class="size-8" /></span>
                <h2 class="mt-3 font-semibold">Semua beres</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tab === 'lunas' ? 'Semua pemain yang diterima sudah lunas.' : 'Tidak ada pendaftar yang perlu dicek.' }}</p>
            </div>
        @endforelse
    </section>
</div>
