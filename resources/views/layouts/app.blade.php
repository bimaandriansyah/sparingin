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

        {{-- Floating pill nav: active item expands to icon + label, others are icon only. --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-md bg-linear-to-t from-canvas via-canvas/90 to-transparent px-4 pt-6 pb-[max(0.75rem,env(safe-area-inset-bottom))]" aria-label="Menu admin">
            <div class="flex items-center gap-1 rounded-full bg-white p-1.5 shadow-[0_8px_30px_-6px_rgb(0_0_0/0.25)] ring-1 ring-black/5">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs(...$item['match']); @endphp
                    <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @else aria-label="{{ $item['label'] }}" @endif
                       @class([
                           'relative flex h-12 items-center justify-center gap-2 rounded-full transition-all duration-300',
                           'flex-[2.2] bg-zinc-950 text-white' => $active,
                           'flex-1 text-zinc-400 hover:text-zinc-700' => ! $active,
                       ])>
                        <flux:icon :name="$item['icon']" :variant="$active ? 'solid' : 'outline'" class="size-6 shrink-0" />
                        @if ($active)
                            <span class="text-sm font-semibold">{{ $item['label'] }}</span>
                        @endif
                        @if (! empty($item['badge']))
                            <span @class(['absolute top-1 min-w-4 rounded-full bg-brand px-1 text-center text-[10px] font-bold leading-4 text-white ring-2', 'left-1/2 ml-1' => ! $active, 'left-8 ring-zinc-950' => $active, 'ring-white' => ! $active])>
                                {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                            </span>
                        @endif
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
