<div class="space-y-4">
    <nav class="segmented" aria-label="Pengaturan">
        <a href="{{ route('profile.edit') }}" wire:navigate class="flex items-center justify-center" @if (request()->routeIs('profile.edit')) aria-current="page" @endif>Profil</a>
        <a href="{{ route('security.edit') }}" wire:navigate class="flex items-center justify-center" @if (request()->routeIs('security.edit')) aria-current="page" @endif>Keamanan</a>
    </nav>

    <section class="card p-4">
        <h2 class="font-semibold">{{ $heading ?? '' }}</h2>
        <p class="text-sm text-zinc-500">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </section>

    {{-- Kept apart from the forms above so it isn't tapped by accident. --}}
    <form method="POST" action="{{ route('logout') }}" class="pt-4">
        @csrf
        <button type="submit" class="btn w-full bg-white text-red-600 shadow-card hover:bg-red-50" data-test="logout-button">
            <flux:icon.arrow-right-start-on-rectangle variant="mini" /> Keluar
        </button>
    </form>
</div>
