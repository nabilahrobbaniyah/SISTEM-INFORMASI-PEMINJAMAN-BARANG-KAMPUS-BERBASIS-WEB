<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_displays_card_and_table_modes(): void
    {
        $borrower = User::factory()->create(['role' => 'peminjam', 'is_active' => true]);
        $category = Category::create(['name' => 'Elektronik']);
        Item::create([
            'category_id' => $category->id,
            'item_code' => 'CAM-001',
            'name' => 'Kamera Kampus',
            'total_quantity' => 2,
            'available_quantity' => 2,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);

        $this->actingAs($borrower)
            ->get(route('peminjam.catalog'))
            ->assertOk()
            ->assertSee('Kamera Kampus')
            ->assertSee('Foto tidak tersedia');

        $this->actingAs($borrower)
            ->get(route('peminjam.catalog', ['view' => 'table']))
            ->assertOk()
            ->assertSee('Kamera Kampus')
            ->assertSee('Tabel');
    }
}
