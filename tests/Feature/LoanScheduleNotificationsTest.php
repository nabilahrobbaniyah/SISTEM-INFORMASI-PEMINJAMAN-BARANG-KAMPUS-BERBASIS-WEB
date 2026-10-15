<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanScheduleNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_reminders_are_sent_once_per_recipient_and_event(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(7, 0));
        $borrower = User::factory()->create();
        $staff = User::factory()->create(['role' => 'petugas']);

        $handover = $this->createLoan($borrower, 'PJM-HANDOVER', 'disetujui', '2026-10-16', '2026-10-20');
        $this->createLoan($borrower, 'PJM-DUE', 'dipinjam', '2026-10-10', '2026-10-15');
        $this->createLoan($borrower, 'PJM-LATE', 'dipinjam', '2026-10-01', '2026-10-14');

        $this->artisan('loans:send-schedule-notifications')->assertSuccessful();
        $this->artisan('loans:send-schedule-notifications')->assertSuccessful();

        $this->assertSame(6, AppNotification::count());
        $this->assertSame(3, AppNotification::where('user_id', $borrower->id)->count());
        $this->assertSame(3, AppNotification::where('user_id', $staff->id)->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $borrower->id,
            'notification_key' => "loan:{$handover->id}:handover:2026-10-16",
        ]);
    }

    private function createLoan(User $borrower, string $code, string $status, string $loanDate, string $dueDate): Loan
    {
        return Loan::create([
            'loan_code' => $code,
            'user_id' => $borrower->id,
            'purpose' => 'Uji pengingat jadwal',
            'loan_date' => $loanDate,
            'due_date' => $dueDate,
            'status' => $status,
        ]);
    }
}
