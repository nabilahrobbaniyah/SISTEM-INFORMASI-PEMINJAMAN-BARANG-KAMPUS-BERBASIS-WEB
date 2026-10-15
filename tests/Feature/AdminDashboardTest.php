<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_chart_data_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tren peminjaman dan keterlambatan')
            ->assertSee('Distribusi status peminjaman');
    }

    public function test_staff_dashboard_lists_today_handover_due_and_overdue_loans(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
        $staff = User::factory()->create(['role' => 'petugas']);
        $borrower = User::factory()->create();
        $this->createLoan($borrower, 'PJM-TODAY-HANDOVER', 'disetujui', '2026-10-15', '2026-10-20');
        $this->createLoan($borrower, 'PJM-FUTURE-HANDOVER', 'disetujui', '2026-10-16', '2026-10-20');
        $this->createLoan($borrower, 'PJM-DUE-TODAY', 'dipinjam', '2026-10-10', '2026-10-15');
        $this->createLoan($borrower, 'PJM-OVERDUE', 'dipinjam', '2026-10-01', '2026-10-14');

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Serah-terima terjadwal hari ini')
            ->assertSee('Disetujui, belum diserahterimakan')
            ->assertSee('PJM-TODAY-HANDOVER')
            ->assertSee('PJM-FUTURE-HANDOVER')
            ->assertSee('PJM-DUE-TODAY')
            ->assertSee('PJM-OVERDUE');
    }

    private function createLoan(User $borrower, string $code, string $status, string $loanDate, string $dueDate): Loan
    {
        return Loan::create([
            'loan_code' => $code,
            'user_id' => $borrower->id,
            'purpose' => 'Pengujian dashboard operasional',
            'loan_date' => $loanDate,
            'due_date' => $dueDate,
            'status' => $status,
        ]);
    }
}
