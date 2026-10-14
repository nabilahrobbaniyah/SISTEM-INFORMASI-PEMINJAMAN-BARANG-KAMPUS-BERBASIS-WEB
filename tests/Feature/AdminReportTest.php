<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_rejects_unknown_status_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.reports.csv', ['status' => 'unexpected']))
            ->assertSessionHasErrors('status');
    }

    public function test_csv_export_neutralizes_formula_like_user_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $borrower = User::factory()->create(['name' => '=HYPERLINK("https://example.invalid")']);
        $category = Category::create(['name' => 'Aksesori']);
        $item = Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-CSV-01',
            'name' => '+SUM(A1:A2)',
            'total_quantity' => 1,
            'available_quantity' => 1,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
        $loan = Loan::create([
            'loan_code' => 'PJM-CSV-01',
            'user_id' => $borrower->id,
            'purpose' => 'Uji ekspor',
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dikembalikan',
        ]);
        $loan->details()->create(['item_id' => $item->id, 'quantity' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.reports.csv'));

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+SUM", $csv);
    }
}
