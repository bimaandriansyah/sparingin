{{--
    Submit button for plain (non-Livewire) POST forms, e.g. login/register.
    The parent <form> must have: x-data="{ submitting: false }" x-on:submit="submitting = true"
    While the request is in flight it shows a spinner + loading text and blocks double submits.
--}}
@props(['loading' => 'Memproses…'])
<button type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting"
        {{ $attributes->class('btn btn-primary w-full') }}>
    <svg x-show="submitting" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <span x-show="! submitting">{{ $slot }}</span>
    <span x-show="submitting" x-cloak>{{ $loading }}</span>
</button>
