<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\InventoryDisposalProposal;
use App\Models\InventoryEvent;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\User;
use Illuminate\Support\Carbon;
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
    public function submit(User $user, array $data, string $submissionKey): Loan
    {
        return DB::transaction(function () use ($user, $data, $submissionKey) {
            $existing = Loan::where('submission_key', $submissionKey)->first();

            if ($existing) {
                if ($existing->user_id !== $user->id) {
                    throw new RuntimeException('Kunci pengajuan tidak valid.');
                }

                return $existing;
            }

            Item::whereIn('id', collect($data['items'])->pluck('item_id'))->orderBy('id')->lockForUpdate()->get();
            $shortages = $this->shortages($data['items'], $data['loan_date'], $data['due_date']);

            if ($shortages) {
                throw new RuntimeException('Ketersediaan barang berubah. '.implode(' ', $shortages));
            }

            $loan = Loan::create([
                'loan_code' => Loan::generateCode(),
                'submission_key' => $submissionKey,
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
            $loan = Loan::with('user')->lockForUpdate()->findOrFail($loan->id);

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

            AuditLog::record(
                'penolakan',
                "{$staff->name} menolak peminjaman {$loan->loan_code} untuk {$loan->user->name}. Alasan: {$reason}",
                $staff->id
            );
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

                if (! $item || ! $item->hasConsistentServiceableStock() || $item->available_quantity < $detail->quantity) {
                    $nama = $item?->name ?? 'Barang';
                    throw new RuntimeException("Stok fisik {$nama} tidak konsisten atau belum cukup untuk diserahkan.");
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

            foreach ($loan->details as $detail) {
                $item = $items->get($detail->item_id);
                if (! $item->hasConsistentServiceableStock()) {
                    throw new RuntimeException("Jumlah stok {$item->name} tidak konsisten setelah serah terima.");
                }
                $item->inventoryEvents()->create([
                    'loan_id' => $loan->id,
                    'loan_detail_id' => $detail->id,
                    'user_id' => $staff->id,
                    'action' => 'loan_handover',
                    'quantity' => $detail->quantity,
                    'occurred_at' => now(),
                    'description' => "Barang diserahkan untuk peminjaman {$loan->loan_code}.",
                    'state_before' => 'tersedia',
                    'state_after' => 'dipinjam',
                    'quantity_before' => $item->inventoryQuantity(),
                    'quantity_after' => $item->inventoryQuantity(),
                ]);
            }

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
     * @param  array<int, array{good_quantity: int, light_damage_quantity: int, heavy_damage_quantity: int, lost_quantity: int, note?: string|null, photo_path?: string|null}>  $returns
     */
    public function receiveReturn(Loan $loan, User $staff, array $returns, ?string $notes): void
    {
        DB::transaction(function () use ($loan, $staff, $returns, $notes) {
            $loan = Loan::with('details')->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'dipinjam') {
                throw new RuntimeException('Pengembalian hanya untuk peminjaman yang sedang berjalan.');
            }

            $items = Item::whereIn('id', $loan->details->pluck('item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $inspectedAny = false;

            foreach ($loan->details as $detail) {
                $input = $returns[$detail->id] ?? null;

                if (! $input) {
                    continue;
                }

                $good = (int) ($input['good_quantity'] ?? 0);
                $lightDamage = (int) ($input['light_damage_quantity'] ?? 0);
                $heavyDamage = (int) ($input['heavy_damage_quantity'] ?? 0);
                $lost = (int) ($input['lost_quantity'] ?? 0);
                $checked = $good + $lightDamage + $heavyDamage + $lost;

                if (min($good, $lightDamage, $heavyDamage, $lost) < 0
                    || $checked > $detail->outstandingQuantity()) {
                    throw new RuntimeException("Jumlah kondisi {$detail->item->name} tidak boleh melebihi sisa {$detail->outstandingQuantity()} unit yang belum kembali.");
                }

                if ($checked === 0) {
                    continue;
                }

                $inspectedAny = true;
                $item = $items->get($detail->item_id);

                if (! $item) {
                    throw new RuntimeException('Data barang untuk pengembalian tidak ditemukan.');
                }

                $inventoryBefore = $item->inventoryQuantity();
                $item->available_quantity += $good;
                $item->total_quantity -= ($lightDamage + $heavyDamage + $lost);
                $item->light_damage_quantity += $lightDamage;
                $item->heavy_damage_quantity += $heavyDamage;
                $item->lost_quantity += $lost;
                if ($item->available_quantity > $item->total_quantity) {
                    throw new RuntimeException("Stok tersedia {$item->name} tidak konsisten dengan jumlah yang dikembalikan.");
                }
                $item->save();

                $detail->returned_good_quantity += $good;
                $detail->returned_light_damage_quantity += $lightDamage;
                $detail->returned_heavy_damage_quantity += $heavyDamage;
                $detail->returned_lost_quantity += $lost;
                $detail->update([
                    'return_condition' => $this->returnConditionLabel($good, $lightDamage, $heavyDamage, $lost),
                    'return_note' => isset($input['note']) ? mb_substr((string) $input['note'], 0, 255) : null,
                    'return_inspected_by' => $staff->id,
                    'return_inspected_at' => now(),
                    'return_photo' => $input['photo_path'] ?? $detail->return_photo,
                ]);
                if (! $item->fresh()->hasConsistentServiceableStock()) {
                    throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten setelah pengembalian.");
                }

                foreach ([
                    'returned_good' => [$good, 'tersedia', 'Barang kembali dalam kondisi baik.'],
                    'light_damage_reported' => [$lightDamage, 'rusak ringan', 'Rusak ringan. menunggu penjadwalan perbaikan.'],
                    'heavy_damage_reported' => [$heavyDamage, 'rusak berat', 'Rusak berat. tidak dapat dipinjam.'],
                    'lost_reported' => [$lost, 'hilang', (string) ($input['note'] ?? 'Kehilangan dicatat saat pengembalian.')],
                ] as $action => [$quantity, $targetState, $description]) {
                    if ($quantity > 0) {
                        InventoryEvent::create([
                            'item_id' => $item->id,
                            'loan_id' => $loan->id,
                            'loan_detail_id' => $detail->id,
                            'user_id' => $staff->id,
                            'action' => $action,
                            'quantity' => $quantity,
                            'occurred_at' => now(),
                            'description' => mb_substr($description, 0, 2000),
                            'evidence_path' => $input['photo_path'] ?? null,
                            'state_before' => 'dipinjam',
                            'state_after' => $targetState,
                            'quantity_before' => $inventoryBefore,
                            'quantity_after' => $item->inventoryQuantity(),
                        ]);
                    }
                }
            }

            if (! $inspectedAny) {
                throw new RuntimeException('Masukkan jumlah barang yang benar-benar diterima untuk diperiksa.');
            }

            $complete = $loan->details->every(fn (LoanDetail $detail) => $detail->returnedQuantity() >= $detail->quantity);
            $loan->update([
                'status' => $complete ? 'dikembalikan' : 'dipinjam',
                'returned_to' => $staff->id,
                'returned_at' => $complete ? now() : null,
                'return_notes' => $notes,
            ]);

            AppNotification::send(
                $loan->user_id,
                'Pengembalian dicatat',
                $complete
                    ? "Semua barang untuk {$loan->loan_code} sudah dikembalikan."
                    : "Sebagian barang untuk {$loan->loan_code} sudah diperiksa. Sisa barang masih tercatat belum kembali.",
                $loan->id
            );

            AuditLog::record(
                $complete ? 'pengembalian' : 'pengembalian-sebagian',
                "{$staff->name} mencatat pengembalian untuk {$loan->loan_code}.",
                $staff->id
            );
        });
    }

    public function reportFound(Item $item, User $staff, int $quantity, string $description, ?string $evidencePath, Carbon $occurredAt): void
    {
        DB::transaction(function () use ($item, $staff, $quantity, $description, $evidencePath, $occurredAt): void {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if ($quantity < 1 || $quantity > $item->lost_quantity) {
                throw new RuntimeException('Jumlah barang ditemukan melebihi jumlah barang yang tercatat hilang.');
            }

            $inventoryBefore = $item->inventoryQuantity();
            $item->lost_quantity -= $quantity;
            $item->inspection_quantity += $quantity;
            $item->save();
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten setelah perubahan.");
            }
            $this->recordInventoryEvent(
                $item,
                $staff,
                'found_waiting_inspection',
                $quantity,
                $description,
                $evidencePath,
                $occurredAt,
                'pemeriksaan',
                null,
                'hilang',
                'pemeriksaan',
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function reportDamage(
        Item $item,
        User $staff,
        int $quantity,
        string $condition,
        string $description,
        ?string $evidencePath,
        Carbon $occurredAt
    ): void {
        if (! in_array($condition, ['rusak ringan', 'rusak berat'], true)) {
            throw new RuntimeException('Pilih kondisi rusak ringan atau rusak berat.');
        }

        DB::transaction(function () use ($item, $staff, $quantity, $condition, $description, $evidencePath, $occurredAt): void {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException('Jumlah stok layak tidak konsisten; lakukan audit sebelum melaporkan kerusakan.');
            }
            $futureReservations = $item->maximumReservedFrom(now()->toDateString());

            if ($quantity < 1 || $quantity > $item->available_quantity) {
                throw new RuntimeException('Jumlah kerusakan melebihi stok yang tersedia di rak.');
            }
            if ($item->total_quantity - $quantity < $futureReservations) {
                throw new RuntimeException('Pelaporan kerusakan akan melanggar reservasi peminjaman.');
            }

            $inventoryBefore = $item->inventoryQuantity();
            $item->total_quantity -= $quantity;
            $item->available_quantity -= $quantity;
            $column = $condition === 'rusak ringan' ? 'light_damage_quantity' : 'heavy_damage_quantity';
            $item->{$column} += $quantity;
            $item->save();

            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten setelah pelaporan kerusakan.");
            }

            $this->recordInventoryEvent(
                $item,
                $staff,
                'damage_reported',
                $quantity,
                $description,
                $evidencePath,
                $occurredAt,
                $condition,
                null,
                'tersedia',
                $condition,
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function inspectFound(Item $item, User $staff, int $quantity, string $condition, string $description, ?string $evidencePath, Carbon $occurredAt): void
    {
        DB::transaction(function () use ($item, $staff, $quantity, $condition, $description, $evidencePath, $occurredAt): void {
            if (! in_array($condition, Item::INSPECTION_CONDITIONS, true)) {
                throw new RuntimeException('Kondisi hasil pemeriksaan tidak valid.');
            }

            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if ($quantity < 1 || $quantity > $item->inspection_quantity) {
                throw new RuntimeException('Jumlah pemeriksaan melebihi jumlah barang yang menunggu pemeriksaan.');
            }

            $inventoryBefore = $item->inventoryQuantity();
            $item->inspection_quantity -= $quantity;
            if ($condition === 'baik') {
                $item->total_quantity += $quantity;
                $item->available_quantity += $quantity;
            } elseif ($condition === 'rusak ringan') {
                $item->light_damage_quantity += $quantity;
            } else {
                $item->heavy_damage_quantity += $quantity;
            }
            $item->save();
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten setelah pemeriksaan.");
            }
            $this->recordInventoryEvent(
                $item,
                $staff,
                'found_inspected',
                $quantity,
                $description,
                $evidencePath,
                $occurredAt,
                $condition,
                null,
                'pemeriksaan',
                $condition === 'baik' ? 'tersedia' : $condition,
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function startRepair(
        Item $item,
        User $staff,
        int $quantity,
        string $description,
        ?string $evidencePath,
        Carbon $startedAt,
        string $sourceCondition = 'rusak ringan'
    ): void
    {
        if (! in_array($sourceCondition, ['rusak ringan', 'rusak berat'], true)) {
            throw new RuntimeException('Pilih kondisi rusak ringan atau rusak berat.');
        }

        DB::transaction(function () use ($item, $staff, $quantity, $description, $evidencePath, $startedAt, $sourceCondition): void {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            $sourceColumn = $sourceCondition === 'rusak ringan' ? 'light_damage_quantity' : 'heavy_damage_quantity';
            $availableForAction = $item->{$sourceColumn};
            if ($sourceCondition === 'rusak berat') {
                $availableForAction -= $this->pendingDisposalQuantity($item);
            }
            if ($quantity < 1 || $quantity > $availableForAction) {
                throw new RuntimeException('Jumlah perbaikan melebihi barang yang tersedia pada kondisi tersebut.');
            }

            $inventoryBefore = $item->inventoryQuantity();
            $item->{$sourceColumn} -= $quantity;
            $item->repair_quantity += $quantity;
            $item->save();
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten saat memulai perbaikan.");
            }

            $this->recordInventoryEvent(
                $item,
                $staff,
                'repair_started',
                $quantity,
                $description,
                $evidencePath,
                $startedAt,
                'perbaikan',
                $startedAt,
                $sourceCondition,
                'perbaikan',
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function returnHeavyDamageToShelf(Item $item, User $staff, int $quantity, string $description, Carbon $occurredAt): void
    {
        DB::transaction(function () use ($item, $staff, $quantity, $description, $occurredAt): void {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            $availableForAction = $item->heavy_damage_quantity - $this->pendingDisposalQuantity($item);
            if ($quantity < 1 || $quantity > $availableForAction) {
                throw new RuntimeException('Jumlah melebihi barang rusak berat yang belum diusulkan untuk dihapus.');
            }

            $inventoryBefore = $item->inventoryQuantity();
            $item->heavy_damage_quantity -= $quantity;
            $item->total_quantity += $quantity;
            $item->available_quantity += $quantity;
            $item->save();
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten setelah barang dikembalikan ke rak.");
            }

            $this->recordInventoryEvent(
                $item,
                $staff,
                'heavy_damage_returned_to_shelf',
                $quantity,
                $description,
                null,
                $occurredAt,
                'baik',
                null,
                'rusak berat',
                'tersedia',
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function proposeHeavyDamageDisposal(
        Item $item,
        User $staff,
        int $quantity,
        string $reason,
        Carbon $proposedAt
    ): void {
        DB::transaction(function () use ($item, $staff, $quantity, $reason, $proposedAt): void {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            $availableForProposal = $item->heavy_damage_quantity - $this->pendingDisposalQuantity($item);
            if ($quantity < 1 || $quantity > $availableForProposal) {
                throw new RuntimeException('Jumlah usulan melebihi barang rusak berat yang belum diusulkan untuk dihapus.');
            }

            $proposal = $item->disposalProposals()->create([
                'proposed_by' => $staff->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'status' => 'pending',
            ]);
            $this->recordInventoryEvent(
                $item,
                $staff,
                'heavy_damage_disposal_proposed',
                $quantity,
                "{$reason}",
                null,
                $proposedAt,
                'menunggu_persetujuan',
                null,
                'rusak berat',
                'usulan penghapusan',
                $item->inventoryQuantity(),
                $item->inventoryQuantity()
            );
        });
    }

    public function reviewHeavyDamageDisposal(
        InventoryDisposalProposal $proposal,
        User $admin,
        bool $approve,
        string $reviewNote
    ): void {
        DB::transaction(function () use ($proposal, $admin, $approve, $reviewNote): void {
            $proposal = InventoryDisposalProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($proposal->status !== 'pending') {
                throw new RuntimeException('Usulan ini sudah pernah ditinjau.');
            }

            $item = Item::query()->lockForUpdate()->findOrFail($proposal->item_id);
            if ($approve && $proposal->quantity > $item->heavy_damage_quantity) {
                throw new RuntimeException('Jumlah rusak berat saat ini tidak mencukupi untuk menyetujui usulan.');
            }
            $inventoryBefore = $item->inventoryQuantity();

            if ($approve) {
                $item->heavy_damage_quantity -= $proposal->quantity;
                $item->disposed_quantity += $proposal->quantity;
                $item->save();
                $eventAction = 'stock_disposed';
                $eventResult = 'disetujui';
                $stateAfter = 'dihapuskan';
                $description = trim("{$proposal->reason} {$reviewNote}");
            } else {
                $eventAction = 'heavy_damage_disposal_rejected';
                $eventResult = 'ditolak';
                $stateAfter = 'rusak berat';
                $description = trim("{$proposal->reason} {$reviewNote}");
            }

            $proposal->update([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            $this->recordInventoryEvent(
                $item,
                $admin,
                $eventAction,
                $proposal->quantity,
                $description,
                null,
                now(),
                $eventResult,
                null,
                'rusak berat',
                $stateAfter,
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function completeRepair(Item $item, User $staff, int $quantity, string $result, string $actionNotes, ?string $evidencePath, Carbon $completedAt): void
    {
        DB::transaction(function () use ($item, $staff, $quantity, $result, $actionNotes, $evidencePath, $completedAt): void {
            if (! in_array($result, ['layak', 'perlu_perbaikan'], true)) {
                throw new RuntimeException('Hasil pemeriksaan perbaikan tidak valid.');
            }

            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if ($quantity < 1 || $quantity > $item->repair_quantity) {
                throw new RuntimeException('Jumlah perbaikan melebihi barang yang sedang diperbaiki.');
            }
            $repairStartedAt = InventoryEvent::query()
                ->where('item_id', $item->id)
                ->where('action', 'repair_started')
                ->orderByDesc('occurred_at')
                ->value('occurred_at');

            if (! $repairStartedAt) {
                throw new RuntimeException('Tanggal mulai perbaikan tidak ditemukan dalam riwayat inventaris.');
            }

            $inventoryBefore = $item->inventoryQuantity();
            if ($result === 'layak') {
                $item->repair_quantity -= $quantity;
                $item->total_quantity += $quantity;
                $item->available_quantity += $quantity;
                $action = 'repair_completed';
            } else {
                $action = 'repair_inspection_failed';
            }

            $item->save();
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException("Jumlah stok layak {$item->name} tidak konsisten setelah perbaikan.");
            }
            $this->recordInventoryEvent(
                $item,
                $staff,
                $action,
                $quantity,
                $actionNotes,
                $evidencePath,
                $completedAt,
                $result,
                Carbon::parse($repairStartedAt),
                'perbaikan',
                $result === 'layak' ? 'tersedia' : 'perbaikan',
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    public function adjustInventory(
        Item $item,
        User $admin,
        string $action,
        int $quantity,
        string $reason,
        ?string $sourceState = null
    ): void {
        if ($quantity < ($action === 'koreksi' ? 0 : 1)) {
            throw new RuntimeException('Jumlah transaksi inventaris tidak valid.');
        }

        DB::transaction(function () use ($item, $admin, $action, $quantity, $reason, $sourceState): void {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if ($action !== 'koreksi' && ! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException('Jumlah stok layak tidak konsisten; lakukan audit sebelum mengubah inventaris.');
            }
            $inventoryBefore = $item->inventoryQuantity();
            $serviceableBefore = $item->total_quantity;
            $availableBefore = $item->available_quantity;
            $eventQuantity = abs($quantity);
            $eventDescription = $reason;

            if ($action === 'penambahan') {
                $item->total_quantity += $quantity;
                $item->available_quantity += $quantity;
                $stateBefore = 'tersedia';
                $stateAfter = 'tersedia';
            } elseif ($action === 'koreksi') {
                $futureReservations = $item->maximumReservedFrom(now()->toDateString());
                $checkedOut = $item->checkedOutQuantity();

                if ($quantity < max($checkedOut, $futureReservations)) {
                    throw new RuntimeException('Jumlah koreksi tidak dapat lebih kecil dari barang yang sedang dipinjam atau sudah dipesan.');
                }

                $item->total_quantity = $quantity;
                $item->available_quantity = $quantity - $checkedOut;
                $eventQuantity = abs($quantity - $serviceableBefore);
                $stateBefore = 'tersedia';
                $stateAfter = 'tersedia';
                $eventDescription = trim(implode(' ', array_filter([
                    trim($reason),
                    "Saldo layak pinjam {$serviceableBefore} -> {$quantity}; stok di rak {$availableBefore} -> {$item->available_quantity}.",
                ])));
            } elseif ($action === 'pengurangan') {
                $futureReservations = $item->maximumReservedFrom(now()->toDateString());

                if ($quantity > $item->available_quantity
                    || $item->total_quantity - $quantity < $futureReservations) {
                    throw new RuntimeException('Pengurangan melebihi stok tersedia atau akan melanggar reservasi peminjaman.');
                }

                $item->total_quantity -= $quantity;
                $item->available_quantity -= $quantity;
                $stateBefore = 'tersedia';
                $stateAfter = 'tersedia';
            } elseif ($action === 'penghapusan') {
                $columns = [
                    'tersedia' => 'available_quantity',
                    'rusak ringan' => 'light_damage_quantity',
                    'pemeriksaan' => 'inspection_quantity',
                    'perbaikan' => 'repair_quantity',
                    'rusak berat' => 'heavy_damage_quantity',
                    'hilang' => 'lost_quantity',
                ];
                $column = $columns[$sourceState ?? ''] ?? null;

                if (! $column || $quantity > $item->{$column}) {
                    throw new RuntimeException('Jumlah penghapusan melebihi barang pada status yang dipilih.');
                }
                if ($sourceState === 'rusak berat'
                    && $quantity > $item->heavy_damage_quantity - $this->pendingDisposalQuantity($item)) {
                    throw new RuntimeException('Sebagian barang rusak berat telah dicadangkan oleh usulan penghapusan yang menunggu persetujuan.');
                }
                if ($sourceState === 'tersedia') {
                    $futureReservations = $item->maximumReservedFrom(now()->toDateString());
                    if ($item->total_quantity - $quantity < $futureReservations) {
                        throw new RuntimeException('Penghapusan akan melanggar reservasi peminjaman.');
                    }

                    $item->total_quantity -= $quantity;
                }

                $item->{$column} -= $quantity;
                $item->disposed_quantity += $quantity;
                $stateBefore = $sourceState;
                $stateAfter = 'dihapuskan';
            } else {
                throw new RuntimeException('Jenis perubahan inventaris tidak valid.');
            }

            $item->save();
            if (! $item->hasConsistentServiceableStock()) {
                throw new RuntimeException('Jumlah stok layak tidak konsisten setelah transaksi inventaris.');
            }

            $this->recordInventoryEvent(
                $item,
                $admin,
                match ($action) {
                    'penambahan' => 'stock_added',
                    'pengurangan' => 'stock_removed',
                    'koreksi' => 'stock_corrected',
                    'penghapusan' => 'stock_disposed',
                },
                $eventQuantity,
                $eventDescription,
                null,
                now(),
                $action,
                null,
                $stateBefore,
                $stateAfter,
                $inventoryBefore,
                $item->inventoryQuantity()
            );
        });
    }

    private function recordInventoryEvent(
        Item $item,
        User $staff,
        string $action,
        int $quantity,
        string $description,
        ?string $evidencePath,
        Carbon $occurredAt,
        ?string $result,
        ?Carbon $startedAt = null,
        ?string $stateBefore = null,
        ?string $stateAfter = null,
        ?int $quantityBefore = null,
        ?int $quantityAfter = null
    ): void {
        InventoryEvent::create([
            'item_id' => $item->id,
            'user_id' => $staff->id,
            'action' => $action,
            'quantity' => $quantity,
            'occurred_at' => $occurredAt,
            'started_at' => $startedAt ?? ($action === 'repair_started' ? $occurredAt : null),
            'completed_at' => in_array($action, ['repair_completed', 'repair_inspection_failed'], true) ? $occurredAt : null,
            'description' => $description,
            'evidence_path' => $evidencePath,
            'result' => $result,
            'state_before' => $stateBefore,
            'state_after' => $stateAfter,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
        ]);
        AuditLog::record('inventaris', "{$staff->name}: {$action} {$quantity} unit {$item->item_code}. {$description}", $staff->id);
    }

    private function pendingDisposalQuantity(Item $item): int
    {
        return (int) $item->disposalProposals()
            ->where('status', 'pending')
            ->sum('quantity');
    }

    private function returnConditionLabel(int $good, int $light, int $heavy, int $lost): string
    {
        $conditions = collect([
            'baik' => $good,
            'rusak ringan' => $light,
            'rusak berat' => $heavy,
            'hilang' => $lost,
        ])->filter(fn (int $quantity) => $quantity > 0)->keys();

        return $conditions->count() === 1 ? $conditions->first() : 'campuran';
    }
}
