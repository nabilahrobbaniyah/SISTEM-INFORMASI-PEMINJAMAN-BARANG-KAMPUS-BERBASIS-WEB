<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_loan_queries_prioritize_each_workflow_by_schedule_and_recency(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create();
        $today = now()->toDateString();

        $pendingNew = $this->createLoan($borrower, 'PJM-PENDING-NEW', 'menunggu', $today, $today);
        $pendingOld = $this->createLoan($borrower, 'PJM-PENDING-OLD', 'menunggu', $today, $today);
        $pendingNew->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        $pendingOld->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        $approvedLater = $this->createLoan($borrower, 'PJM-APPROVED-LATER', 'disetujui', now()->addDays(2)->toDateString(), now()->addDays(4)->toDateString());
        $approvedSooner = $this->createLoan($borrower, 'PJM-APPROVED-SOONER', 'disetujui', now()->addDay()->toDateString(), now()->addDays(3)->toDateString());

        $borrowedLater = $this->createLoan($borrower, 'PJM-DUE-LATER', 'dipinjam', $today, now()->addDays(3)->toDateString());
        $borrowedSooner = $this->createLoan($borrower, 'PJM-DUE-SOONER', 'dipinjam', $today, now()->addDay()->toDateString());

        $finishedOld = $this->createLoan($borrower, 'PJM-FINISHED-OLD', 'dikembalikan', $today, $today);
        $finishedLatest = $this->createLoan($borrower, 'PJM-FINISHED-LATEST', 'dikembalikan', $today, $today);
        $finishedOld->forceFill(['returned_at' => now()->subDay()])->save();
        $finishedLatest->forceFill(['returned_at' => now()])->save();

        $this->actingAs($staff);

        $this->assertOrder('menunggu', ['PJM-PENDING-OLD', 'PJM-PENDING-NEW']);
        $this->assertOrder('disetujui', ['PJM-APPROVED-SOONER', 'PJM-APPROVED-LATER']);
        $this->assertOrder('dipinjam', ['PJM-DUE-SOONER', 'PJM-DUE-LATER']);
        $this->assertOrder('dikembalikan', ['PJM-FINISHED-LATEST', 'PJM-FINISHED-OLD']);
    }

    private function assertOrder(string $status, array $expectedCodes): void
    {
        $this->get(route('admin.loans.index', ['status' => $status]))
            ->assertOk()
            ->assertViewHas('loans', fn ($loans) => $loans->pluck('loan_code')->all() === $expectedCodes);
    }

    private function createLoan(User $borrower, string $code, string $status, string $loanDate, string $dueDate): Loan
    {
        return Loan::create([
            'loan_code' => $code,
            'user_id' => $borrower->id,
            'purpose' => 'Pengujian urutan peminjaman',
            'loan_date' => $loanDate,
            'due_date' => $dueDate,
            'status' => $status,
        ]);
    }
}
