<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('campus.email_domains', ['kampus.ac.id']);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@kampus.ac.id',
            'identity_number' => '103072400001',
            'role' => 'admin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@kampus.ac.id',
            'identity_number' => '103072400001',
            'role' => 'peminjam',
        ]);
        $this->assertNull(User::where('email', 'test@kampus.ac.id')->firstOrFail()->email_verified_at);
        Notification::assertSentTo(User::where('email', 'test@kampus.ac.id')->firstOrFail(), VerifyEmail::class);
        $this->get(route('peminjam.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_registration_accepts_eight_digit_nip(): void
    {
        $this->post('/register', [
            'name' => 'Test Employee',
            'email' => 'employee@kampus.ac.id',
            'identity_number' => '24123456',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', ['identity_number' => '24123456', 'role' => 'peminjam']);
    }

    public function test_registration_rejects_identity_numbers_with_other_lengths(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@kampus.ac.id',
            'identity_number' => '12345678901',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('identity_number');
    }

    public function test_non_campus_email_cannot_register(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'identity_number' => '103072400001',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
