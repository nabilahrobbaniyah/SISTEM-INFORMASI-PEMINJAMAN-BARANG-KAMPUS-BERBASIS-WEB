<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryEvent extends Model
{
    protected $fillable = [
        'item_id',
        'loan_id',
        'loan_detail_id',
        'user_id',
        'action',
        'quantity',
        'occurred_at',
        'started_at',
        'completed_at',
        'description',
        'evidence_path',
        'result',
        'state_before',
        'state_after',
        'quantity_before',
        'quantity_after',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function loanDetail(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class);
    }
}
