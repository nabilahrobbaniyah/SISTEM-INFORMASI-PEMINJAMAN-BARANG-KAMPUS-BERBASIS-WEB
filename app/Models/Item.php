<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    public const CONDITIONS = ['baik', 'rusak ringan', 'rusak berat'];

    /**
     * total_quantity is serviceable inventory (including checked-out units);
     * available_quantity is the serviceable stock physically on the shelf.
     */
    protected $fillable = [
        'category_id',
        'item_code',
        'name',
        'description',
        'total_quantity',
        'available_quantity',
        'condition',
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

    /** The item's serviceable stock may be loaned while the item is active. */
    public function isBorrowable(): bool
    {
        return $this->is_active && $this->condition !== 'rusak berat';
    }

    /** Units physically on the shelf and ready for handover. */
    public function checkedOutQuantity(): int
    {
        return (int) $this->loanDetails()
            ->whereHas('loan', fn ($loan) => $loan->where('status', 'dipinjam'))
            ->sum('quantity');
    }

    /**
     * Units committed during an overlapping period.
     * Pending requests do not reserve stock; checked-out loans remain reserved
     * until their return has been recorded.
     */
    public function reservedBetween(string $start, string $end, ?int $exceptLoanId = null): int
    {
        return (int) LoanDetail::query()
            ->where('item_id', $this->id)
            ->whereHas('loan', function ($loan) use ($start, $end, $exceptLoanId) {
                $loan->whereIn('status', ['disetujui', 'dipinjam'])
                    ->whereDate('loan_date', '<=', $end)
                    ->where(function ($w) use ($start) {
                        $w->whereDate('due_date', '>=', $start)
                            ->orWhere('status', 'dipinjam');
                    });

                if ($exceptLoanId) {
                    $loan->where('id', '!=', $exceptLoanId);
                }
            })
            ->sum('quantity');
    }

    /** Borrowable serviceable stock after period reservations are deducted. */
    public function availableBetween(string $start, string $end, ?int $exceptLoanId = null): int
    {
        return max(0, $this->total_quantity - $this->reservedBetween($start, $end, $exceptLoanId));
    }
}
