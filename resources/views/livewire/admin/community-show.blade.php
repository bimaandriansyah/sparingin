<div class="space-y-5">
    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-zinc-500">
        <flux:icon.chevron-left variant="mini" /> Komunitas
    </a>

    {{-- Header --}}
    <section class="card p-4">
        <div class="flex items-center gap-3">
            <x-avatar :name="$community->name" :src="$community->logo_path ? asset('storage/'.$community->logo_path) : null" size="lg" tone="brand" />
            <div class="min-w-0">
                <h1 class="display truncate text-2xl font-bold">{{ $community->name }}</h1>
                <p class="text-xs text-zinc-500">{{ $events->count() }} event · {{ $paymentMethods->count() }} metode bayar</p>
            </div>
        </div>
        <div class="mt-4 flex gap-2" x-data="{ copied: false }">
            <a href="{{ route('community.public', $community) }}" target="_blank" class="btn btn-secondary flex-1 text-sm">
                <flux:icon.eye variant="mini" /> Lihat publik
            </a>
            <button type="button" class="btn btn-secondary flex-1 text-sm"
                    x-on:click="navigator.clipboard.writeText(@js(route('community.public', $community))); copied = true; setTimeout(() => copied = false, 1500)">
                <flux:icon.link variant="mini" /> <span x-text="copied ? 'Tersalin' : 'Salin link'">Salin link</span>
            </button>
        </div>
    </section>

    {{-- Events --}}
    <section class="space-y-2">
        <div class="flex items-center justify-between px-1">
            <h2 class="font-semibold">Event</h2>
            <a href="{{ route('admin.events.create', $community) }}" wire:navigate class="btn btn-primary min-h-10 px-4 text-sm">
                <flux:icon.plus variant="micro" /> Buat event
            </a>
        </div>
        @forelse ($events as $event)
            <x-event-row :event="$event" :href="route('admin.events.show', $event)" wire:key="e-{{ $event->id }}" />
        @empty
            <div class="card p-4 text-sm text-zinc-500">Belum ada event. Buat event pertama untuk mulai menerima pendaftar.</div>
        @endforelse
    </section>

    {{-- Payment methods --}}
    <section class="card space-y-3 p-4">
        <div>
            <h2 class="font-semibold">Metode pembayaran</h2>
            <p class="text-xs text-zinc-500">Ditampilkan ke pemain saat mendaftar.</p>
        </div>

        @foreach ($paymentMethods as $pm)
            <div class="flex items-center gap-3 rounded-2xl bg-zinc-50 p-3 ring-1 ring-zinc-100" wire:key="pm-{{ $pm->id }}">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-zinc-700 ring-1 ring-zinc-200">
                    <flux:icon.credit-card variant="mini" />
                </span>
                <div class="min-w-0 flex-1 text-sm">
                    <div class="font-semibold">{{ $pm->provider }}</div>
                    <div class="truncate text-xs text-zinc-500"><span class="font-mono tabular-nums">{{ $pm->account_number }}</span> · a.n. {{ $pm->account_name }}</div>
                </div>
                <div x-data="{ confirming: false }" class="flex shrink-0 items-center gap-1">
                    <button type="button" x-show="! confirming" x-on:click="confirming = true" class="btn btn-icon min-h-11 w-11 text-zinc-400 hover:bg-red-50 hover:text-red-600" aria-label="Hapus {{ $pm->provider }}">
                        <flux:icon.trash variant="mini" />
                    </button>
                    <template x-if="confirming">
                        <div class="flex items-center gap-1">
                            <button type="button" x-on:click="confirming = false" class="btn btn-secondary min-h-9 px-3 text-xs">Batal</button>
                            <button type="button" wire:click="deletePaymentMethod({{ $pm->id }})" class="btn min-h-9 bg-red-600 px-3 text-xs text-white hover:bg-red-700">Hapus</button>
                        </div>
                    </template>
                </div>
            </div>
        @endforeach

        <form wire:submit="addPaymentMethod" class="space-y-3 rounded-2xl border-2 border-dashed border-zinc-200 p-3">
            <flux:input wire:model="provider" label="Bank / e-wallet" placeholder="BCA, DANA, GoPay" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="accountNumber" label="Nomor" inputmode="numeric" placeholder="No. rekening / HP" />
                <flux:input wire:model="accountName" label="Atas nama" />
            </div>
            <button type="submit" class="btn btn-secondary w-full text-sm"><flux:icon.plus variant="micro" /> Tambah metode</button>
        </form>
    </section>

    {{-- Community info --}}
    <form wire:submit="save" class="card space-y-4 p-4">
        <h2 class="font-semibold">Info komunitas</h2>
        <flux:field>
            <flux:label>Logo</flux:label>
            <label class="flex cursor-pointer items-center gap-3">
                @if ($logo && ! $errors->has('logo'))
                    <img src="{{ $logo->temporaryUrl() }}" class="size-14 rounded-full object-cover" alt="">
                @else
                    <x-avatar :name="$community->name" :src="$community->logo_path ? asset('storage/'.$community->logo_path) : null" size="lg" tone="brand" />
                @endif
                <span class="btn btn-secondary min-h-10 px-4 text-sm"><flux:icon.photo variant="micro" /> Ganti logo</span>
                <input type="file" wire:model="logo" accept="image/*" class="sr-only">
            </label>
            <flux:error name="logo" />
        </flux:field>
        <flux:input wire:model="name" label="Nama" required />
        <flux:input wire:model="city" label="Kota" />
        <flux:textarea wire:model="description" label="Deskripsi" rows="3" />
        <flux:input wire:model="whatsapp" type="tel" inputmode="tel" label="WhatsApp pengurus" description="Untuk tombol Chat Pengurus di halaman publik." placeholder="08123456789" />
        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">Simpan perubahan</button>
    </form>
</div>
