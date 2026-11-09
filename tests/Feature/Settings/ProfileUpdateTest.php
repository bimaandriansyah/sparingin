<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('phone', '0812-3456-7890')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('6281234567890', $user->phone);
    }

    public function test_phone_must_be_unique_and_valid(): void
    {
        $other = User::factory()->create(['phone' => '6281234567890']);
        $this->actingAs(User::factory()->create());

        Livewire::test(Profile::class)->set('phone', '081234567890')->call('updateProfileInformation')
            ->assertHasErrors(['phone' => 'unique']);
        Livewire::test(Profile::class)->set('phone', '12345')->call('updateProfileInformation')
            ->assertHasErrors(['phone' => 'regex']);
    }

    public function test_delete_account_lives_on_security_tab_not_profile(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertDontSee('Hapus akun');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
