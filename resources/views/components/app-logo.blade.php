{{-- SparingIn wordmark linking home/dashboard. --}}
@props(['sidebar' => false])
<a {{ $attributes->merge(['class' => 'flex items-center px-2 py-1']) }}>
    <img src="{{ asset('images/logo-wordmark.png') }}" alt="{{ config('app.name') }}" class="h-6 w-auto">
</a>
