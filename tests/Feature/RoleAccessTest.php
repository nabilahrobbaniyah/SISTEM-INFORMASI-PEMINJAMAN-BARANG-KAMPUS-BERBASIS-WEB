<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_cannot_open_staff_routes(): void
    {
        $borrower = User::factory()->create(['role' => 'peminjam', 'is_active' => true]);

        $this->actingAs($borrower)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_staff_cannot_open_borrower_routes(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);

        $this->actingAs($staff)->get(route('peminjam.dashboard'))->assertForbidden();
    }

    public function test_inactive_staff_cannot_open_protected_routes(): void
    {
        $staff = User::factory()->create(['role' => 'admin', 'is_active' => false]);

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
    }
}
