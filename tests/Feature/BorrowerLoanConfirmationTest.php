<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerLoanConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_reviewed_then_confirmed_and_repeated_confirmation_does_not_duplicate_it(): void
    {
        $borrower = User::factory()->create(['role' => 'peminjam']);
        $item = $this->createItem();
        $data = $this->requestData($item);

        $this->actingAs($borrower)
            ->post(route('peminjam.loans.review'), $data)
            ->assertRedirect(route('peminjam.loans.confirmation'));

        $this->get(route('peminjam.loans.confirmation'))
            ->assertOk()
            ->assertSee('Kembali untuk Mengedit')
            ->assertSee('Konfirmasi Pengajuan')
            ->assertSee($item->item_code);

        $key = session('loan_confirmation.key');
        $this->post(route('peminjam.loans.store'), ['submission_key' => $key])
            ->assertRedirect();
        $this->post(route('peminjam.loans.store'), ['submission_key' => $key])
            ->assertRedirect();

        $this->assertSame(1, Loan::where('user_id', $borrower->id)->count());
    }

    public function test_confirmation_rechecks_availability_before_creating_loan(): void
    {
        $borrower = User::factory()->create(['role' => 'peminjam']);
        $item = $this->createItem();

        $this->actingAs($borrower)->post(route('peminjam.loans.review'), $this->requestData($item));
        $item->update(['total_quantity' => 0, 'available_quantity' => 0]);

        $this->post(route('peminjam.loans.store'), [
            'submission_key' => session('loan_confirmation.key'),
        ])->assertRedirect(route('peminjam.loans.create'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('loans', 0);
    }

    private function requestData(Item $item): array
    {
        return [
            'loan_date' => now()->addDay()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'purpose' => 'Kegiatan akademik',
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ];
    }

    private function createItem(): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-CONFIRM',
            'name' => 'Proyektor',
            'total_quantity' => 1,
            'available_quantity' => 1,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }
}
