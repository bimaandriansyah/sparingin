@php $rp = fn (int $v) => $v === 0 ? 'Gratis' : 'Rp'.number_format($v, 0, ',', '.'); @endphp
<div class="space-y-4 px-4 pb-32 pt-2">
    <header class="flex items-center gap-2">
        <button type="button" wire:click="back" class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Kembali">
            <flux:icon.chevron-left variant="mini" />
        </button>
        <div class="min-w-0">
            <h1 class="display truncate text-2xl font-bold">Bergabung</h1>
            <p class="truncate text-xs text-zinc-500">{{ $event->title }} · {{ $event->starts_at->translatedFormat('D, d M · H:i') }}</p>
        </div>
    </header>

    {{-- Stepper --}}
    <ol class="card flex items-start justify-between px-4 py-3" aria-label="Langkah pendaftaran">
        @foreach (\App\Livewire\Public\JoinEvent::STEPS as $n => $label)
            <li class="flex flex-1 flex-col items-center gap-1 text-center" @if ($step === $n) aria-current="step" @endif>
                <div class="flex w-full items-center">
                    <span @class(['h-0.5 flex-1', 'bg-transparent' => $n === 1, 'bg-zinc-950' => $n > 1 && $step >= $n, 'bg-zinc-200' => $n > 1 && $step < $n])></span>
                    <span @class([
                        'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                        'bg-zinc-950 text-white' => $step >= $n,
                        'bg-zinc-100 text-zinc-400' => $step < $n,
                    ])>
                        @if ($step > $n) <flux:icon.check variant="micro" /> @else {{ $n }} @endif
                    </span>
                    <span @class(['h-0.5 flex-1', 'bg-transparent' => $n === 3, 'bg-zinc-950' => $n < 3 && $step > $n, 'bg-zinc-200' => $n < 3 && $step <= $n])></span>
                </div>
                <span @class(['text-[11px] font-semibold', 'text-zinc-950' => $step === $n, 'text-zinc-400' => $step !== $n])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    {{-- Step 1: Data diri --}}
    @if ($step === 1)
        <section class="card space-y-5 p-4">
            <label class="mx-auto flex w-fit cursor-pointer flex-col items-center gap-2">
                @if ($photo && ! $errors->has('photo'))
                    <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto" class="size-24 rounded-full object-cover ring-4 ring-brand-soft">
                @else
                    <span class="flex size-24 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><flux:icon.camera class="size-8" /></span>
                @endif
                <span class="text-sm font-semibold text-brand-dark">{{ $photo ? 'Ganti foto' : 'Tambah foto' }}</span>
                <span class="text-xs text-zinc-500">Opsional · tampil di lapangan</span>
                <input type="file" wire:model="photo" accept="image/*" class="sr-only">
            </label>
            <div wire:loading wire:target="photo" class="text-center text-xs text-zinc-500">Mengunggah…</div>
            <flux:error name="photo" />

            <flux:input wire:model="name" label="Nama panggilan" placeholder="Contoh: Budi" autocomplete="nickname" />
            <flux:input wire:model="phone" label="Nomor WhatsApp" description="Untuk dihubungi pengurus. Tidak ditampilkan ke publik." type="tel" inputmode="tel" autocomplete="tel" placeholder="08123456789" />
        </section>
    @endif

    {{-- Step 2: Pilih posisi --}}
    @if ($step === 2)
        <section class="card space-y-4 p-4">
            @if ($teams->count() > 1)
                <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Pilih tim">
                    @foreach ($teams as $i => $t)
                        <button type="button" wire:click="$set('team', {{ $i }})" aria-pressed="{{ $team === $i ? 'true' : 'false' }}"
                                @class([
                                    'min-h-10 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors',
                                    'bg-zinc-950 text-white' => $team === $i,
                                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' => $team !== $i,
                                ])>{{ $t->name }}</button>
                    @endforeach
                </div>
            @endif

            @if ($teamEmpty)
                <button type="button" wire:click="pickTeam" class="btn btn-secondary w-full text-sm">
                    <flux:icon.user-group variant="micro" /> Booking 1 tim ({{ $teams[$team]->name }})
                    @if ($event->team_price !== null)<span class="chip bg-brand-soft text-brand-dark">{{ $rp($event->team_price) }}</span>@endif
                </button>
            @endif

            <x-lineup :positions="$positions" selectable :selected="$slotIds" />

            @if ($picked->isEmpty())
                <p class="flex items-center justify-center gap-1.5 text-xs text-zinc-500">
                    <flux:icon.hand-raised variant="micro" /> Ketuk <b>+</b> untuk pilih posisi. Bisa pilih lebih dari satu untuk temanmu.
                </p>
            @endif
            <flux:error name="slotIds" />
        </section>

        @if ($picked->isNotEmpty())
            <section class="card space-y-3 p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Pemain <span class="text-sm font-normal text-zinc-500">· {{ $picked->count() }} posisi</span></h2>
                    @if ($picked->count() > 1)
                        <button type="button" wire:click="$set('slotIds', [])" class="text-xs font-semibold text-zinc-500">Kosongkan</button>
                    @endif
                </div>
                @foreach ($picked as $i => $slot)
                    <div wire:key="pk-{{ $slot->id }}" class="flex items-start gap-3">
                        <span class="mt-1 flex h-9 min-w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-zinc-950 px-1.5 text-white">
                            <span class="text-[11px] font-bold leading-none">{{ $slot->name }}</span>
                            <span class="mt-0.5 max-w-12 truncate text-[8px] leading-none text-white/60">{{ $slot->team->name }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            @if ($i === 0)
                                <div class="flex min-h-11 items-center justify-between rounded-2xl bg-zinc-50 px-3 text-sm">
                                    <span class="truncate font-semibold">{{ $name }}</span><span class="text-xs text-zinc-500">Kamu</span>
                                </div>
                            @else
                                <flux:input wire:model.blur="names.{{ $slot->id }}" placeholder="Nama teman" aria-label="Nama pemain posisi {{ $slot->name }}" />
                                <flux:error name="names.{{ $slot->id }}" />
                            @endif
                        </div>
                        <button type="button" wire:click="pick({{ $slot->id }})" class="btn btn-secondary btn-icon mt-0.5 min-h-10 w-10" aria-label="Hapus posisi {{ $slot->name }}">
                            <flux:icon.x-mark variant="micro" />
                        </button>
                    </div>
                @endforeach
                @include('livewire.public.partials.quote', ['quote' => $quote])
            </section>
        @endif
    @endif

    {{-- Step 3: Metode iuran --}}
    @if ($step === 3 && $picked->isNotEmpty())
        <section class="card space-y-3 p-4">
            <div class="flex items-center justify-between">
                <p class="font-semibold">{{ $name }}@if ($picked->count() > 1) <span class="font-normal text-zinc-500">+ {{ $picked->count() - 1 }} teman</span>@endif</p>
                <p class="text-xs text-zinc-500">{{ $picked->pluck('team.name')->unique()->join(', ') }} · {{ $picked->pluck('name')->join(', ') }}</p>
            </div>
            @include('livewire.public.partials.quote', ['quote' => $quote])
        </section>

        @if ($quote['total'] === 0)
            <section class="card flex items-start gap-3 p-4 text-sm">
                <flux:icon.gift class="shrink-0 text-green-600" />
                <p>Pendaftaran ini <b>gratis</b>. Tidak perlu transfer, langsung kirim pendaftaran. Pengurus akan mengonfirmasi pendaftaranmu.</p>
            </section>
        @else
            <section class="card space-y-3 p-4">
                <h2 class="font-semibold">Pilih metode iuran</h2>
                <div class="space-y-2" role="radiogroup" aria-label="Metode iuran">
                    @foreach ($paymentMethods as $pm)
                        @php $active = $payment === (string) $pm->id; @endphp
                        <div wire:key="pm-{{ $pm->id }}" x-data="{ copied: false }" @class([
                            'rounded-2xl p-3 transition-colors',
                            'bg-zinc-950 text-white' => $active,
                            'bg-zinc-50 ring-1 ring-zinc-100' => ! $active,
                        ])>
                            <button type="button" role="radio" aria-checked="{{ $active ? 'true' : 'false' }}" wire:click="$set('payment', '{{ $pm->id }}')"
                                    class="flex min-h-8 w-full items-center gap-3 text-left">
                                <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full border-2', 'border-white' => $active, 'border-zinc-300' => ! $active])>
                                    @if ($active) <span class="size-2.5 rounded-full bg-white"></span> @endif
                                </span>
                                <span class="flex-1 font-semibold">{{ $pm->provider }}</span>
                                <span @class(['text-xs', 'text-white/60' => $active, 'text-zinc-400' => ! $active])>Transfer</span>
                            </button>
                            @if ($active)
                                <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-white/10 p-3">
                                    <div>
                                        <div class="font-mono text-lg tabular-nums">{{ $pm->account_number }}</div>
                                        <div class="text-xs text-white/70">a.n. {{ $pm->account_name }}</div>
                                    </div>
                                    <button type="button" class="btn min-h-10 bg-white px-4 text-sm text-zinc-950"
                                            x-on:click="navigator.clipboard.writeText(@js($pm->account_number)); copied = true; setTimeout(() => copied = false, 1500)">
                                        <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Cash at the venue: always offered, no proof needed. --}}
                    @php $active = $payment === 'cash'; @endphp
                    <div wire:key="pm-cash" @class([
                        'rounded-2xl p-3 transition-colors',
                        'bg-zinc-950 text-white' => $active,
                        'bg-zinc-50 ring-1 ring-zinc-100' => ! $active,
                    ])>
                        <button type="button" role="radio" aria-checked="{{ $active ? 'true' : 'false' }}" wire:click="$set('payment', 'cash')"
                                class="flex min-h-8 w-full items-center gap-3 text-left">
                            <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full border-2', 'border-white' => $active, 'border-zinc-300' => ! $active])>
                                @if ($active) <span class="size-2.5 rounded-full bg-white"></span> @endif
                            </span>
                            <span class="flex-1 font-semibold">Tunai</span>
                            <span @class(['text-xs', 'text-white/60' => $active, 'text-zinc-400' => ! $active])>Bayar di lokasi</span>
                        </button>
                        @if ($active)
                            <div class="mt-3 flex items-start gap-2 rounded-xl bg-white/10 p-3 text-sm">
                                <flux:icon.banknotes variant="mini" class="mt-0.5 shrink-0" />
                                <p>Bayar <b>{{ $rp($quote['total']) }}</b> ke Admin sebelum/sesudah kick-off. Tidak perlu upload bukti.</p>
                            </div>
                        @endif
                    </div>
                </div>
                <flux:error name="payment" />
            </section>

            @if ($payment !== 'cash')
                <section class="card space-y-2 p-4">
                    <h2 class="font-semibold">Bukti transfer</h2>
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 p-3 hover:border-zinc-300">
                        @if ($proof && ! $errors->has('proof'))
                            <img src="{{ $proof->temporaryUrl() }}" alt="Pratinjau bukti" class="size-14 rounded-xl object-cover">
                            <span class="text-sm font-semibold">Ganti foto</span>
                        @else
                            <span class="flex size-14 items-center justify-center rounded-xl bg-zinc-100"><flux:icon.photo class="text-zinc-400" /></span>
                            <span class="text-sm"><span class="font-semibold">Upload screenshot</span><br><span class="text-zinc-500">JPG/PNG, maks. 5MB</span></span>
                        @endif
                        <input type="file" wire:model="proof" accept="image/*" class="sr-only">
                    </label>
                    <div wire:loading wire:target="proof" class="text-xs text-zinc-500">Mengunggah…</div>
                    <flux:error name="proof" />
                </section>
            @endif
        @endif
    @endif

    {{-- Bottom bar --}}
    <div class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-md bg-gradient-to-t from-canvas via-canvas/95 to-transparent px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-6">
        <div class="flex gap-2">
            <button type="button" wire:click="back" class="btn btn-secondary btn-icon" aria-label="Kembali"><flux:icon.chevron-left variant="mini" /></button>
            @if ($step < 3)
                <button type="button" wire:click="next" class="btn btn-primary flex-1" wire:loading.attr="disabled" wire:target="next,photo">Lanjut</button>
            @else
                <button type="button" wire:click="register" class="btn btn-primary flex-1" wire:loading.attr="disabled" wire:target="register,proof">
                    <span wire:loading.remove wire:target="register">Kirim pendaftaran</span>
                    <span wire:loading wire:target="register">Mengirim…</span>
                </button>
            @endif
        </div>
    </div>
</div>
