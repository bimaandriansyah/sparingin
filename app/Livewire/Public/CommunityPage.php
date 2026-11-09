<?php

namespace App\Livewire\Public;

use App\Models\Registration;
use App\Models\Community;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::public')]
class CommunityPage extends Component
{
    public Community $community;

    #[Url(as: 'tgl')]
    public string $date = '';

    #[Locked]
    public string $month = '';

    public function mount(): void
    {
        $selected = $this->selectedDate();
        $this->date = $selected->toDateString();
        $this->month = $selected->format('Y-m');
    }

    public function shiftMonth(int $by): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->addMonthsNoOverflow($by)->format('Y-m');
    }

    public function selectDate(string $date): void
    {
        $this->date = $this->selectedDate($date)->toDateString();
    }

    private function selectedDate(?string $date = null): CarbonInterface
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date ?? $this->date)->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    public function render()
    {
        $first = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
        $gridStart = $first->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = $this->community->events()->public()
            ->whereBetween('starts_at', [$gridStart, $gridEnd->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        $selected = $this->selectedDate();

        return view('livewire.public.community-page', [
            'first' => $first,
            'days' => collect(range(0, $gridStart->diffInDays($gridEnd)))->map(fn ($i) => $gridStart->copy()->addDays($i)),
            'eventDays' => $events->groupBy(fn ($e) => $e->starts_at->toDateString()),
            'selected' => $selected,
            'dayEvents' => $this->community->events()->public()->whereDate('starts_at', $selected)->with('slots', 'community')->withCount([
                'registrations as taken' => fn ($q) => $q->whereIn('status', Registration::HOLDING),
            ])->orderBy('starts_at')->get(),
            'upcoming' => $this->community->events()->public()->where('status', 'open')->where('starts_at', '>', now())
                ->with('slots', 'community')->orderBy('starts_at')->limit(5)->get(),
        ])->title($this->community->name);
    }
}
