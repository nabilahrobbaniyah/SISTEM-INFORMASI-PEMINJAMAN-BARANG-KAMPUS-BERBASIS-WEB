<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryEvent;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_found_item_waits_for_inspection_before_becoming_available(): void
    {
        $item = $this->createItem();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item->update(['total_quantity' => 0, 'available_quantity' => 0, 'lost_quantity' => 1]);
        $inventoryBefore = $item->fresh()->inventoryQuantity();
        $workflow = app(LoanWorkflow::class);

        $workflow->reportFound($item, $staff, 1, 'Ditemukan di ruang kelas', null, now());
        $this->assertSame(1, $item->fresh()->inspection_quantity);
        $this->assertSame(0, $item->fresh()->available_quantity);
        $this->assertSame(0, $item->fresh()->total_quantity);

        $workflow->inspectFound($item, $staff, 1, 'baik', 'Lolos pemeriksaan fungsi', null, now());
        $this->assertSame(0, $item->fresh()->inspection_quantity);
        $this->assertSame(1, $item->fresh()->available_quantity);
        $this->assertSame(1, $item->fresh()->total_quantity);
        $this->assertSame($inventoryBefore, $item->fresh()->inventoryQuantity());
        $this->assertSame(2, $item->fresh()->inventoryEvents()->count());
    }

    public function test_repair_pass_does_not_change_physical_inventory_count(): void
    {
        $item = $this->createItem();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item->update(['total_quantity' => 0, 'available_quantity' => 0, 'repair_quantity' => 1]);
        InventoryEvent::create([
            'item_id' => $item->id,
            'user_id' => $staff->id,
            'action' => 'repair_started',
            'quantity' => 1,
            'occurred_at' => now()->subDay(),
            'description' => 'Mulai perbaikan',
            'result' => 'perbaikan',
        ]);

        app(LoanWorkflow::class)->completeRepair(
            $item,
            $staff,
            1,
            'layak',
            'Kabel diganti dan fungsi diperiksa',
            null,
            now()
        );

        $this->assertSame(1, $item->fresh()->total_quantity);
        $this->assertSame(1, $item->fresh()->available_quantity);
        $this->assertSame(0, $item->fresh()->repair_quantity);
        $this->assertSame(1, $item->fresh()->inventoryQuantity());
        $completion = $item->fresh()->inventoryEvents()->where('action', 'repair_completed')->firstOrFail();
        $this->assertNotNull($completion->started_at);
        $this->assertNotNull($completion->completed_at);
    }

    public function test_repair_completion_uses_the_latest_repair_start_in_history(): void
    {
        $item = $this->createItem();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item->update(['total_quantity' => 0, 'available_quantity' => 0, 'repair_quantity' => 1]);
        $oldStart = now()->subDays(10);
        $oldCompletion = now()->subDays(9);
        $latestStart = now()->subDay();

        InventoryEvent::create([
            'item_id' => $item->id,
            'user_id' => $staff->id,
            'action' => 'repair_started',
            'quantity' => 1,
            'occurred_at' => $oldStart,
            'started_at' => $oldStart,
            'description' => 'Perbaikan sebelumnya',
            'result' => 'perbaikan',
        ]);
        InventoryEvent::create([
            'item_id' => $item->id,
            'user_id' => $staff->id,
            'action' => 'repair_completed',
            'quantity' => 1,
            'occurred_at' => $oldCompletion,
            'started_at' => $oldStart,
            'completed_at' => $oldCompletion,
            'description' => 'Perbaikan sebelumnya selesai',
            'result' => 'layak',
        ]);
        InventoryEvent::create([
            'item_id' => $item->id,
            'user_id' => $staff->id,
            'action' => 'repair_started',
            'quantity' => 1,
            'occurred_at' => $latestStart,
            'started_at' => $latestStart,
            'description' => 'Perbaikan saat ini',
            'result' => 'perbaikan',
        ]);

        app(LoanWorkflow::class)->completeRepair(
            $item,
            $staff,
            1,
            'layak',
            'Barang sudah berfungsi',
            null,
            now()
        );

        $completion = $item->fresh()->inventoryEvents()->where('action', 'repair_completed')->latest('id')->firstOrFail();
        $this->assertSame($latestStart->format('Y-m-d H:i:s'), $completion->started_at->format('Y-m-d H:i:s'));
    }

    public function test_reported_damage_is_removed_from_borrowable_stock_and_recorded(): void
    {
        $item = $this->createItem();
        $staff = User::factory()->create(['role' => 'petugas']);

        app(LoanWorkflow::class)->reportDamage(
            $item,
            $staff,
            1,
            'rusak ringan',
            'Konektor kabel bengkok',
            null,
            now()
        );

        $item->refresh();
        $this->assertSame(0, $item->total_quantity);
        $this->assertSame(0, $item->available_quantity);
        $this->assertSame(1, $item->light_damage_quantity);
        $this->assertSame(1, $item->inventoryQuantity());
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'damage_reported',
            'state_before' => 'tersedia',
            'state_after' => 'rusak ringan',
            'quantity' => 1,
        ]);
    }

    public function test_damage_report_respects_peak_overlapping_reservations_not_sum_of_future_loans(): void
    {
        $item = $this->createItem();
        $item->update(['total_quantity' => 10, 'available_quantity' => 10]);
        $borrower = User::factory()->create();
        foreach ([3, 5] as $monthOffset) {
            $loan = Loan::create([
                'loan_code' => 'PJM-RESERVE-'.$monthOffset,
                'user_id' => $borrower->id,
                'purpose' => 'Uji jadwal yang tidak bertumpang tindih',
                'loan_date' => now()->addMonths($monthOffset)->toDateString(),
                'due_date' => now()->addMonths($monthOffset)->addDays(2)->toDateString(),
                'status' => 'disetujui',
            ]);
            $loan->details()->create(['item_id' => $item->id, 'quantity' => 5]);
        }
        $staff = User::factory()->create(['role' => 'petugas']);

        $this->assertSame(5, $item->maximumReservedFrom(now()->toDateString()));
        app(LoanWorkflow::class)->reportDamage($item, $staff, 5, 'rusak berat', 'Rumah barang retak', null, now());

        $item->refresh();
        $this->assertSame(5, $item->total_quantity);
        $this->assertSame(5, $item->available_quantity);
        $this->assertSame(5, $item->heavy_damage_quantity);
    }

    private function createItem(): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-LIFE',
            'name' => 'Proyektor',
            'total_quantity' => 1,
            'available_quantity' => 1,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }
}
