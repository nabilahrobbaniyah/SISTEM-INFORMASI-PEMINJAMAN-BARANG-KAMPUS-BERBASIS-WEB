<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminItemInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_available_quantity_input_is_ignored(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();

        $this->actingAs($staff)->put(route('admin.items.update', $item), [
            'category_id' => $item->category_id,
            'item_code' => $item->item_code,
            'name' => $item->name,
            'total_quantity' => 5,
            'available_quantity' => 5,
            'condition' => 'baik',
            'location' => $item->location,
        ])->assertRedirect(route('admin.items.index'));

        $this->assertSame(3, $item->fresh()->available_quantity);
    }

    public function test_total_stock_cannot_be_reduced_during_an_active_loan(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        $borrower = User::factory()->create();
        $loan = Loan::create([
            'loan_code' => 'PJM-ACTIVE-01',
            'user_id' => $borrower->id,
            'purpose' => 'Uji peminjaman aktif',
            'loan_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dipinjam',
        ]);
        $loan->details()->create(['item_id' => $item->id, 'quantity' => 2]);

        $this->actingAs($staff)->put(route('admin.items.update', $item), [
            'category_id' => $item->category_id,
            'item_code' => $item->item_code,
            'name' => $item->name,
            'total_quantity' => 4,
            'condition' => 'baik',
            'location' => $item->location,
        ])->assertSessionHasErrors('total_quantity');

        $this->assertSame(5, $item->fresh()->total_quantity);
        $this->assertSame(3, $item->fresh()->available_quantity);
    }

    private function createItem(): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-001',
            'name' => 'Proyektor',
            'total_quantity' => 5,
            'available_quantity' => 3,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }
}
