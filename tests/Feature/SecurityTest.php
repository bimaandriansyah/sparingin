<?php

namespace Tests\Feature;

use App\Livewire\Admin\EventForm;
use App\Livewire\Public\CommunityPage;
use App\Livewire\Public\JoinEvent;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->event = Event::factory()->create(['players_per_team' => 5]);
        $this->event->rebuildTeams([['name' => 'A', 'formation' => '1-2-1']], 0, 1000);
    }

    public function test_locked_event_cannot_be_unlocked_from_the_browser(): void
    {
        $this->event->register($this->event->slots()->first(), 'X', '081200000009', null);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->event->community->user)->test(EventForm::class, ['event' => $this->event])
            ->set('locked', false);
    }

    public function test_rebuilding_teams_never_wipes_registrations(): void
    {
        $this->event->register($this->event->slots()->first(), 'X', '081200000009', null);

        try {
            $this->event->rebuildTeams([['name' => 'B', 'formation' => '2-2']], 0, 1000);
            $this->fail('rebuildTeams should refuse');
        } catch (\LogicException) {
        }
        $this->assertSame(1, $this->event->registrations()->count());
    }

    public function test_maps_link_must_be_http(): void
    {
        Livewire::actingAs($this->event->community->user)->test(EventForm::class, ['event' => $this->event])
            ->set('maps_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors('maps_url');
    }

    public function test_calendar_month_is_not_client_writable(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(CommunityPage::class, ['community' => $this->event->community])->set('month', 'bukan-bulan');
    }

    public function test_public_player_photo_is_reencoded_without_metadata(): void
    {
        Livewire::test(JoinEvent::class, ['event' => $this->event])
            ->set('name', 'Budi')->set('phone', '081234567890')
            ->set('photo', UploadedFile::fake()->image('me.jpg', 300, 300))
            ->call('next')
            ->call('pick', $this->event->slots()->where('name', 'GK')->value('id'))->call('next')
            ->call('register')->assertHasNoErrors();

        $path = $this->event->registrations()->sole()->photo_path;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertNotFalse(@imagecreatefromstring(Storage::disk('public')->get($path)));
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_guest_cannot_reach_admin_or_proofs(): void
    {
        $this->get(route('admin.pending'))->assertRedirect(route('login'));
        $this->get(route('admin.events.edit', $this->event))->assertRedirect(route('login'));
    }

    public function test_failed_login_and_register_return_json_errors_for_background_form(): void
    {
        $this->postJson(route('login.store'), ['phone' => '081234567890', 'password' => 'salah-sekali'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->postJson(route('register.store'), ['name' => '', 'phone' => '0812', 'password' => 'a', 'password_confirmation' => 'b'])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'password']);
    }

    public function test_owner_can_delete_event_with_its_files_and_open_a_team_lineup(): void
    {
        $reg = $this->event->register($this->event->slots()->first(), 'Budi', '081200000009', null);
        Storage::put('proofs/p.jpg', 'x');
        Storage::disk('public')->put('photos/f.jpg', 'x');
        $reg->update(['proof_path' => 'proofs/p.jpg', 'photo_path' => 'photos/f.jpg']);
        $team = $this->event->teams()->first();

        Livewire::actingAs($this->event->community->user)->test(\App\Livewire\Admin\EventShow::class, ['event' => $this->event])
            ->call('toggleTeam', $team->id)->assertSee('Budi')
            ->call('deleteEvent')->assertRedirect(route('admin.communities.show', $this->event->community));

        $this->assertModelMissing($this->event);
        $this->assertDatabaseCount('registrations', 0);
        Storage::assertMissing('proofs/p.jpg');
        Storage::disk('public')->assertMissing('photos/f.jpg');
    }

    public function test_stranger_cannot_open_or_delete_someone_elses_event(): void
    {
        $this->actingAs(\App\Models\User::factory()->create())
            ->get(route('admin.events.show', $this->event))->assertForbidden();
    }
}
