{{-- Price breakdown for a booking: base → promo → total. $quote comes from Event::quote(). --}}
@php $rp = fn (int $v) => $v === 0 ? 'Gratis' : 'Rp'.number_format($v, 0, ',', '.'); @endphp
<div class="space-y-1.5 rounded-2xl bg-zinc-50 p-3 text-sm tabular-nums">
    @if ($quote['label'])
        <div class="flex justify-between text-zinc-500"><span>Harga normal</span><span class="line-through">{{ $rp($quote['base']) }}</span></div>
        <div class="flex justify-between font-semibold text-green-700">
            <span class="inline-flex items-center gap-1"><flux:icon.tag variant="micro" />{{ $quote['label'] }}</span>
            <span>−{{ $rp($quote['base'] - $quote['total']) }}</span>
        </div>
    @endif
    <div class="flex items-baseline justify-between">
        <span class="font-semibold">Total iuran</span>
        <span class="display text-2xl font-bold normal-case">{{ $rp($quote['total']) }}</span>
    </div>
</div>
