<div class="space-y-6 px-4 pb-12 pt-2">
    {{-- Installed player app (start_url "/?pwa=1") jumps to the last community viewed. --}}
    <script>
        if (new URLSearchParams(location.search).has('pwa')) {
            try {
                const last = localStorage.getItem('sparingin:last');
                if (last && new URL(last).origin === location.origin) location.replace(last);
            } catch (e) {}
        }
    </script>

    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl bg-zinc-950 p-6 text-white shadow-card">
        <img src="{{ asset('images/logo-mark.png') }}" alt="" class="absolute -right-10 -top-6 h-52 rotate-6 opacity-90">
        <div class="relative pt-24">
            <span class="chip bg-white/15 text-white">Futsal · Mini Soccer · Sepak Bola</span>
            <h1 class="display mt-3 text-4xl font-bold">Main bola bareng,<br><span class="text-brand">tinggal gabung.</span></h1>
            <p class="mt-3 text-sm leading-relaxed text-white/75">Cari open play di sekitarmu, pilih posisi di lapangan, lalu bayar. Tanpa perlu bikin akun.</p>
            <div class="mt-5 flex gap-2">
                <a href="#event" class="btn flex-1 bg-brand text-white hover:opacity-90">Cari event</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn flex-1 bg-white/10 text-white hover:bg-white/20">Dashboard</a>
                @else
                    <a href="#pengurus" class="btn flex-1 bg-white/10 text-white hover:bg-white/20">Buat event</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Open events --}}
    <section id="event" class="scroll-mt-16 space-y-3">
        <div class="flex items-end justify-between px-1">
            <div>
                <h2 class="display text-2xl font-bold">Event terbuka</h2>
                <p class="text-xs text-zinc-500">Dari berbagai komunitas, masih bisa didaftar</p>
            </div>
            <span class="chip bg-white text-zinc-700 shadow-card tabular-nums">{{ $events->count() }} event</span>
        </div>

        <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filter jenis">
            @foreach (['' => 'Semua'] + \App\Models\Event::SPORTS as $value => $label)
                <button type="button" wire:click="$set('sport', '{{ $value }}')" aria-pressed="{{ $sport === $value ? 'true' : 'false' }}"
                        @class([
                            'min-h-10 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors',
                            'bg-zinc-950 text-white' => $sport === $value,
                            'bg-white text-zinc-600 shadow-card hover:bg-zinc-50' => $sport !== $value,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @forelse ($byDay as $day => $dayEvents)
            @php $date = \Illuminate\Support\Carbon::parse($day); @endphp
            <div class="space-y-2" wire:key="day-{{ $day }}">
                <h3 class="px-1 pt-1 text-sm font-semibold text-zinc-500">
                    {{ $date->isToday() ? 'Hari ini' : ($date->isTomorrow() ? 'Besok' : $date->translatedFormat('l')) }}
                    <span class="font-normal">· {{ $date->translatedFormat('j F') }}</span>
                </h3>
                @foreach ($dayEvents as $event)
                    @php $left = $event->slots_count - $event->taken; @endphp
                    <x-event-row :event="$event" :href="route('event.public', $event)" show-community wire:key="ev-{{ $event->id }}">
                        <span class="inline-flex items-center gap-1"><flux:icon.banknotes variant="micro" class="text-zinc-400" />{{ $event->priceLabel() }}</span>
                        <span @class([
                            'chip px-2 py-0.5',
                            'bg-orange-50 text-orange-700' => $left <= 3,
                            'bg-zinc-100 text-zinc-600' => $left > 3,
                        ])>{{ $left > 0 ? 'Sisa '.$left.' slot' : 'Penuh' }}</span>
                    </x-event-row>
                @endforeach
            </div>
        @empty
            <div class="card flex flex-col items-center p-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><flux:icon.calendar-days class="size-7" /></span>
                <h3 class="mt-3 font-semibold">Belum ada event terbuka</h3>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $sport ? 'Coba pilih jenis lain, atau cek lagi nanti.' : 'Cek lagi nanti, atau buat event untuk komunitasmu.' }}
                </p>
            </div>
        @endforelse
    </section>

    {{-- For organisers --}}
    <section id="pengurus" class="scroll-mt-16 space-y-3">
        <div class="px-1">
            <h2 class="display text-2xl font-bold">Untuk pengurus</h2>
            <p class="text-xs text-zinc-500">Kelola open play komunitasmu dari HP</p>
        </div>
        @php
            // [icon, step label, title, description, banner style]
            $banners = [
                ['calendar-days', 'Satu link', 'Semua jadwal dalam kalender', 'Bagikan satu link komunitas ke grup WhatsApp. Pemain lihat agenda dan langsung gabung.', 'hero-card'],
                ['user-group', 'Formasi', 'Pemain pilih posisi di lapangan', 'Atur jumlah pemain, nama tim, dan formasi. Pemain tinggal ketuk posisi kosong.', 'bg-zinc-950 text-white'],
                ['banknotes', 'Iuran', 'Transfer atau tunai, tercatat rapi', 'Bukti transfer masuk otomatis. Pemain tunai bisa bayar sebelum atau sesudah main.', 'bg-gradient-to-br from-green-600 to-emerald-800 text-white'],
                ['check-badge', 'Konfirmasi', 'Terima, tolak, tandai lunas', 'Semua dari HP. Dana terkumpul dan daftar pemain langsung terupdate.', 'bg-gradient-to-br from-zinc-800 to-zinc-950 text-white'],
            ];
        @endphp

        {{-- Swipeable banners (CSS scroll-snap); dots follow the scroll, autoplay stops once the user touches it. --}}
        <div x-data="{
                active: 0,
                count: {{ count($banners) + 1 }},
                timer: null,
                go(i) { const el = this.$refs.track; el.scrollTo({ left: el.children[i].offsetLeft - el.offsetLeft - 16, behavior: 'smooth' }) },
                sync() { const el = this.$refs.track; this.active = Math.round(el.scrollLeft / (el.children[0].offsetWidth + 12)) },
                stop() { clearInterval(this.timer) },
             }"
             x-init="if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => go((active + 1) % count), 5000)"
             aria-roledescription="carousel" aria-label="Fitur untuk pengurus">
            <div x-ref="track" x-on:scroll.debounce.60ms="sync()" x-on:pointerdown="stop()" x-on:touchstart.passive="stop()"
                 class="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 pb-1">
                @foreach ($banners as $i => [$icon, $step, $title, $desc, $style])
                    <article class="relative flex min-h-48 w-[86%] shrink-0 snap-start flex-col justify-end overflow-hidden rounded-3xl p-5 shadow-card {{ $style }}"
                             aria-roledescription="slide" aria-label="{{ $i + 1 }} dari {{ count($banners) + 1 }}: {{ $title }}">
                        <flux:icon :name="$icon" class="pointer-events-none absolute -right-4 -top-4 size-36 opacity-15" />
                        <span class="chip w-fit bg-white/20 text-white">{{ $step }}</span>
                        <h3 class="display mt-3 text-2xl font-bold leading-tight">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-white/80">{{ $desc }}</p>
                    </article>
                @endforeach

                {{-- Dedicated CTA slide at the end --}}
                <article class="relative flex min-h-48 w-[86%] shrink-0 snap-start flex-col justify-end overflow-hidden rounded-3xl border-2 border-brand bg-white p-5 shadow-card"
                         aria-roledescription="slide" aria-label="{{ count($banners) + 1 }} dari {{ count($banners) + 1 }}: Mulai sekarang">
                    <img src="{{ asset('images/logo-mark.png') }}" alt="" class="pointer-events-none absolute right-4 top-4 h-14 w-auto">
                    <span class="chip w-fit bg-brand-soft text-brand-dark">Mulai sekarang</span>
                    <h3 class="display mt-3 text-2xl font-bold leading-tight">Buat open play komunitasmu</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600">Daftar pakai nomor HP, buat komunitas, lalu bagikan link event-nya.</p>
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary relative mt-4 w-full">Ke dashboard</a>
                    @else
                        <a href="{{ route('register') }}" class="btn relative mt-4 w-full bg-brand text-white hover:opacity-90">Daftar admin</a>
                    @endauth
                </article>
            </div>

            <div class="mt-3 flex justify-center gap-1.5">
                @foreach (range(0, count($banners)) as $i)
                    <button type="button" x-on:click="stop(); go({{ $i }})" aria-label="Slide {{ $i + 1 }}"
                            :aria-current="active === {{ $i }}"
                            :class="active === {{ $i }} ? 'w-6 bg-zinc-950' : 'w-2 bg-zinc-300'"
                            class="h-2 rounded-full transition-all duration-300"></button>
                @endforeach
            </div>
        </div>

    </section>
</div>
