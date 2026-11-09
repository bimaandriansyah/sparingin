<div class="space-y-5">
    <header class="flex items-end justify-between">
        <h1 class="display text-3xl font-bold">Event</h1>
        @if ($communities->count() === 1)
            <a href="{{ route('admin.events.create', $communities->first()) }}" wire:navigate class="btn btn-primary min-h-10 px-4 text-sm"><flux:icon.plus variant="micro" /> Buat</a>
        @elseif ($communities->count() > 1)
            <flux:dropdown align="end">
                <button type="button" class="btn btn-primary min-h-10 px-4 text-sm"><flux:icon.plus variant="micro" /> Buat</button>
                <flux:menu>
                    @foreach ($communities as $c)
                        <flux:menu.item :href="route('admin.events.create', $c)" wire:navigate>{{ $c->name }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endif
    </header>

    <div class="segmented" role="group" aria-label="Filter event">
        @foreach (['upcoming' => 'Mendatang', 'past' => 'Selesai'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    <section class="space-y-2">
        @forelse ($events as $event)
            <x-event-row :event="$event" :href="route('admin.events.show', $event)" show-community wire:key="ev-{{ $event->id }}">
                <span class="inline-flex items-center gap-1 tabular-nums"><flux:icon.users variant="micro" class="text-zinc-400" />{{ $event->taken }}/{{ $event->slots_count }}</span>
                @if ($event->pending)
                    <span class="chip bg-amber-50 text-amber-700">{{ $event->pending }} menunggu</span>
                @endif
            </x-event-row>
        @empty
            <div class="card p-6 text-center text-sm text-zinc-500">
                {{ $tab === 'past' ? 'Belum ada event yang selesai.' : 'Belum ada event mendatang.' }}
            </div>
        @endforelse
    </section>
</div>
