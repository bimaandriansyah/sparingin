<?php

namespace App\Livewire\Public;

use App\Models\Registration;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::public')]
class EventPlayers extends Component
{
    public Event $event;

    #[Url(as: 'tim')]
    public int $team = 0;

    public function render()
    {
        $teams = $this->event->teams;
        $this->team = min(max($this->team, 0), max($teams->count() - 1, 0));
        $current = $teams[$this->team] ?? null;

        return view('livewire.public.event-players', [
            'teams' => $teams,
            'current' => $current,
            'positions' => $current ? $current->slots()->with(['registrations' => fn ($q) => $q->whereIn('status', Registration::HOLDING)])->get() : collect(),
        ])->title('Daftar Pemain · '.$this->event->title);
    }
}
