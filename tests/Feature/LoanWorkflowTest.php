<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            [$detail->id => ['condition' => 'rusak ringan']],
            'Perlu diperbaiki'
        );

        $this->assertSame(0, $item->fresh()->total_quantity);
        $this->assertSame(0, $item->fresh()->available_quantity);
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
            [$detail->id => ['condition' => 'baik']],
            null
        );

        $this->assertSame(2, $item->fresh()->available_quantity);
        $this->assertSame(2, $item->fresh()->total_quantity);
        $this->assertSame('dikembalikan', $loan->fresh()->status);
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

    private function createBorrowedLoan(User $borrower, Item $item): Loan
    {
        $loan = Loan::create([
            'loan_code' => 'PJM-TEST-01',
            'user_id' => $borrower->id,
            'purpose' => 'Uji alur pengembalian',
            'loan_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dipinjam',
        ]);
        $loan->details()->create(['item_id' => $item->id, 'quantity' => 1]);

        return $loan;
    }
}
