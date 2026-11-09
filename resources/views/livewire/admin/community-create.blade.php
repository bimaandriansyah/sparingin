<div class="space-y-5">
    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-zinc-500">
        <flux:icon.chevron-left variant="mini" /> Komunitas
    </a>

    <header>
        <h1 class="display text-3xl font-bold">Buat komunitas</h1>
        <p class="mt-1 text-sm text-zinc-500">Logo, deskripsi, WhatsApp pengurus, dan metode bayar bisa diatur setelah ini.</p>
    </header>

    <form wire:submit="create" class="card space-y-4 p-4">
        <flux:input wire:model="name" label="Nama komunitas" placeholder="contoh: Minisoccer Jaksel" required autofocus />
        <flux:input wire:model="city" label="Kota" placeholder="contoh: Jakarta Selatan" />
        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="create">Buat komunitas</span>
            <span wire:loading wire:target="create">Membuat…</span>
        </button>
    </form>
</div>
