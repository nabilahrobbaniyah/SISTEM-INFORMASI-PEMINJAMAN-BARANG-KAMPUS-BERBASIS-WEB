<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPrintAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_loan_owner_or_active_staff_can_view_the_receipt(): void
    {
        $owner = User::factory()->create(['role' => 'peminjam', 'is_active' => true]);
        $otherBorrower = User::factory()->create(['role' => 'peminjam', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        $loan = Loan::create([
            'loan_code' => 'PJM-PRINT-01',
            'user_id' => $owner->id,
            'purpose' => 'Uji bukti peminjaman',
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dipinjam',
        ]);
        $loan->details()->create(['item_id' => $item->id, 'quantity' => 1]);

        $this->actingAs($otherBorrower)->get(route('loans.print', $loan))->assertForbidden();
        $this->actingAs($owner)->get(route('loans.print', $loan))->assertOk();
        $this->actingAs($staff)->get(route('loans.print', $loan))->assertOk();
    }

    public function test_returned_receipt_shows_each_return_condition_and_quantity(): void
    {
        $owner = User::factory()->create(['role' => 'peminjam', 'is_active' => true]);
        $item = $this->createItem();
        $loan = Loan::create([
            'loan_code' => 'PJM-PRINT-RETURNED',
            'user_id' => $owner->id,
            'purpose' => 'Uji rincian kondisi pengembalian',
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dikembalikan',
            'returned_at' => now(),
        ]);
        $loan->details()->create([
            'item_id' => $item->id,
            'quantity' => 3,
            'returned_good_quantity' => 1,
            'returned_light_damage_quantity' => 1,
            'returned_heavy_damage_quantity' => 0,
            'returned_lost_quantity' => 1,
        ]);

        $this->actingAs($owner)
            ->get(route('loans.print', $loan))
            ->assertOk()
            ->assertSee('Rincian kondisi pengembalian')
            ->assertSee('Baik: 1')
            ->assertSee('Rusak ringan: 1')
            ->assertSee('Rusak berat: 0')
            ->assertSee('Hilang: 1')
            ->assertSee('Belum kembali: 0');
    }

    private function createItem(): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-PRINT-01',
            'name' => 'Proyektor',
            'total_quantity' => 1,
            'available_quantity' => 0,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }
}
