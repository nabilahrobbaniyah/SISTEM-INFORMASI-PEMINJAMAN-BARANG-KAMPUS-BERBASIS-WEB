<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seluruh aturan alur peminjaman ada di sini:
 * pengajuan -> persetujuan/penolakan -> serah terima -> pengembalian.
 * Kesalahan bisnis dilempar sebagai RuntimeException dengan pesan berbahasa Indonesia.
 */
class LoanWorkflow
{
    /**
     * Cek ketersediaan per periode untuk daftar barang.
     *
     * @param  array<int, array{item_id: int|string, quantity: int|string}>  $rows
     * @return array<int, string> pesan kekurangan (kosong bila semua tersedia)
     */
    public function shortages(array $rows, string $start, string $end, ?int $exceptLoanId = null): array
    {
        $items = Item::whereIn('id', collect($rows)->pluck('item_id'))->get()->keyBy('id');
        $messages = [];

        foreach ($rows as $row) {
            $item = $items->get((int) $row['item_id']);

            if (! $item || ! $item->isBorrowable()) {
                $nama = $item?->name ?? 'Barang';
                $messages[] = "{$nama} tidak dapat dipinjam (nonaktif atau rusak berat).";

                continue;
            }

            $qty = (int) $row['quantity'];
            $available = $item->availableBetween($start, $end, $exceptLoanId);

            if ($qty > $available) {
                $messages[] = "{$item->name}: diminta {$qty} unit, tersedia {$available} unit pada periode tersebut.";
            }
        }

        return $messages;
    }

    /**
     * @param  array{loan_date: string, due_date: string, purpose: string, items: array<int, array{item_id: int|string, quantity: int|string}>}  $data
     */
    public function submit(User $user, array $data): Loan
    {
        return DB::transaction(function () use ($user, $data) {
            $loan = Loan::create([
                'loan_code' => Loan::generateCode(),
                'user_id' => $user->id,
                'purpose' => $data['purpose'],
                'loan_date' => $data['loan_date'],
                'due_date' => $data['due_date'],
                'status' => 'menunggu',
            ]);

            foreach ($data['items'] as $row) {
                $loan->details()->create([
                    'item_id' => (int) $row['item_id'],
                    'quantity' => (int) $row['quantity'],
                ]);
            }

            $staffIds = User::whereIn('role', ['admin', 'petugas'])->where('is_active', true)->pluck('id')->all();

            AppNotification::send(
                $staffIds,
                'Pengajuan peminjaman baru',
                "{$user->name} mengajukan peminjaman {$loan->loan_code}.",
                $loan->id
            );

            AuditLog::record('pengajuan', "{$user->name} mengajukan peminjaman {$loan->loan_code}.", $user->id);

            return $loan;
        });
    }

    public function cancel(Loan $loan, User $user): void
    {
        DB::transaction(function () use ($loan, $user) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);

            if ($loan->user_id !== $user->id) {
                throw new RuntimeException('Anda hanya dapat membatalkan pengajuan milik sendiri.');
            }
            if ($loan->status !== 'menunggu') {
                throw new RuntimeException('Hanya pengajuan yang masih menunggu yang dapat dibatalkan.');
            }

            $loan->update(['status' => 'dibatalkan']);

            AuditLog::record('pembatalan', "{$user->name} membatalkan pengajuan {$loan->loan_code}.", $user->id);
        });
    }

    public function approve(Loan $loan, User $staff): void
    {
        DB::transaction(function () use ($loan, $staff) {
            $loan = Loan::with('details')->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'menunggu') {
                throw new RuntimeException('Pengajuan ini sudah diproses.');
            }

            // Kunci baris barang agar dua persetujuan bersamaan tidak melebihi stok.
            Item::whereIn('id', $loan->details->pluck('item_id'))->lockForUpdate()->get();

            $rows = $loan->details->map(fn ($d) => ['item_id' => $d->item_id, 'quantity' => $d->quantity])->all();
            $kurang = $this->shortages(
                $rows,
                $loan->loan_date->toDateString(),
                $loan->due_date->toDateString(),
                $loan->id
            );

            if ($kurang) {
                throw new RuntimeException('Tidak dapat disetujui. '.implode(' ', $kurang));
            }

            $loan->update([
                'status' => 'disetujui',
                'processed_by' => $staff->id,
                'processed_at' => now(),
                'rejection_reason' => null,
            ]);

            AppNotification::send(
                $loan->user_id,
                'Pengajuan disetujui',
                "Pengajuan {$loan->loan_code} disetujui. Ambil barang pada {$loan->loan_date->format('d/m/Y')}.",
                $loan->id
            );

            AuditLog::record('persetujuan', "{$staff->name} menyetujui peminjaman {$loan->loan_code}.", $staff->id);
        });
    }

    public function reject(Loan $loan, User $staff, string $reason): void
    {
        DB::transaction(function () use ($loan, $staff, $reason) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'menunggu') {
                throw new RuntimeException('Pengajuan ini sudah diproses.');
            }

            $loan->update([
                'status' => 'ditolak',
                'processed_by' => $staff->id,
                'processed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AppNotification::send(
                $loan->user_id,
                'Pengajuan ditolak',
                "Pengajuan {$loan->loan_code} ditolak. Alasan: {$reason}",
                $loan->id
            );

            AuditLog::record('penolakan', "{$staff->name} menolak peminjaman {$loan->loan_code}.", $staff->id);
        });
    }

    public function handover(Loan $loan, User $staff): void
    {
        DB::transaction(function () use ($loan, $staff) {
            $loan = Loan::with('details')->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'disetujui') {
                throw new RuntimeException('Serah terima hanya untuk peminjaman yang sudah disetujui.');
            }

            $items = Item::whereIn('id', $loan->details->pluck('item_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($loan->details as $detail) {
                $item = $items->get($detail->item_id);

                if (! $item || $item->available_quantity < $detail->quantity) {
                    $nama = $item?->name ?? 'Barang';
                    throw new RuntimeException("Stok fisik {$nama} di tempat penyimpanan belum cukup untuk diserahkan.");
                }
            }

            foreach ($loan->details as $detail) {
                $items->get($detail->item_id)->decrement('available_quantity', $detail->quantity);
            }

            $loan->update([
                'status' => 'dipinjam',
                'handed_over_by' => $staff->id,
                'handed_over_at' => now(),
            ]);

            AppNotification::send(
                $loan->user_id,
                'Barang diserahkan',
                "Barang untuk {$loan->loan_code} sudah diserahkan. Kembalikan paling lambat {$loan->due_date->format('d/m/Y')}.",
                $loan->id
            );

            AuditLog::record('serah-terima', "{$staff->name} menyerahkan barang {$loan->loan_code}.", $staff->id);
        });
    }

    /**
     * @param  array<int, array{condition: string, note?: string|null}>  $returns  kunci = id loan_details
     */
    public function receiveReturn(Loan $loan, User $staff, array $returns, ?string $notes): void
    {
        DB::transaction(function () use ($loan, $staff, $returns, $notes) {
            $loan = Loan::with('details')->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'dipinjam') {
                throw new RuntimeException('Pengembalian hanya untuk peminjaman yang sedang berjalan.');
            }

            $items = Item::whereIn('id', $loan->details->pluck('item_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($loan->details as $detail) {
                $input = $returns[$detail->id] ?? null;

                if (! $input || empty($input['condition'])) {
                    throw new RuntimeException('Kondisi setiap barang yang dikembalikan wajib diisi.');
                }

                if (! in_array($input['condition'], Item::CONDITIONS, true)) {
                    throw new RuntimeException('Kondisi barang yang dikembalikan tidak valid.');
                }

                $item = $items->get($detail->item_id);

                if (! $item) {
                    throw new RuntimeException('Data barang untuk pengembalian tidak ditemukan.');
                }

                $detail->update([
                    'return_condition' => $input['condition'],
                    'return_note' => $input['note'] ?? null,
                ]);

                if ($input['condition'] === 'baik') {
                    if ($item->available_quantity + $detail->quantity > $item->total_quantity) {
                        throw new RuntimeException("Stok tersedia {$item->name} tidak konsisten dengan jumlah peminjaman.");
                    }

                    $item->increment('available_quantity', $detail->quantity);
                } else {
                    if ($item->total_quantity - $detail->quantity < $item->available_quantity) {
                        throw new RuntimeException("Jumlah stok layak pinjam {$item->name} tidak konsisten dengan pengembalian.");
                    }

                    $item->decrement('total_quantity', $detail->quantity);
                }
            }

            $loan->update([
                'status' => 'dikembalikan',
                'returned_to' => $staff->id,
                'returned_at' => now(),
                'return_notes' => $notes,
            ]);

            AppNotification::send(
                $loan->user_id,
                'Pengembalian dicatat',
                "Pengembalian barang untuk {$loan->loan_code} sudah dicatat. Terima kasih.",
                $loan->id
            );

            AuditLog::record('pengembalian', "{$staff->name} mencatat pengembalian {$loan->loan_code}.", $staff->id);
        });
    }
}
