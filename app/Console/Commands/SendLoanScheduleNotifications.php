<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Console\Command;

class SendLoanScheduleNotifications extends Command
{
    protected $signature = 'loans:send-schedule-notifications';

    protected $description = 'Kirim pengingat serah-terima dan pengembalian peminjaman.';

    public function handle(): int
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $staffIds = User::query()
            ->whereIn('role', ['admin', 'petugas'])
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        Loan::query()
            ->where('status', 'disetujui')
            ->whereDate('loan_date', $tomorrow)
            ->each(function (Loan $loan) use ($staffIds, $tomorrow): void {
                AppNotification::sendOnce(
                    array_merge([$loan->user_id], $staffIds),
                    "loan:{$loan->id}:handover:{$tomorrow}",
                    'Pengingat serah-terima besok',
                    "Serah-terima peminjaman {$loan->loan_code} dijadwalkan besok ({$loan->loan_date->format('d/m/Y')}).",
                    $loan->id
                );
            });

        Loan::query()
            ->where('status', 'dipinjam')
            ->whereDate('due_date', $today)
            ->each(function (Loan $loan) use ($staffIds, $today): void {
                AppNotification::sendOnce(
                    array_merge([$loan->user_id], $staffIds),
                    "loan:{$loan->id}:due:{$today}",
                    'Peminjaman jatuh tempo hari ini',
                    "Peminjaman {$loan->loan_code} harus dikembalikan hari ini ({$loan->due_date->format('d/m/Y')}).",
                    $loan->id
                );
            });

        Loan::query()
            ->where('status', 'dipinjam')
            ->whereDate('due_date', '<', $today)
            ->each(function (Loan $loan) use ($staffIds): void {
                $dueDate = $loan->due_date->toDateString();
                AppNotification::sendOnce(
                    array_merge([$loan->user_id], $staffIds),
                    "loan:{$loan->id}:overdue:{$dueDate}",
                    'Peminjaman terlambat dikembalikan',
                    "Peminjaman {$loan->loan_code} telah melewati jatuh tempo {$loan->due_date->format('d/m/Y')}.",
                    $loan->id
                );
            });

        $this->info('Pengingat jadwal peminjaman telah diproses.');

        return self::SUCCESS;
    }
}
