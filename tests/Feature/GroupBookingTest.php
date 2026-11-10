<?php

namespace Tests\Feature;

use App\Livewire\Admin\EventForm;
use App\Livewire\Admin\EventShow;
use App\Livewire\Public\JoinEvent;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GroupBookingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        // Futsal 5-a-side, two teams: GK Rp30.000 + 4 players Rp50.000 each = Rp230.000 per team.
        $this->event = Event::factory()->create(['sport' => 'futsal', 'players_per_team' => 5]);
        $this->event->rebuildTeams([['name' => 'Tim A', 'formation' => '1-2-1'], ['name' => 'Tim B', 'formation' => '2-2']], 30000, 50000);
    }

    private function slot(string $code, string $team = 'Tim A'): int
    {
        return $this->event->slots()->whereHas('team', fn ($q) => $q->where('name', $team))->where('name', $code)->value('id');
    }

    private function wizard()
    {
        return Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', 'Budi')->set('phone', '081234567890')->call('next');
    }

    public function test_quote_applies_team_package_or_best_group_tier(): void
    {
        $this->event->update(['team_price' => 200000, 'group_discounts' => [['min' => 2, 'off' => 5000], ['min' => 3, 'off' => 10000]]]);
        $teamA = $this->event->slots()->where('event_team_id', $this->event->teams[0]->id)->get();

        $whole = $this->event->quote($teamA);
        $this->assertSame([230000, 200000, 'Paket 1 tim'], [$whole['base'], $whole['total'], $whole['label']]);

        $three = $this->event->quote($teamA->take(3));
        $this->assertSame(130000 - 30000, $three['total']); // GK 30K + 2×50K, minus 10K each
        $this->assertSame('Promo grup 3+ orang', $three['label']);

        $this->assertNull($this->event->quote($teamA->take(1))['label']);
    }

    public function test_booker_books_friends_with_cash_and_promo(): void
    {
        $this->event->update(['group_discounts' => [['min' => 2, 'off' => 5000]]]);

        $this->wizard()
            ->call('pick', $this->slot('CF'))->call('pick', $this->slot('CM'))
            ->call('next')->assertHasErrors('names.'.$this->slot('CM')) // friend names are required
            ->set('names.'.$this->slot('CM'), 'Andi')
            ->call('next')->assertSet('step', 3)
            ->set('payment', 'cash')
            ->call('register')->assertHasNoErrors()->assertRedirect();

        $booker = Registration::whereNull('booker_id')->sole();
        $this->assertSame(['Budi', 'CF', 45000], [$booker->name, $booker->slot->name, $booker->amount]);
        $friend = $booker->members()->sole();
        $this->assertSame(['Andi', null, 'cash', 45000], [$friend->name, $friend->phone, $friend->payment_type, $friend->amount]);
        $this->assertSame(90000, $booker->groupTotal());

        $this->get(route('registration.status', $booker->token))->assertOk()->assertSee(['Andi', 'Rp90.000']);
    }

    public function test_booking_is_capped_at_one_team(): void
    {
        $c = $this->wizard();
        foreach ($this->event->slots()->pluck('id')->take(6) as $id) {
            $c->call('pick', $id);
        }
        $c->assertHasErrors('slotIds')->assertCount('slotIds', 5);
    }

    public function test_book_whole_team_button_and_admin_acts_on_the_group(): void
    {
        $this->event->update(['team_price' => 200000]);
        $c = $this->wizard()->call('pickTeam')->assertCount('slotIds', 5);
        foreach (array_slice($c->get('slotIds'), 1) as $i => $id) {
            $c->set("names.$id", "Teman $i");
        }
        $c->call('next')->set('payment', 'cash')->call('register')->assertHasNoErrors();

        $booker = Registration::whereNull('booker_id')->sole();
        $this->assertSame(200000, $booker->groupTotal());

        $admin = Livewire::actingAs($this->event->community->user)->test(EventShow::class, ['event' => $this->event])
            ->assertSee('+ 4 teman');

        $admin->call('setStatus', $booker->id, 'accepted');
        $this->assertSame(['accepted'], Registration::pluck('status')->unique()->values()->all());
        $this->assertSame(0, $this->event->collected()); // cash: accepted, not paid yet

        $admin->call('setPaid', $booker->id, true);
        $this->assertSame(0, Registration::whereNull('paid_at')->count());
        $this->assertSame(200000, $this->event->collected());

        $admin->call('setStatus', $booker->id, 'rejected');
        $this->assertSame(['rejected'], Registration::pluck('status')->unique()->values()->all());
    }

    public function test_rejected_booker_can_rebook_and_old_friends_are_dropped(): void
    {
        $first = $this->event->book([$this->slot('CF') => 'Budi', $this->slot('CM') => 'Andi'], '081234567890', null, null, null, 'cash');
        $first->changeStatus('rejected');

        $again = $this->event->book([$this->slot('GK', 'Tim B') => 'Budi'], '081234567890', null, null, null, 'cash');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Registration::count());
    }

    public function test_admin_saves_promo_settings(): void
    {
        Livewire::actingAs($this->event->community->user)->test(EventForm::class, ['event' => $this->event])
            ->set('team_price', '200000')
            ->call('addDiscount')->set('discounts.0.min', 3)->set('discounts.0.off', 10000)
            ->call('save')->assertHasNoErrors();

        $this->event->refresh();
        $this->assertSame(200000, $this->event->team_price);
        $this->assertSame([['min' => 3, 'off' => 10000]], $this->event->group_discounts);
    }
}
