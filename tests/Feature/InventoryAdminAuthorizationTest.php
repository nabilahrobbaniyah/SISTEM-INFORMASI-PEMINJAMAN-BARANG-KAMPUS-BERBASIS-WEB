<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_inventory_but_cannot_create_or_edit_item_details(): void
    {
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem();

        $this->actingAs($staff)
            ->get(route('admin.items.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('admin.items.create'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('admin.items.edit', $item))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.items.update', $item), [
                'name' => 'Manipulasi nama',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.items.adjust-stock', $item), [
                'action' => 'penambahan',
                'quantity' => 1,
                'reason' => 'Percobaan tanpa kewenangan',
            ])
            ->assertForbidden();
    }

    private function createItem(): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-AUTH-01',
            'name' => 'Proyektor',
            'total_quantity' => 2,
            'available_quantity' => 2,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }
}
