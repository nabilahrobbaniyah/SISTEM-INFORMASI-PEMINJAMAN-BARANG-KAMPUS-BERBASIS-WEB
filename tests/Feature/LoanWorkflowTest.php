<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LoanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_damaged_return_is_not_added_to_ready_stock_and_loan_is_closed_atomically(): void
    {
        $borrower = User::factory()->create();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem(total: 1, available: 0);
        $loan = $this->createBorrowedLoan($borrower, $item);
        $detail = $loan->details()->firstOrFail();

        app(LoanWorkflow::class)->receiveReturn(
            $loan,
            $staff,
            [$detail->id => ['good_quantity' => 0, 'light_damage_quantity' => 1, 'heavy_damage_quantity' => 0, 'lost_quantity' => 0]],
            'Perlu diperbaiki'
        );

        $this->assertSame(0, $item->fresh()->total_quantity);
        $this->assertSame(0, $item->fresh()->available_quantity);
        $this->assertSame(1, $item->fresh()->light_damage_quantity);
        $this->assertSame(0, $item->fresh()->repair_quantity);
        $this->assertSame('dikembalikan', $loan->fresh()->status);
        $this->assertSame('rusak ringan', $detail->fresh()->return_condition);
    }

    public function test_good_return_restores_ready_stock_once(): void
    {
        $borrower = User::factory()->create();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem(total: 2, available: 1);
        $loan = $this->createBorrowedLoan($borrower, $item);
        $detail = $loan->details()->firstOrFail();

        app(LoanWorkflow::class)->receiveReturn(
            $loan,
            $staff,
            [$detail->id => ['good_quantity' => 1, 'light_damage_quantity' => 0, 'heavy_damage_quantity' => 0, 'lost_quantity' => 0]],
            null
        );

        $this->assertSame(2, $item->fresh()->available_quantity);
        $this->assertSame(2, $item->fresh()->total_quantity);
        $this->assertSame('dikembalikan', $loan->fresh()->status);
    }

    public function test_partial_return_keeps_remaining_quantity_outstanding(): void
    {
        $borrower = User::factory()->create();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem(total: 3, available: 0);
        $loan = $this->createBorrowedLoan($borrower, $item, quantity: 3);
        $detail = $loan->details()->firstOrFail();

        app(LoanWorkflow::class)->receiveReturn(
            $loan,
            $staff,
            [$detail->id => ['good_quantity' => 1, 'light_damage_quantity' => 0, 'heavy_damage_quantity' => 0, 'lost_quantity' => 0]],
            null
        );

        $this->assertSame('dipinjam', $loan->fresh()->status);
        $this->assertSame(2, $detail->fresh()->outstandingQuantity());
        $this->assertSame(1, $item->fresh()->available_quantity);
        $this->assertSame(2, $item->fresh()->checkedOutQuantity());
    }

    public function test_return_tracks_mixed_conditions_and_missing_units_separately(): void
    {
        $borrower = User::factory()->create();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem(total: 4, available: 0);
        $loan = $this->createBorrowedLoan($borrower, $item, quantity: 4);
        $detail = $loan->details()->firstOrFail();

        app(LoanWorkflow::class)->receiveReturn(
            $loan,
            $staff,
            [$detail->id => [
                'good_quantity' => 1,
                'light_damage_quantity' => 1,
                'heavy_damage_quantity' => 1,
                'lost_quantity' => 1,
            ]],
            'Pemeriksaan campuran'
        );

        $this->assertSame([1, 1, 1, 1], [
            $detail->fresh()->returned_good_quantity,
            $detail->fresh()->returned_light_damage_quantity,
            $detail->fresh()->returned_heavy_damage_quantity,
            $detail->fresh()->returned_lost_quantity,
        ]);
        $this->assertSame(1, $item->fresh()->available_quantity);
        $this->assertSame(1, $item->fresh()->light_damage_quantity);
        $this->assertSame(1, $item->fresh()->heavy_damage_quantity);
        $this->assertSame(1, $item->fresh()->lost_quantity);
        $this->assertSame(1, $item->fresh()->total_quantity);
        $this->assertSame($item->fresh()->inventoryQuantity(), array_sum($item->fresh()->conditionQuantities()));
        $this->assertDatabaseHas('inventory_events', ['action' => 'lost_reported', 'quantity' => 1, 'user_id' => $staff->id]);
    }

    public function test_condition_quantities_cannot_exceed_outstanding_quantity_and_roll_back_stock_changes(): void
    {
        $borrower = User::factory()->create();
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem(total: 2, available: 0);
        $loan = $this->createBorrowedLoan($borrower, $item, quantity: 2);
        $detail = $loan->details()->firstOrFail();

        try {
            app(LoanWorkflow::class)->receiveReturn(
                $loan,
                $staff,
                [$detail->id => [
                    'good_quantity' => 2,
                    'light_damage_quantity' => 0,
                    'heavy_damage_quantity' => 0,
                    'lost_quantity' => 1,
                ]],
                null
            );
            $this->fail('Mismatched condition quantities must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tidak boleh melebihi sisa', $exception->getMessage());
        }

        $this->assertSame(0, $item->fresh()->available_quantity);
        $this->assertSame(0, $detail->fresh()->returnedQuantity());
        $this->assertSame('dipinjam', $loan->fresh()->status);
    }

    public function test_starting_repair_moves_light_damage_without_changing_recorded_inventory_total(): void
    {
        $staff = User::factory()->create(['role' => 'petugas']);
        $item = $this->createItem(total: 0, available: 0);
        $item->update(['light_damage_quantity' => 2]);
        $before = $item->fresh()->inventoryQuantity();

        app(LoanWorkflow::class)->startRepair($item, $staff, 1, 'Mulai mengganti komponen', null, now());

        $this->assertSame(1, $item->fresh()->light_damage_quantity);
        $this->assertSame(1, $item->fresh()->repair_quantity);
        $this->assertSame($before, $item->fresh()->inventoryQuantity());
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'repair_started',
            'state_before' => 'rusak ringan',
            'state_after' => 'perbaikan',
            'user_id' => $staff->id,
        ]);
    }

    public function test_inventory_adjustments_record_audit_values_and_disposal_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem(total: 2, available: 2);
        $item->update([
            'total_quantity' => 0,
            'available_quantity' => 0,
            'heavy_damage_quantity' => 2,
        ]);
        $workflow = app(LoanWorkflow::class);

        $workflow->adjustInventory($item, $admin, 'penambahan', 3, 'Pembelian dengan dokumen PO-42');
        $this->assertSame(5, $item->fresh()->total_quantity);
        $this->assertSame(5, $item->fresh()->available_quantity);
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'stock_added',
            'quantity_before' => 2,
            'quantity_after' => 5,
            'user_id' => $admin->id,
        ]);

        $workflow->adjustInventory($item->fresh(), $admin, 'penghapusan', 2, 'Pemusnahan barang rusak', 'rusak berat');
        $this->assertSame(2, $item->fresh()->disposed_quantity);
        $this->assertSame(5, $item->fresh()->inventoryQuantity());
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'stock_disposed',
            'state_before' => 'rusak berat',
            'state_after' => 'dihapuskan',
            'user_id' => $admin->id,
        ]);
    }

    private function createItem(int $total, int $available): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-001',
            'name' => 'Proyektor',
            'total_quantity' => $total,
            'available_quantity' => $available,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }

    private function createBorrowedLoan(User $borrower, Item $item, int $quantity = 1): Loan
    {
        $loan = Loan::create([
            'loan_code' => 'PJM-TEST-01',
            'user_id' => $borrower->id,
            'purpose' => 'Uji alur pengembalian',
            'loan_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dipinjam',
        ]);
        $loan->details()->create(['item_id' => $item->id, 'quantity' => $quantity]);

        return $loan;
    }
}
