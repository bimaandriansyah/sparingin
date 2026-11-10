{{-- Inline error for an ajaxForm field (see resources/js/app.js). Server-rendered errors still show via Flux. --}}
@props(['field'])
<p data-error x-show="error('{{ $field }}')" x-text="error('{{ $field }}')" x-cloak role="alert"
   {{ $attributes->class('-mt-4 flex items-center gap-1.5 text-sm font-medium text-red-600') }}></p>
