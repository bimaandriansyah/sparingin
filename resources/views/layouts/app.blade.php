{{-- Admin layout: phone-width column on every screen, bottom nav bar. --}}
@php
    $user = auth()->user();
    $pendingCount = \App\Models\Registration::pendingFor($user)->count();
    $nav = [
        ['route' => 'dashboard', 'match' => ['dashboard', 'admin.communities.*'], 'icon' => 'user-group', 'label' => 'Komunitas'],
        ['route' => 'admin.events.index', 'match' => ['admin.events.*'], 'icon' => 'calendar-days', 'label' => 'Event'],
        ['route' => 'admin.pending', 'match' => ['admin.pending'], 'icon' => 'banknotes', 'label' => 'Konfirmasi', 'badge' => $pendingCount],
        ['route' => 'profile.edit', 'match' => ['profile.edit', 'security.edit'], 'icon' => 'user-circle', 'label' => 'Akun'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-zinc-200 text-zinc-900 antialiased">
        <div class="mx-auto min-h-dvh max-w-md bg-canvas shadow-sm">
            <header class="sticky top-0 z-20 flex h-14 items-center justify-between bg-canvas/90 px-3 backdrop-blur">
                <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
            </header>

            <main class="px-4 pb-28 pt-2">
                {{ $slot }}
            </main>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-md rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_-12px_rgb(0_0_0/0.15)]" aria-label="Menu admin">
            <div class="grid grid-cols-4">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs(...$item['match']); @endphp
                    <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                       @class([
                           'flex h-16 flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors',
                           'text-zinc-950' => $active,
                           'text-zinc-400 hover:text-zinc-700' => ! $active,
                       ])>
                        <span @class([
                            'relative flex h-8 w-14 items-center justify-center rounded-full transition-colors',
                            'bg-zinc-950 text-white' => $active,
                        ])>
                            <flux:icon :name="$item['icon']" :variant="$active ? 'solid' : 'outline'" class="size-5" />
                            @if (! empty($item['badge']))
                                <span class="absolute -right-0.5 -top-1 min-w-4 rounded-full bg-brand px-1 text-center text-[10px] font-bold leading-4 text-white ring-2 ring-white">
                                    {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                                </span>
                            @endif
                        </span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
