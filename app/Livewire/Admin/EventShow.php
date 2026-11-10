<?php

namespace App\Livewire\Admin;

use App\Models\Registration;
use App\Models\Event;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;

class EventShow extends Component
{
    public Event $event;

    #[Url]
    public string $filter = 'pending';

    /** Team whose line-up is expanded. */
    #[Url(as: 'tim')]
    public ?int $openTeam = null;

    public function mount(Event $event): void
    {
        abort_unless($event->community->user_id === auth()->id(), 403);
    }

    public function setStatus(int $registrationId, string $status): void
    {
        $error = $this->event->registrations()->findOrFail($registrationId)->changeStatus($status);

        if ($error) {
            Flux::toast(variant: 'danger', text: $error);
        }
    }

    public function setPaid(int $registrationId, bool $paid = true): void
    {
        $this->event->registrations()->findOrFail($registrationId)->markPaid($paid);
        Flux::toast(variant: 'success', text: $paid ? 'Ditandai lunas.' : 'Tanda lunas dibatalkan.');
    }

    public function toggleOpen(): void
    {
        $this->event->update(['status' => $this->event->status === 'open' ? 'closed' : 'open']);
    }

    public function toggleTeam(int $teamId): void
    {
        $this->openTeam = $this->openTeam === $teamId ? null : $teamId;
    }

    public function deleteEvent()
    {
        $community = $this->event->community;
        $this->event->deleteWithFiles();

        return $this->redirectRoute('admin.communities.show', $community, navigate: true);
    }

    public function render()
    {
        $registrations = $this->event->registrations()->with('slot.team', 'paymentMethod')->latest();
        $unpaid = fn ($q) => $q->where('status', 'accepted')->whereNull('paid_at')->whereHas('slot', fn ($s) => $s->where('price', '>', 0));
        match ($this->filter) {
            'unpaid' => $unpaid($registrations),
            'all' => null,
            default => $registrations->where('status', $this->filter),
        };

        return view('livewire.admin.event-show', [
            'registrations' => $registrations->get(),
            'counts' => $this->event->registrations()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
                ->put('unpaid', $unpaid($this->event->registrations())->count()),
            'teams' => $this->event->teams,
            'positions' => $this->event->slots()->withCount(['registrations as taken' => fn ($q) => $q->whereIn('status', Registration::HOLDING)])->get(),
            'lineup' => $this->openTeam
                ? $this->event->slots()->where('event_team_id', $this->openTeam)
                    ->with(['registrations' => fn ($q) => $q->whereIn('status', Registration::HOLDING)])->get()
                : collect(),
            'collected' => $this->event->collected(),
            'target' => $this->event->target(),
        ])->title($this->event->title);
    }
}
