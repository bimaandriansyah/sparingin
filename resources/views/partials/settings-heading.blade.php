@php $user = auth()->user(); @endphp
<header class="mb-4 space-y-4">
    <h1 class="display text-3xl font-bold">Akun</h1>

    <section class="card flex items-center gap-3 p-4">
        <x-avatar :name="$user->name" size="lg" tone="dark" />
        <div class="min-w-0">
            <p class="truncate font-semibold">{{ $user->name }}</p>
            <p class="truncate text-sm text-zinc-500 tabular-nums">+{{ $user->phone }}</p>
        </div>
    </section>
</header>
