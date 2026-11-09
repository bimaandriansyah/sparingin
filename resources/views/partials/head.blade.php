<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.png" type="image/png">
{{-- Admin + auth pages install as "SparingIn Admin"; player pages use layouts/public. --}}
<link rel="apple-touch-icon" href="/apple-touch-icon-admin.png">
<link rel="manifest" href="/manifest-admin.webmanifest">
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="SparingIn Admin">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
