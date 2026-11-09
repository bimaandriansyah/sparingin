<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'phone' => '0812 3456 7890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame('6281234567890', \App\Models\User::sole()->phone);
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        \App\Models\User::factory()->create(['phone' => '6281234567890']);

        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'phone' => '+62 812-3456-7890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['phone' => 'Nomor HP ini sudah terdaftar.']);
    }
}
