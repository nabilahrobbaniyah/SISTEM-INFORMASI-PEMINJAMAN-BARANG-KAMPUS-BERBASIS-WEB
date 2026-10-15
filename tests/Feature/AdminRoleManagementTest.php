<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_change_requires_password_and_reason_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'peminjam']);

        $this->actingAs($admin)->patch(route('admin.users.role', $target), [
            'role' => 'petugas',
            'reason' => 'Ditugaskan mengelola serah-terima',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.users.edit', $target));

        $this->assertSame('petugas', $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'target_user_id' => $target->id,
            'old_value' => 'peminjam',
            'new_value' => 'petugas',
            'reason' => 'Ditugaskan mengelola serah-terima',
            'result' => 'berhasil',
        ]);
    }

    public function test_admin_cannot_change_own_role_and_invalid_reauthentication_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.users.role', $admin), [
            'role' => 'petugas',
            'reason' => 'Perubahan tugas',
            'current_password' => 'password',
        ])->assertSessionHasErrors('role');

        $target = User::factory()->create(['role' => 'peminjam']);
        $this->actingAs($admin)->patch(route('admin.users.role', $target), [
            'role' => 'petugas',
            'reason' => 'Perubahan tugas',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('audit_logs', [
            'target_user_id' => $target->id,
            'result' => 'gagal_autentikasi',
        ]);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_staff_cannot_manage_roles(): void
    {
        $staff = User::factory()->create(['role' => 'petugas']);
        $target = User::factory()->create(['role' => 'peminjam']);

        $this->actingAs($staff)
            ->patch(route('admin.users.role', $target), [
                'role' => 'admin',
                'reason' => 'Perubahan tugas',
                'current_password' => 'password',
            ])
            ->assertForbidden();

        $this->assertSame('peminjam', $target->fresh()->role);
    }

    public function test_admin_created_accounts_receive_separate_password_setup_and_email_verification_links(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Akun Baru',
            'email' => 'new-user@kampus.ac.id',
            'identity_number' => '123456789012',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new-user@kampus.ac.id')->firstOrFail();
        $this->assertSame('peminjam', $user->role);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertNotSame('password', $user->password);
    }

    public function test_user_edit_form_does_not_expose_a_password_or_role_field_in_general_account_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'peminjam']);

        $this->actingAs($admin)->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertSee('name="current_password"', false);
    }
}
