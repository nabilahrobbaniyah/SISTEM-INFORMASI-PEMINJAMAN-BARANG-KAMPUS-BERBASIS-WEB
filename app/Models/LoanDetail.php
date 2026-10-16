<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDetail extends Model
{
    protected $fillable = [
        'loan_id',
        'item_id',
        'quantity',
        'return_condition',
        'return_note',
        'returned_good_quantity',
        'returned_light_damage_quantity',
        'returned_heavy_damage_quantity',
        'returned_lost_quantity',
        'return_inspected_by',
        'return_inspected_at',
        'return_photo',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_good_quantity' => 'integer',
            'returned_light_damage_quantity' => 'integer',
            'returned_heavy_damage_quantity' => 'integer',
            'returned_lost_quantity' => 'integer',
            'return_inspected_at' => 'datetime',
        ];
    }

    public function returnQuantity(string $condition): int
    {
        return match ($condition) {
            'baik' => $this->returned_good_quantity ?? 0,
            'rusak ringan' => $this->returned_light_damage_quantity ?? 0,
            'rusak berat' => $this->returned_heavy_damage_quantity ?? 0,
            'hilang' => $this->returned_lost_quantity ?? 0,
            default => 0,
        };
    }

    public function returnedQuantity(): int
    {
        return $this->returnQuantity('baik')
            + $this->returnQuantity('rusak ringan')
            + $this->returnQuantity('rusak berat')
            + $this->returnQuantity('hilang');
    }

    public function outstandingQuantity(): int
    {
        return $this->quantity - $this->returnedQuantity();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function returnInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_inspected_by');
    }
}
