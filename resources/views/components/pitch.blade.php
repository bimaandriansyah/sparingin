{{-- Vertical football pitch (GK side at the bottom). Children are absolutely positioned by x/y %. --}}
<div {{ $attributes->class('relative overflow-hidden rounded-2xl bg-[repeating-linear-gradient(0deg,#2f7a3f_0,#2f7a3f_10%,#37884a_10%,#37884a_20%)]') }}>
    <div class="pointer-events-none absolute inset-[4%] rounded-sm border-2 border-white/60"></div>
    <div class="pointer-events-none absolute inset-x-[4%] top-1/2 border-t-2 border-white/60"></div>
    <div class="pointer-events-none absolute left-1/2 top-1/2 aspect-square w-1/4 -translate-1/2 rounded-full border-2 border-white/60"></div>
    <div class="pointer-events-none absolute left-1/4 right-1/4 top-[4%] h-[13%] border-2 border-t-0 border-white/60"></div>
    <div class="pointer-events-none absolute bottom-[4%] left-1/4 right-1/4 h-[13%] border-2 border-b-0 border-white/60"></div>
    {{ $slot }}
</div>
