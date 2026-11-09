{{--
    Admin registration card. Parent Livewire component must have setStatus(id, status) and setPaid(id, bool).
    Acceptance (Diterima) and payment (Lunas) are separate: cash players can be accepted and pay later.
--}}
@props(['r', 'showEvent' => false])
@php
    $free = $r->slot->price === 0;
    $statusChip = [
        'pending' => ['bg-amber-50 text-amber-700', 'clock', 'Menunggu'],
        'accepted' => ['bg-blue-50 text-blue-700', 'user-plus', 'Diterima'],
        'rejected' => ['bg-red-50 text-red-700', 'x-circle', 'Ditolak'],
    ][$r->status];
    // Accepting a transfer (proof checked) or a free spot also marks it paid; cash waits for "Tandai lunas".
    $acceptLabel = $r->payment_type === 'transfer' ? 'Terima & lunas' : 'Terima';
@endphp
<article {{ $attributes->class('card space-y-3 p-4') }}>
    <div class="flex items-center gap-3">
        <x-avatar :name="$r->name" :src="$r->photoUrl()" :tone="$r->isPaid() ? 'dark' : 'soft'" />
        <div class="min-w-0 flex-1">
            <h3 class="truncate font-semibold">{{ $r->name }}</h3>
            <p class="text-xs text-zinc-500">
                {{ $r->slot->team->name }} · {{ $r->slot->name }} · {{ $r->created_at->diffForHumans() }}
            </p>
        </div>
        <div class="flex flex-col items-end gap-1 text-right">
            <div class="stat-value text-sm">{{ $free ? 'Gratis' : 'Rp'.number_format($r->slot->price, 0, ',', '.') }}</div>
            <span class="chip {{ $statusChip[0] }}"><flux:icon :name="$statusChip[1]" variant="micro" />{{ $statusChip[2] }}</span>
        </div>
    </div>

    {{-- Payment line: method + paid state --}}
    @if (! $free)
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($r->paymentLabel())
                <span @class(['chip', 'bg-zinc-950 text-white' => $r->payment_type === 'cash', 'bg-zinc-100 text-zinc-700' => $r->payment_type !== 'cash'])>
                    <flux:icon :name="$r->payment_type === 'cash' ? 'banknotes' : 'credit-card'" variant="micro" />{{ $r->paymentLabel() }}
                </span>
            @endif
            @if ($r->isPaid())
                <span class="chip bg-green-50 text-green-700"><flux:icon.check-circle variant="micro" />Lunas · {{ $r->paid_at->translatedFormat('d M') }}</span>
            @elseif ($r->status !== 'rejected')
                <span class="chip bg-orange-50 text-orange-700"><flux:icon.exclamation-circle variant="micro" />Belum lunas</span>
            @endif
        </div>
    @endif

    @if ($showEvent)
        <a href="{{ route('admin.events.show', $r->event) }}" wire:navigate class="flex items-center gap-2 rounded-2xl bg-zinc-50 px-3 py-2 text-xs ring-1 ring-zinc-100">
            <flux:icon.calendar-days variant="micro" class="text-zinc-400" />
            <span class="truncate"><b>{{ $r->event->title }}</b> · {{ $r->event->starts_at->translatedFormat('D, d M · H:i') }}</span>
        </a>
    @endif

    {{-- In-page confirm: native confirm() is blocked in some webviews/PWAs, which made "Tolak" silently do nothing. --}}
    <div x-data="{ confirming: false }">
        <div class="flex gap-2" x-show="! confirming">
            @if ($r->proof_path)
                <a href="{{ route('admin.registrations.proof', $r) }}" target="_blank" class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Lihat bukti transfer {{ $r->name }}">
                    <flux:icon.photo variant="mini" />
                </a>
            @endif
            <a href="https://wa.me/{{ $r->phone }}" target="_blank" rel="noopener" class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Chat {{ $r->name }} via WhatsApp">
                <x-icon-whatsapp class="size-5 text-green-600" />
            </a>

            @if ($r->status === 'pending')
                <button type="button" class="btn min-h-11 flex-1 bg-red-50 text-sm text-red-700 hover:bg-red-100" x-on:click="confirming = true">Tolak</button>
                <button type="button" class="btn btn-primary min-h-11 flex-1 text-sm" wire:click="setStatus({{ $r->id }}, 'accepted')" wire:loading.attr="disabled">
                    <flux:icon.check variant="micro" /> {{ $acceptLabel }}
                </button>
            @elseif ($r->status === 'accepted')
                <button type="button" class="btn min-h-11 flex-1 bg-red-50 text-sm text-red-700 hover:bg-red-100" x-on:click="confirming = true">Tolak</button>
                @if ($free)
                    {{-- nothing to collect --}}
                @elseif ($r->isPaid())
                    <button type="button" class="btn btn-secondary min-h-11 flex-1 text-sm" wire:click="setPaid({{ $r->id }}, false)" wire:loading.attr="disabled">
                        Batalkan lunas
                    </button>
                @else
                    <button type="button" class="btn min-h-11 flex-1 bg-green-600 text-sm text-white hover:bg-green-700" wire:click="setPaid({{ $r->id }}, true)" wire:loading.attr="disabled">
                        <flux:icon.banknotes variant="micro" /> Tandai lunas
                    </button>
                @endif
            @else
                <button type="button" class="btn btn-secondary min-h-11 flex-1 text-sm" wire:click="setStatus({{ $r->id }}, 'accepted')" wire:loading.attr="disabled">
                    Terima lagi
                </button>
            @endif
        </div>

        <div x-show="confirming" x-cloak class="space-y-2 rounded-2xl bg-red-50 p-3" role="alertdialog" aria-label="Konfirmasi tolak {{ $r->name }}">
            <p class="text-sm text-red-800"><b>Tolak pendaftaran {{ $r->name }}?</b> Posisinya akan dibuka lagi untuk pemain lain.</p>
            <div class="flex gap-2">
                <button type="button" class="btn btn-secondary min-h-11 flex-1 text-sm" x-on:click="confirming = false">Batal</button>
                <button type="button" class="btn min-h-11 flex-1 bg-red-600 text-sm text-white hover:bg-red-700"
                        wire:click="setStatus({{ $r->id }}, 'rejected')" wire:loading.attr="disabled" x-on:click="confirming = false">
                    Ya, tolak
                </button>
            </div>
        </div>
    </div>
</article>
