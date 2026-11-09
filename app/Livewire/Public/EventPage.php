<?php

namespace App\Livewire\Public;

use App\Models\Registration;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Event detail: info only. Lineup lives on EventPlayers, booking on JoinEvent. */
#[Layout('layouts::public')]
class EventPage extends Component
{
    public Event $event;

    public function render()
    {
        $players = $this->event->registrations()->whereIn('status', Registration::HOLDING)->latest()->get(['id', 'name', 'photo_path', 'status', 'paid_at']);

        return view('livewire.public.event-page', [
            'players' => $players,
            'total' => $this->event->slots()->count(),
            'collected' => $this->event->collected(),
            'target' => $this->event->target(),
        ])->title($this->event->title);
    }
}
