<div class="space-y-5">
    <a href="{{ $event ? route('admin.events.show', $event) : route('admin.communities.show', $community) }}" wire:navigate
       class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-zinc-500">
        <flux:icon.chevron-left variant="mini" /> {{ $event ? $event->title : $community->name }}
    </a>

    <h1 class="display text-3xl font-bold">{{ $event ? 'Edit event' : 'Buat event' }}</h1>

    <form wire:submit="save" class="space-y-4">
        {{-- Details --}}
        <section class="card space-y-4 p-4">
            <h2 class="font-semibold">Detail acara</h2>
            <flux:input wire:model="title" label="Judul" placeholder="contoh: El Classico Jilid 1" required />
            <flux:input wire:model="starts_at" type="datetime-local" label="Tanggal & jam kick-off" required />
            <flux:input wire:model="location" label="Lokasi" placeholder="contoh: Stadion Wilis" required />
            <flux:input wire:model="maps_url" type="url" label="Link Google Maps" placeholder="https://maps.app.goo.gl/..." />
            <flux:textarea wire:model="description" label="Deskripsi" rows="4" placeholder="Jersey disediakan, wasit, fotografer, dll." />

            <flux:field>
                <flux:label>Poster</flux:label>
                <label class="block cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed border-zinc-200 hover:border-zinc-300">
                    @if ($poster && ! $errors->has('poster'))
                        <img src="{{ $poster->temporaryUrl() }}" class="aspect-[16/10] w-full object-cover" alt="Pratinjau poster">
                    @elseif ($event?->poster_path)
                        <img src="{{ asset('storage/'.$event->poster_path) }}" class="aspect-[16/10] w-full object-cover" alt="Poster saat ini">
                    @else
                        <span class="flex aspect-[16/7] flex-col items-center justify-center gap-1 text-sm text-zinc-500">
                            <flux:icon.photo class="text-zinc-300" />
                            <span class="font-semibold text-zinc-700">Upload poster</span>
                            <span class="text-xs">Opsional · JPG/PNG maks. 4MB</span>
                        </span>
                    @endif
                    <input type="file" wire:model="poster" accept="image/*" class="sr-only">
                </label>
                <div wire:loading wire:target="poster" class="text-xs text-zinc-500">Mengunggah…</div>
                <flux:error name="poster" />
            </flux:field>

            <flux:field>
                <flux:label>Jenis event</flux:label>
                <div class="segmented" role="group" aria-label="Jenis event">
                    @foreach (\App\Models\Event::VISIBILITIES as $value => $label)
                        <button type="button" wire:click="$set('visibility', '{{ $value }}')" aria-pressed="{{ $visibility === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <flux:description>
                    {{ $visibility === 'internal'
                        ? 'Tidak tampil di kalender publik. Pemain hanya bisa daftar lewat link event.'
                        : 'Tampil di kalender halaman komunitas, siapa saja bisa daftar.' }}
                </flux:description>
            </flux:field>
        </section>

        {{-- Match format --}}
        <section class="card space-y-4 p-4">
            <h2 class="font-semibold">Format & iuran</h2>
            @if ($locked)
                <div class="flex items-start gap-2 rounded-2xl bg-amber-50 p-3 text-sm text-amber-800">
                    <flux:icon.lock-closed variant="mini" class="mt-0.5 shrink-0" />
                    Sudah ada pendaftar, jadi jenis, jumlah pemain, formasi, dan daftar tim tidak bisa diubah. Nama tim dan iuran masih bisa.
                </div>
            @endif

            <flux:field>
                <flux:label>Jenis</flux:label>
                <div class="segmented" role="group" aria-label="Jenis">
                    @foreach (\App\Models\Event::SPORTS as $value => $label)
                        <button type="button" @disabled($locked) wire:click="chooseSport('{{ $value }}')"
                                aria-pressed="{{ $sport === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </flux:field>

            <flux:field>
                <flux:label>Jumlah pemain per tim</flux:label>
                <div class="flex items-center gap-3">
                    <button type="button" class="btn btn-secondary btn-icon" wire:click="stepPlayers(-1)" aria-label="Kurangi pemain"
                            @disabled($locked || $players_per_team <= \App\Support\Formation::MIN_PLAYERS)><flux:icon.minus variant="mini" /></button>
                    <input type="number" inputmode="numeric" wire:model.blur="players_per_team" @disabled($locked)
                           min="{{ \App\Support\Formation::MIN_PLAYERS }}" max="{{ \App\Support\Formation::MAX_PLAYERS }}"
                           class="h-12 w-20 rounded-2xl border border-zinc-200 bg-white text-center text-lg font-bold tabular-nums disabled:opacity-50"
                           aria-label="Jumlah pemain per tim">
                    <button type="button" class="btn btn-secondary btn-icon" wire:click="stepPlayers(1)" aria-label="Tambah pemain"
                            @disabled($locked || $players_per_team >= \App\Support\Formation::MAX_PLAYERS)><flux:icon.plus variant="mini" /></button>
                    <span class="text-sm text-zinc-500">termasuk kiper</span>
                </div>
                <flux:error name="players_per_team" />
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="gk_price" type="number" inputmode="numeric" min="0" step="1000" label="Iuran kiper (Rp)" />
                <flux:input wire:model="player_price" type="number" inputmode="numeric" min="0" step="1000" label="Iuran pemain (Rp)" />
            </div>

            {{-- Promo (optional) --}}
            <div class="space-y-3 rounded-2xl bg-zinc-50 p-3 ring-1 ring-zinc-100">
                <div class="flex items-center gap-2">
                    <flux:icon.tag variant="mini" class="text-brand-dark" />
                    <h3 class="text-sm font-semibold">Promo <span class="font-normal text-zinc-500">· opsional</span></h3>
                </div>
                <flux:input wire:model="team_price" type="number" inputmode="numeric" min="0" step="1000" label="Harga paket 1 tim (Rp)"
                            description="Total untuk yang booking 1 tim penuh. Kosongkan jika tidak ada." placeholder="Contoh: 350000" />

                <div class="space-y-2">
                    <p class="text-sm font-medium">Diskon grup</p>
                    @foreach ($discounts as $i => $d)
                        <div class="flex items-start gap-2" wire:key="disc-{{ $i }}">
                            <div class="w-16 shrink-0">
                                <flux:input wire:model="discounts.{{ $i }}.min" type="number" inputmode="numeric" min="2" aria-label="Minimal orang" placeholder="Min. orang" />
                                <flux:error name="discounts.{{ $i }}.min" />
                            </div>
                            <span class="mt-3 shrink-0 text-xs text-zinc-500">orang, potong</span>
                            <div class="min-w-0 flex-1">
                                <flux:input wire:model="discounts.{{ $i }}.off" type="number" inputmode="numeric" min="0" step="1000" aria-label="Potongan per orang (Rp)" placeholder="Rp/orang" />
                                <flux:error name="discounts.{{ $i }}.off" />
                            </div>
                            <button type="button" wire:click="removeDiscount({{ $i }})" class="btn btn-secondary btn-icon min-h-11 w-11" aria-label="Hapus diskon">
                                <flux:icon.x-mark variant="micro" />
                            </button>
                        </div>
                    @endforeach
                    @if (count($discounts) < 5)
                        <button type="button" wire:click="addDiscount" class="btn btn-secondary w-full text-sm"><flux:icon.plus variant="micro" /> Tambah diskon grup</button>
                    @endif
                    <p class="text-xs text-zinc-500">Potongan per orang jika daftar bareng minimal sekian orang, mis. 2 orang potong Rp5.000/orang. Paket 1 tim tidak digabung dengan diskon grup.</p>
                </div>
            </div>
        </section>

        {{-- Teams --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="font-semibold">Tim <span class="text-sm font-normal text-zinc-500">· {{ count($teams) }} tim, {{ count($teams) * (int) $players_per_team }} slot</span></h2>
            </div>
            <flux:error name="teams" />

            @foreach ($teams as $i => $team)
                @php
                    $validFormation = \App\Support\Formation::valid($team['formation'] ?? '', (int) $players_per_team);
                    $presets = \App\Support\Formation::presets((int) $players_per_team);
                    $isPreset = in_array($team['formation'], $presets, true);
                @endphp
                <article class="card space-y-4 p-4" wire:key="team-{{ $team['id'] ?? 'new' }}-{{ $i }}" x-data="{ custom: {{ $isPreset ? 'false' : 'true' }} }">
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <flux:input wire:model.blur="teams.{{ $i }}.name" label="Nama tim" placeholder="Tim Merah" />
                        </div>
                        @if (! $locked && count($teams) > 1)
                            <button type="button" wire:click="removeTeam({{ $i }})" class="btn btn-icon min-h-11 w-11 text-zinc-400 hover:bg-red-50 hover:text-red-600"
                                    aria-label="Hapus {{ $team['name'] }}"><flux:icon.trash variant="mini" /></button>
                        @endif
                    </div>


                    <flux:field>
                        <flux:label>Formasi</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($presets as $preset)
                                <button type="button" @disabled($locked) wire:click="setFormation({{ $i }}, '{{ $preset }}')" x-on:click="custom = false"
                                        @class([
                                            'chip min-h-9 px-3 text-sm tabular-nums disabled:opacity-50',
                                            'bg-zinc-950 text-white' => $team['formation'] === $preset,
                                            'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' => $team['formation'] !== $preset,
                                        ])>{{ $preset }}</button>
                            @endforeach
                            <button type="button" @disabled($locked) x-on:click="custom = true"
                                    :class="custom ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                                    class="chip min-h-9 px-3 text-sm disabled:opacity-50">Custom</button>
                        </div>
                        <div x-show="custom" x-cloak class="mt-2">
                            <flux:input wire:model.live.debounce.400ms="teams.{{ $i }}.formation" :disabled="$locked"
                                        placeholder="mis. 2-2-2" inputmode="numeric"
                                        description="Jumlah pemain per lini dari belakang ke depan, total {{ (int) $players_per_team - 1 }} + kiper." />
                        </div>
                        <flux:error name="teams.{{ $i }}.formation" />
                    </flux:field>

                    {{-- Preview --}}
                    <x-pitch class="aspect-[4/3]">
                        @if ($validFormation)
                            @foreach (\App\Support\Formation::layout($team['formation']) as [$code, $x, $y])
                                <div class="absolute -translate-1/2 text-center" style="left: {{ $x }}%; top: {{ $y }}%">
                                    <span @class(['mx-auto block size-6 rounded-full ring-2 ring-white', 'bg-zinc-950' => $code === 'GK', 'bg-brand' => $code !== 'GK'])></span>
                                    <span class="mt-0.5 block text-[9px] font-bold leading-none text-white">{{ $code }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm font-semibold text-white">
                                Formasi belum pas untuk {{ (int) $players_per_team }} pemain
                            </div>
                        @endif
                    </x-pitch>
                </article>
            @endforeach

            @if (! $locked && count($teams) < \App\Livewire\Admin\EventForm::MAX_TEAMS)
                <button type="button" wire:click="addTeam" class="btn w-full border-2 border-dashed border-zinc-300 bg-transparent text-zinc-600 hover:bg-white">
                    <flux:icon.plus variant="mini" /> Tambah tim
                </button>
            @endif
        </section>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="save,poster">
            <span wire:loading.remove wire:target="save">Simpan event</span>
            <span wire:loading wire:target="save">Menyimpan…</span>
        </button>
    </form>
</div>
