<?php

namespace App\Livewire\Admin;

use App\Models\Registration;
use App\Models\Event;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Event')]
class Events extends Component
{
    #[Url]
    public string $tab = 'upcoming';

    public function render()
    {
        $events = Event::whereHas('community', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('community')
            ->withCount([
                'slots',
                'registrations as taken' => fn ($q) => $q->whereIn('status', Registration::HOLDING),
                'registrations as pending' => fn ($q) => $q->where('status', 'pending'),
            ]);

        $this->tab === 'past'
            ? $events->where('starts_at', '<=', now())->latest('starts_at')
            : $events->where('starts_at', '>', now())->orderBy('starts_at');

        return view('livewire.admin.events', [
            'events' => $events->get(),
            'communities' => auth()->user()->communities()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
