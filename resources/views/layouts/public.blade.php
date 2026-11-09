{{-- Public pages are always light (no @fluxAppearance), mobile-first. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
        <title>{{ filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name') }}</title>
        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#ffffff">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="SparingIn">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh bg-zinc-200 text-zinc-900 antialiased">
        <div class="mx-auto min-h-dvh max-w-md bg-canvas shadow-sm">
            <header class="sticky top-0 z-20 flex h-14 items-center justify-center bg-canvas/90 backdrop-blur">
                <a href="{{ route('home') }}" aria-label="{{ config('app.name') }} — beranda"><img src="{{ asset('images/logo-wordmark.png') }}" alt="{{ config('app.name') }}" class="h-6 w-auto"></a>
            </header>

            {{ $slot }}
        </div>

        @fluxScripts
    </body>
</html>
