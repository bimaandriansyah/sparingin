<?php

namespace Tests\Feature;

use App\Livewire\Admin\CommunityCreate;
use App\Livewire\Admin\EventForm;
use App\Livewire\Admin\EventShow;
use App\Livewire\Admin\Pending;
use App\Livewire\Public\JoinEvent;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        // Futsal, 1 team "Tim A" in 1-2-1: GK (free) + CB, CM, CM, CF (Rp50.000) — one seat each.
        $this->event = Event::factory()->create(['sport' => 'futsal', 'players_per_team' => 5]);
        $this->event->rebuildTeams([['name' => 'Tim A', 'formation' => '1-2-1']], 0, 50000);
        $this->event->community->paymentMethods()->create(['provider' => 'BCA', 'account_number' => '123', 'account_name' => 'Admin']);
    }

    /** Walks the 3-step join wizard: data diri → posisi → metode iuran. */
    private function register(string $position, string $name, string $phone, ?UploadedFile $photo = null)
    {
        return Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', $name)
            ->set('phone', $phone)
            ->set('photo', $photo)
            ->call('next')
            ->call('pick', $this->event->slots()->where('name', $position)->value('id'))
            ->call('next')
            ->set('payment', (string) $this->event->community->paymentMethods()->value('id'))
            ->set('proof', UploadedFile::fake()->image('bukti.jpg'))
            ->call('register');
    }

    public function test_rebuild_teams_follows_each_teams_formation(): void
    {
        $event = Event::factory()->create(['sport' => 'sepak_bola', 'players_per_team' => 11]);
        $event->rebuildTeams([
            ['name' => 'Tim Merah', 'formation' => '4-4-2'],
            ['name' => 'Tim Biru', 'formation' => '4-2-3-1'],
        ], 30000, 50000);

        $this->assertSame(22, $event->slots()->count());
        $this->assertSame(['Tim Merah', 'Tim Biru'], $event->teams->pluck('name')->all());
        $this->assertSame(2, $event->teams[1]->slots()->where('name', 'DM')->count());
        $this->assertSame(2 * 30000 + 20 * 50000, $event->target());
        $this->assertSame('30K–50K', $event->fresh()->priceLabel());
    }

    public function test_guest_can_register_and_see_status(): void
    {
        $this->register('CF', 'Budi', '0812-3456-7890')->assertHasNoErrors()->assertRedirect();

        $r = Registration::sole();
        $this->assertSame('6281234567890', $r->phone);
        $this->assertSame('pending', $r->status);
        Storage::disk('local')->assertExists($r->proof_path);

        $this->get(route('registration.status', $r->token))->assertOk()->assertSee(['Menunggu konfirmasi', 'Tim A', 'CF']);
    }

    public function test_position_taken_and_duplicate_phone_are_enforced(): void
    {
        $this->register('GK', 'A', '081200000001')->assertHasNoErrors();
        $this->register('GK', 'B', '081200000002')->assertHasErrors('slotIds');
        $this->register('CF', 'A lagi', '+6281200000001')->assertHasErrors('phone');
        $this->assertSame(1, Registration::count());
    }

    public function test_wizard_steps_validate_before_moving_on(): void
    {
        Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', 'Budi')->set('phone', '081234567890')
            ->call('next')->assertSet('step', 2)
            ->call('next')->assertHasErrors('slotIds')->assertSet('step', 2)
            ->call('pick', $this->event->slots()->where('name', 'CF')->value('id'))
            ->call('next')->assertSet('step', 3)
            ->call('register')->assertHasErrors(['payment', 'proof'])
            ->call('back')->assertSet('step', 2);
    }

    public function test_free_position_needs_no_proof_but_still_waits_for_admin(): void
    {
        Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', 'Kiper')->set('phone', '081299990000')->call('next')
            ->call('pick', $this->event->slots()->where('name', 'GK')->value('id'))->call('next')
            ->call('register')->assertHasNoErrors()->assertRedirect();

        $r = Registration::sole();
        $this->assertSame('pending', $r->status);
        $this->assertNull($r->proof_path);
        $this->get(route('registration.status', $r->token))->assertSee('Admin sedang mengecek pendaftaran kamu.');
    }

    public function test_cash_payment_needs_no_proof_and_waits_for_admin(): void
    {
        Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', 'Tunai')->set('phone', '081211110000')->call('next')
            ->call('pick', $this->event->slots()->where('name', 'CF')->value('id'))->call('next')
            ->call('register')->assertHasErrors(['payment'])
            ->set('payment', 'cash')
            ->call('register')->assertHasNoErrors()->assertRedirect();

        $r = Registration::sole();
        $this->assertSame(['cash', 'pending', null, null], [$r->payment_type, $r->status, $r->proof_path, $r->payment_method_id]);
        $this->assertSame('Tunai', $r->paymentLabel());
        $this->get(route('registration.status', $r->token))->assertSee('tunai ke Admin sebelum/sesudah kick-off.');
        $this->actingAs($this->event->community->user)->get(route('admin.pending'))->assertSee('Tunai');
    }

    public function test_cash_player_can_be_accepted_now_and_marked_paid_after_the_event(): void
    {
        Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', 'Andi')->set('phone', '081211110001')->call('next')
            ->call('pick', $this->event->slots()->where('name', 'CF')->value('id'))->call('next')
            ->set('payment', 'cash')->call('register');
        $r = Registration::sole();
        $owner = $this->event->community->user;

        // Accepting cash does NOT mark it paid.
        Livewire::actingAs($owner)->test(Pending::class)->call('setStatus', $r->id, 'accepted');
        $r->refresh();
        $this->assertSame('accepted', $r->status);
        $this->assertFalse($r->isPaid());
        $this->assertSame(0, $this->event->collected());
        $this->get(route('registration.status', $r->token))->assertSee(['Diterima', 'Jangan lupa bayar Rp50.000 tunai']);
        $this->get(route('event.players', $this->event))->assertSee('Terdaftar')->assertDontSee('>Lunas<', false);

        // It shows up under "Belum lunas", and can be paid after the match.
        $this->travel(3)->days();
        Livewire::actingAs($owner)->test(Pending::class)->set('tab', 'lunas')->assertSee('Andi')
            ->call('setPaid', $r->id, true)
            ->assertDontSee('Andi');
        $this->assertTrue($r->fresh()->isPaid());
        $this->assertSame(50000, $this->event->collected());
        $this->get(route('registration.status', $r->token))->assertSee('Lunas');

        // Undo works too.
        Livewire::actingAs($owner)->test(EventShow::class, ['event' => $this->event])->call('setPaid', $r->id, false);
        $this->assertFalse($r->fresh()->isPaid());
    }

    public function test_photo_and_payment_method_are_saved(): void
    {
        $this->register('CF', 'Budi', '081234567890', UploadedFile::fake()->image('budi.jpg'))->assertHasNoErrors();

        $r = Registration::sole();
        Storage::disk('public')->assertExists($r->photo_path);
        $this->assertSame('BCA', $r->paymentMethod->provider);
        $this->assertSame('transfer', $r->payment_type);
        $this->get(route('event.players', $this->event))->assertSee($r->photoUrl());
    }

    public function test_rejected_player_frees_seat_and_can_retry(): void
    {
        $this->register('CF', 'A', '081200000001');
        Registration::sole()->update(['status' => 'rejected']);

        $this->register('CF', 'A', '081200000001')->assertHasNoErrors();
        $this->assertSame('pending', Registration::sole()->status);
    }

    public function test_closed_event_rejects_registration(): void
    {
        $this->event->update(['status' => 'closed']);

        Livewire::test(JoinEvent::class, ['event' => $this->event])->assertRedirect(route('event.public', $this->event));
        $this->get(route('event.public', $this->event))->assertSee('Pendaftaran ditutup')->assertDontSee(route('event.join', $this->event));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->event->register($this->event->slots()->first(), 'Budi', '081234567890', null);
    }

    public function test_admin_confirms_and_lineup_shows_badge(): void
    {
        $this->register('CF', 'Budi Santoso', '081234567890');
        $r = Registration::sole();

        $this->get(route('event.players', $this->event))->assertSee(['Budi Santoso', 'Menunggu']);

        Livewire::actingAs($this->event->community->user)
            ->test(EventShow::class, ['event' => $this->event])
            ->call('setStatus', $r->id, 'accepted');

        // Transfer with proof: accepting also marks it paid.
        $this->assertSame('accepted', $r->fresh()->status);
        $this->assertTrue($r->fresh()->isPaid());
        $this->assertSame(50000, $this->event->collected());
        $this->get(route('event.players', $this->event))
            ->assertSee(['Budi Santoso', 'Lunas'])
            ->assertDontSee('6281234567890');
        $this->get(route('event.public', $this->event))->assertSee(['1/5 slot terisi', 'Rp50.000'])->assertDontSee('6281234567890');
    }

    public function test_admin_creates_event_and_formation_locks_after_registration(): void
    {
        $community = $this->event->community;
        Livewire::actingAs($community->user)
            ->test(EventForm::class, ['community' => $community])
            ->set('title', 'Open Play')
            ->set('sport', 'minisoccer')
            ->assertSet('players_per_team', 7)
            ->call('stepPlayers', 1) // 8 players
            ->call('addTeam')
            ->set('teams.2.name', 'Tim Biru')
            ->call('setFormation', 0, '3-3-1')
            ->set('teams.1.formation', '2-2-3') // custom
            ->set('starts_at', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->set('location', 'Lapangan X')
            ->set('gk_price', 30000)
            ->set('player_price', 45000)
            ->call('save')
            ->assertHasNoErrors();

        $created = $community->events()->where('title', 'Open Play')->sole();
        $this->assertSame(8, $created->players_per_team);
        $this->assertSame(['Tim Merah', 'Tim Putih', 'Tim Biru'], $created->teams->pluck('name')->all());
        $this->assertSame(['3-3-1', '2-2-3', '3-3-1'], $created->teams->pluck('formation')->all());
        $this->assertSame(24, $created->slots()->count());
        $this->assertSame(26, strlen($created->ulid));

        // After a registration, sport/teams are frozen but prices still update.
        $this->register('GK', 'A', '081200000001');
        Livewire::actingAs($community->user)
            ->test(EventForm::class, ['event' => $this->event])
            ->assertSet('locked', true)
            ->set('teams.0.name', 'Tim Garuda')
            ->set('gk_price', 20000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Tim Garuda'], $this->event->teams()->pluck('name')->all());
        $this->assertSame(5, $this->event->slots()->count());
        $this->assertSame(20000, $this->event->slots()->where('name', 'GK')->value('price'));
    }

    public function test_other_admin_cannot_access_event_or_proof(): void
    {
        $this->register('CF', 'Budi', '081234567890');
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('admin.events.show', $this->event))->assertForbidden();
        $this->actingAs($intruder)->get(route('admin.events.edit', $this->event))->assertForbidden();
        $this->actingAs($intruder)->get(route('admin.registrations.proof', Registration::sole()))->assertForbidden();
        $this->actingAs($this->event->community->user)->get(route('admin.registrations.proof', Registration::sole()))->assertOk();
    }

    public function test_pending_page_confirms_only_own_registrations(): void
    {
        $this->register('CF', 'Budi Santoso', '081234567890');
        $r = Registration::sole();
        $owner = $this->event->community->user;

        $this->actingAs($owner)->get(route('admin.pending'))->assertOk()->assertSee(['Budi Santoso', $this->event->title]);
        $this->actingAs($owner)->get(route('admin.events.index'))->assertOk()->assertSee(['1 menunggu', $this->event->title]);

        // Another admin can't see or act on it.
        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('admin.pending'))->assertDontSee('Budi Santoso');
        Livewire::actingAs($intruder)->test(Pending::class)->call('setStatus', $r->id, 'accepted')->assertNotFound();
        $this->assertSame('pending', $r->fresh()->status);

        Livewire::actingAs($owner)->test(Pending::class)->call('setStatus', $r->id, 'accepted')->assertDontSee('Budi Santoso');
        $this->assertSame('accepted', $r->fresh()->status);
    }

    public function test_reject_needs_no_browser_dialog(): void
    {
        $this->register('CF', 'Budi', '081234567890');
        $owner = $this->event->community->user;

        // The page must not rely on window.confirm() (blocked in some webviews/PWAs).
        $this->actingAs($owner)->get(route('admin.pending'))->assertDontSee('wire:confirm', false)->assertSee('Ya, tolak');

        Livewire::actingAs($owner)->test(Pending::class)->call('setStatus', Registration::sole()->id, 'rejected');
        $this->assertSame('rejected', Registration::sole()->status);
    }

    public function test_unrejecting_fails_when_position_was_retaken(): void
    {
        $this->register('GK', 'A', '081200000001');
        $a = Registration::sole();
        $a->changeStatus('rejected');
        $this->register('GK', 'B', '081200000002');

        $this->assertNotNull($a->changeStatus('accepted'));
        $this->assertSame('rejected', $a->fresh()->status);
    }

    public function test_messages_are_in_indonesian(): void
    {
        $this->post(route('login.store'), ['phone' => '081200000000', 'password' => 'salah'])
            ->assertSessionHasErrors(['phone' => 'Nomor HP atau kata sandi salah.']);

        Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->call('next')
            ->assertHasErrors(['name' => 'Nama panggilan wajib diisi.'])
            ->assertSet('step', 1);

        $this->get(route('login'))->assertSee(['Masuk ke akun kamu', 'Nomor HP', 'Belum punya akun?'])->assertDontSee('Lupa kata sandi?');
    }

    public function test_player_and_admin_pages_install_as_separate_apps(): void
    {
        $this->get(route('event.public', $this->event))->assertSee('/manifest.webmanifest')->assertDontSee('manifest-admin');
        $this->get(route('home'))->assertSee('/manifest.webmanifest');
        $this->get(route('login'))->assertSee('/manifest-admin.webmanifest');
        $this->actingAs($this->event->community->user)->get(route('dashboard'))->assertSee('/manifest-admin.webmanifest');

        $admin = json_decode(file_get_contents(public_path('manifest-admin.webmanifest')), true);
        $player = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertNotSame($admin['id'], $player['id']);
        $this->assertSame('/dashboard', $admin['start_url']);
    }

    public function test_formation_must_match_player_count_and_team_names_unique(): void
    {
        $community = $this->event->community;
        Livewire::actingAs($community->user)
            ->test(EventForm::class, ['community' => $community])
            ->set('title', 'X')->set('location', 'Y')->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('teams.0.formation', '3-3-3')
            ->set('teams.1.name', 'tim merah')
            ->call('save')
            ->assertHasErrors(['teams.0.formation', 'teams.1.name']);

        Livewire::actingAs($community->user)
            ->test(EventForm::class, ['community' => $community])
            ->call('stepPlayers', 10)
            ->assertSet('players_per_team', 11)
            ->assertSet('teams.0.formation', '4-4-2');
    }

    public function test_chosen_formation_survives_blur_and_same_sport_tap(): void
    {
        $event = Event::factory()->create(['sport' => 'minisoccer', 'players_per_team' => 9]);
        $event->rebuildTeams([['name' => 'A', 'formation' => '3-3-2'], ['name' => 'B', 'formation' => '3-3-2']], 0, 1000);

        Livewire::actingAs($event->community->user)->test(EventForm::class, ['event' => $event])
            ->call('setFormation', 0, '4-3-1')
            ->set('teams.1.formation', '2-4-2') // custom
            ->set('players_per_team', 9)         // number input re-sends on blur
            ->call('chooseSport', 'minisoccer')  // tapping the current sport
            ->assertSet('players_per_team', 9)
            ->assertSet('teams.0.formation', '4-3-1')
            ->assertSet('teams.1.formation', '2-4-2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['4-3-1', '2-4-2'], $event->teams()->pluck('formation')->all());
    }

    public function test_changing_sport_resets_only_formations_that_no_longer_fit(): void
    {
        $community = $this->event->community;
        Livewire::actingAs($community->user)->test(EventForm::class, ['community' => $community])
            ->call('chooseSport', 'futsal')
            ->assertSet('players_per_team', 5)
            ->assertSet('teams.0.formation', '1-2-1')
            ->call('setFormation', 0, '2-2')
            ->call('stepPlayers', 0) // same count
            ->assertSet('teams.0.formation', '2-2');
    }

    public function test_locked_event_cannot_change_structure(): void
    {
        $this->register('GK', 'A', '081200000001');
        Livewire::actingAs($this->event->community->user)
            ->test(EventForm::class, ['event' => $this->event])
            ->call('addTeam')->assertForbidden();
    }

    public function test_internal_event_is_hidden_from_calendar_but_reachable_by_link(): void
    {
        Livewire::actingAs($this->event->community->user)
            ->test(EventForm::class, ['event' => $this->event])
            ->assertSee('Umum')
            ->set('visibility', 'internal')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertTrue($this->event->fresh()->isInternal());

        $calendar = route('community.public', $this->event->community).'?tgl='.$this->event->starts_at->toDateString();
        $this->get($calendar)->assertOk()->assertDontSee($this->event->title);

        $this->get(route('event.public', $this->event))->assertOk()->assertSee('Internal');
        $this->get(route('event.players', $this->event))->assertOk()->assertSee('GK');
        $this->register('CF', 'Budi', '081234567890')->assertHasNoErrors();
    }

    public function test_community_is_created_on_its_own_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('admin.communities.create'))->assertDontSee('Nama komunitas');
        $this->get(route('admin.communities.create'))->assertOk()->assertSee('Nama komunitas');

        Livewire::actingAs($user)->test(CommunityCreate::class)
            ->set('name', 'FC Senin Malam')->set('city', 'Bandung')
            ->call('create')
            ->assertRedirect(route('admin.communities.show', $user->communities()->sole()));
        $this->assertSame('fc-senin-malam', $user->communities()->sole()->slug);
    }

    public function test_home_lists_open_public_events_from_all_communities(): void
    {
        $make = fn (array $attrs) => tap(Event::factory()->create(['players_per_team' => 5] + $attrs))
            ->rebuildTeams([['name' => 'A', 'formation' => '1-2-1']], 0, 1000);
        $make(['title' => 'Futsal Rabu', 'sport' => 'futsal']);              // other community, open
        $make(['title' => 'Rahasia', 'visibility' => 'internal']);           // internal
        $make(['title' => 'Sudah Tutup', 'status' => 'closed']);             // closed
        $make(['title' => 'Kemarin', 'starts_at' => now()->subDay()]);       // past

        $this->get(route('home'))->assertOk()
            ->assertSee([$this->event->title, 'Futsal Rabu', $this->event->community->name, 'Sisa 5 slot'])
            ->assertDontSee(['Rahasia', 'Sudah Tutup', 'Kemarin']);

        Livewire::test(\App\Livewire\Public\Home::class)->set('sport', 'minisoccer')
            ->assertDontSee('Futsal Rabu');
    }

    public function test_pages_render(): void
    {
        $this->assertStringContainsString($this->event->ulid, route('event.public', $this->event));
        $this->get(route('event.public', $this->event))->assertOk()->assertSee(['Gratis–50K', 'Lihat daftar pemain', route('event.join', $this->event)]);
        $this->get(route('event.players', $this->event))->assertOk()->assertSee(['GK', 'CF', 'Tim A']);
        $this->get(route('event.join', $this->event))->assertOk()->assertSee(['Data Diri', 'Pilih Posisi', 'Metode Iuran']);
        $this->get(route('community.public', $this->event->community).'?tgl='.$this->event->starts_at->toDateString())
            ->assertOk()->assertSee($this->event->title);
        $this->get(route('community.public', $this->event->community))->assertOk()->assertSee('Event mendatang');
        $this->get(route('community.public', $this->event->community).'?tgl=bukan-tanggal')->assertOk();

        $this->actingAs($this->event->community->user);
        $this->get(route('admin.events.show', $this->event))->assertOk()->assertSee('0/5');
        $this->get(route('admin.events.edit', $this->event))->assertOk();
        $this->get(route('admin.events.create', $this->event->community))->assertOk();
        $this->get(route('admin.communities.show', $this->event->community))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee($this->event->community->name);
    }
}
