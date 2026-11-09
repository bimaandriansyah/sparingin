<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'phone' => '0'.substr($user->phone, 2), // typed the local way
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'phone' => '0'.substr($user->phone, 2), // typed the local way
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('phone');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'phone' => '0'.substr($user->phone, 2), // typed the local way
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_failed_login_shows_the_phone_as_typed(): void
    {
        $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['phone' => '089612345678', 'password' => 'salah'])
            ->assertSee('value="089612345678"', false)
            ->assertDontSee('value="6289612345678"', false);
    }

    public function test_user_can_log_in_with_0896_prefix(): void
    {
        $user = \App\Models\User::factory()->create(['phone' => '6289612345678']);

        $this->post(route('login.store'), ['phone' => '0896-1234-5678', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }
}
