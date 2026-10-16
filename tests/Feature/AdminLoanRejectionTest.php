<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoanRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejection_requires_reason_and_records_actor_time_status_and_borrower_notification(): void
    {
        $staff = User::factory()->create(['role' => 'petugas']);
        $borrower = User::factory()->create();
        $loan = $this->createLoan($borrower);

        $this->actingAs($staff)
            ->post(route('admin.loans.reject', $loan), [])
            ->assertSessionHasErrors('reason_code');

        $this->post(route('admin.loans.reject', $loan), [
            'reason_code' => 'Barang tidak tersedia',
        ])->assertRedirect();

        $loan = $loan->fresh();
        $this->assertSame('ditolak', $loan->status);
        $this->assertSame($staff->id, $loan->processed_by);
        $this->assertNotNull($loan->processed_at);
        $this->assertSame('Barang tidak tersedia', $loan->rejection_reason);
        $this->assertSame(1, AppNotification::where('user_id', $borrower->id)->where('loan_id', $loan->id)->count());
    }

    public function test_only_waiting_requests_can_be_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create();
        $loan = $this->createLoan($borrower, 'disetujui');

        $this->actingAs($staff)->post(route('admin.loans.reject', $loan), [
            'reason_code' => 'Jadwal tidak dapat dipenuhi',
        ])->assertSessionHas('error');

        $this->assertSame('disetujui', $loan->fresh()->status);
        $this->assertSame(0, AppNotification::where('loan_id', $loan->id)->count());
    }

    private function createLoan(User $borrower, string $status = 'menunggu'): Loan
    {
        return Loan::create([
            'loan_code' => 'PJM-REJECT-'.strtoupper(substr(md5((string) microtime()), 0, 6)),
            'user_id' => $borrower->id,
            'purpose' => 'Peminjaman barang',
            'loan_date' => now()->addDay()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => $status,
        ]);
    }
}
