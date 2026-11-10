@php
    $r = $registration;
    $event = $r->event;
    $total = $r->groupTotal();
    $owed = $r->status === 'accepted' && ! $r->isPaid() && $total > 0;
    $price = 'Rp'.number_format($total, 0, ',', '.');
    [$tone, $icon, $title, $message] = match (true) {
        $r->status === 'rejected' => ['bg-red-600 text-white', 'x-circle', 'Ditolak', 'Pendaftaran tidak dapat dikonfirmasi. Hubungi pengurus atau daftar ulang.'],
        $r->status === 'pending' => ['bg-amber-400 text-amber-950', 'clock', 'Menunggu konfirmasi', match (true) {
            $r->payment_type === 'cash' => "Admin akan mengonfirmasi pendaftaranmu. Bayar {$price} tunai ke Admin sebelum/sesudah kick-off.",
            (bool) $r->proof_path => 'Admin sedang mengecek bukti transfer kamu. Halaman ini akan diperbarui otomatis.',
            default => 'Admin sedang mengecek pendaftaran kamu. Halaman ini akan diperbarui otomatis.',
        }],
        $owed => ['bg-blue-600 text-white', 'user-plus', 'Diterima', "Kamu sudah masuk daftar pemain. Jangan lupa bayar {$price} tunai ke Admin sebelum/sesudah kick-off."],
        default => ['bg-green-600 text-white', 'check-circle', $r->isPaid() && $total > 0 ? 'Lunas' : 'Diterima', 'Kamu sudah masuk daftar pemain. Sampai ketemu di lapangan!'],
    };
@endphp

<div class="space-y-4 px-4 pb-8 pt-2" @if ($r->status === 'pending' || $owed) wire:poll.15s @endif>
    <section class="rounded-3xl p-5 shadow-card {{ $tone }}" role="status">
        <flux:icon :name="$icon" class="size-10" />
        <h1 class="display mt-3 text-3xl font-bold">{{ $title }}</h1>
        <p class="mt-1 text-sm opacity-90">{{ $message }}</p>
    </section>

    <a href="{{ route('event.public', $event) }}" wire:navigate class="card pressable flex items-center gap-3 p-3">
        <x-date-block :date="$event->starts_at" />
        <div class="min-w-0 flex-1">
            <h2 class="truncate font-semibold">{{ $event->title }}</h2>
            <p class="truncate text-xs text-zinc-500">{{ $event->community->name }}</p>
            <div class="mt-1.5 flex flex-wrap gap-x-3 text-xs text-zinc-600">
                <span class="inline-flex items-center gap-1"><flux:icon.clock variant="micro" class="text-zinc-400" />{{ $event->starts_at->format('H:i') }}</span>
                <span class="inline-flex items-center gap-1"><flux:icon.map-pin variant="micro" class="text-zinc-400" />{{ $event->location }}</span>
            </div>
        </div>
        <flux:icon.chevron-right variant="mini" class="text-zinc-300" />
    </a>

    @if ($r->members->isNotEmpty())
        <section class="card divide-y divide-zinc-100 px-4">
            @foreach ($r->group() as $m)
                <div class="flex items-center gap-3 py-3" wire:key="m-{{ $m->id }}">
                    <span class="flex h-9 min-w-11 items-center justify-center rounded-xl bg-zinc-950 px-1.5 text-xs font-bold text-white">{{ $m->slot->name }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $m->name }}</span>
                    <span class="text-xs text-zinc-500">{{ $m->slot->team->name }}</span>
                </div>
            @endforeach
            <div class="flex items-center justify-between py-3 text-sm">
                <span class="font-semibold">Total iuran</span><span class="stat-value">{{ $total ? $price : 'Gratis' }}</span>
            </div>
        </section>
    @else
    <section class="card grid grid-cols-3 divide-x divide-zinc-100 py-4 text-center">
        <div class="min-w-0 px-2">
            <div class="stat-label">Nama</div>
            <div class="stat-value mt-1 truncate text-sm">{{ $r->name }}</div>
        </div>
        <div class="px-2">
            <div class="stat-label">Tim</div>
            <div class="stat-value mt-1 text-sm">{{ $r->slot->team->name }}</div>
        </div>
        <div class="px-2">
            <div class="stat-label">Posisi</div>
            <div class="stat-value mt-1 text-sm">{{ $r->slot->name }}</div>
        </div>
    </section>
    @endif

    <div class="flex items-start gap-3 rounded-2xl bg-brand-soft p-4 text-sm text-brand-dark">
        <flux:icon.bookmark variant="mini" class="mt-0.5 shrink-0" />
        <p>Simpan link halaman ini untuk cek status pendaftaran kamu.</p>
    </div>

    @if ($r->status === 'rejected')
        <a href="{{ route('event.public', $event) }}" wire:navigate class="btn btn-primary w-full">Daftar ulang</a>
    @else
        <a href="{{ route('event.public', $event) }}" wire:navigate class="btn btn-secondary w-full">Lihat event</a>
    @endif
</div>
