<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Item extends Model
{
    public const INSPECTION_CONDITIONS = ['baik', 'rusak ringan', 'rusak berat'];

    protected $fillable = [
        'category_id',
        'item_code',
        'name',
        'description',
        'total_quantity',
        'available_quantity',
        'lost_quantity',
        'inspection_quantity',
        'light_damage_quantity',
        'repair_quantity',
        'heavy_damage_quantity',
        'disposed_quantity',
        'location',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'total_quantity' => 'integer',
            'available_quantity' => 'integer',
            'lost_quantity' => 'integer',
            'inspection_quantity' => 'integer',
            'light_damage_quantity' => 'integer',
            'repair_quantity' => 'integer',
            'heavy_damage_quantity' => 'integer',
            'disposed_quantity' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function loanDetails(): HasMany
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function inventoryEvents(): HasMany
    {
        return $this->hasMany(InventoryEvent::class);
    }

    public function disposalProposals(): HasMany
    {
        return $this->hasMany(InventoryDisposalProposal::class);
    }

    /** The item's serviceable stock may be loaned while the item is active. */
    public function isBorrowable(): bool
    {
        return $this->is_active && $this->total_quantity > 0;
    }

    public function inventoryQuantity(): int
    {
        // The serviceable total already includes units that are currently on loan.
        return $this->total_quantity
            + $this->lost_quantity
            + $this->inspection_quantity
            + $this->light_damage_quantity
            + $this->repair_quantity
            + $this->heavy_damage_quantity
            + $this->disposed_quantity;
    }

    public function hasConsistentServiceableStock(): bool
    {
        return $this->available_quantity + $this->checkedOutQuantity() === $this->total_quantity;
    }

    public function conditionQuantities(): array
    {
        return [
            'tersedia' => $this->available_quantity,
            'dipinjam' => $this->checkedOutQuantity(),
            'rusak ringan' => $this->light_damage_quantity,
            'dalam perbaikan' => $this->repair_quantity,
            'rusak berat' => $this->heavy_damage_quantity,
            'hilang' => $this->lost_quantity,
            'menunggu pemeriksaan' => $this->inspection_quantity,
            'dihapuskan' => $this->disposed_quantity,
        ];
    }

    public function checkedOutQuantity(): int
    {
        return (int) $this->loanDetails()
            ->whereHas('loan', fn ($loan) => $loan->where('status', 'dipinjam'))
            ->sum(DB::raw('quantity - returned_good_quantity - returned_light_damage_quantity - returned_heavy_damage_quantity - returned_lost_quantity'));
    }

    /**
     * Unit yang dialokasikan selama periode yang tumpang tindih.
     * Permintaan yang tertunda tidak mencadangkan stok; pinjaman yang telah keluar tetap berstatus dicadangkan
     * hingga pengembaliannya tercatat.
     */
    public function reservedBetween(string $start, string $end, ?int $exceptLoanId = null): int
    {
        $query = LoanDetail::query()
            ->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->where('loan_details.item_id', $this->id)
            ->whereIn('loans.status', ['disetujui', 'dipinjam'])
            ->whereDate('loans.loan_date', '<=', $end)
            ->where(function ($w) use ($start) {
                $w->whereDate('loans.due_date', '>=', $start)
                    ->orWhere('loans.status', 'dipinjam');
            });

        if ($exceptLoanId) {
            $query->where('loans.id', '!=', $exceptLoanId);
        }

        return (int) $query->sum(DB::raw(
            "CASE WHEN loans.status = 'dipinjam' THEN CASE WHEN loan_details.quantity - loan_details.returned_good_quantity - loan_details.returned_light_damage_quantity - loan_details.returned_heavy_damage_quantity - loan_details.returned_lost_quantity > 0 THEN loan_details.quantity - loan_details.returned_good_quantity - loan_details.returned_light_damage_quantity - loan_details.returned_heavy_damage_quantity - loan_details.returned_lost_quantity ELSE 0 END ELSE loan_details.quantity END"
        ));
    }

    /** Borrowable serviceable stock after period reservations are deducted. */
    public function availableBetween(string $start, string $end, ?int $exceptLoanId = null): int
    {
        return max(0, $this->total_quantity - $this->reservedBetween($start, $end, $exceptLoanId));
    }

    /**
     * Maximum confirmed reservations that overlap on any date from the given date onward.
     * Active loans remain reserved until their return is recorded.
     */
    public function maximumReservedFrom(string $start): int
    {
        $reservations = DB::table('loan_details')
            ->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->where('loan_details.item_id', $this->id)
            ->whereIn('loans.status', ['disetujui', 'dipinjam'])
            ->where(function ($query) use ($start) {
                $query->where('loans.status', 'dipinjam')
                    ->orWhereDate('loans.due_date', '>=', $start);
            })
            ->get([
                'loans.status',
                'loans.loan_date',
                'loans.due_date',
                'loan_details.quantity',
                'loan_details.returned_good_quantity',
                'loan_details.returned_light_damage_quantity',
                'loan_details.returned_heavy_damage_quantity',
                'loan_details.returned_lost_quantity',
            ]);

        $activeLoans = 0;
        $reservationChanges = [];

        foreach ($reservations as $reservation) {
            $quantity = (int) $reservation->quantity;
            if ($reservation->status === 'dipinjam') {
                $returned = (int) $reservation->returned_good_quantity
                    + (int) $reservation->returned_light_damage_quantity
                    + (int) $reservation->returned_heavy_damage_quantity
                    + (int) $reservation->returned_lost_quantity;
                $activeLoans += max(0, $quantity - $returned);

                continue;
            }

            $reservationStart = max($start, Carbon::parse($reservation->loan_date)->toDateString());
            $reservationEnd = Carbon::parse($reservation->due_date)->addDay()->toDateString();
            $reservationChanges[$reservationStart] = ($reservationChanges[$reservationStart] ?? 0) + $quantity;
            $reservationChanges[$reservationEnd] = ($reservationChanges[$reservationEnd] ?? 0) - $quantity;
        }

        ksort($reservationChanges);
        $reserved = $activeLoans;
        $maximumReserved = $activeLoans;
        foreach ($reservationChanges as $change) {
            $reserved += $change;
            $maximumReserved = max($maximumReserved, $reserved);
        }

        return $maximumReserved;
    }
}
