<?php

namespace App\Livewire\Public;

use App\Models\Event;
use App\Models\Registration;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Landing page: upcoming open events from every community (public ones only). */
#[Layout('layouts::public')]
#[Title('Cari open play bola')]
class Home extends Component
{
    #[Url(as: 'jenis')]
    public string $sport = '';

    public function render()
    {
        $events = Event::public()
            ->where('status', 'open')
            ->where('starts_at', '>', now())
            ->when(isset(Event::SPORTS[$this->sport]), fn ($q) => $q->where('sport', $this->sport))
            ->with('community', 'slots')
            ->withCount(['slots', 'registrations as taken' => fn ($q) => $q->whereIn('status', Registration::HOLDING)])
            ->orderBy('starts_at')
            ->limit(30)
            ->get()
            ->filter(fn ($e) => $e->taken < $e->slots_count) // only events you can still join
            ->values();

        return view('livewire.public.home', [
            'events' => $events,
            'byDay' => $events->groupBy(fn ($e) => $e->starts_at->toDateString()),
        ]);
    }
}
