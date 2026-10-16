<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Loan extends Model
{
    /** Lama peminjaman maksimal (hari). Ubah angka ini bila aturan kampus berbeda. */
    public const MAX_DAYS = 14;

    public const STATUS_LABELS = [
        'menunggu' => 'Menunggu persetujuan',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'dipinjam' => 'Sedang dipinjam',
        'dikembalikan' => 'Dikembalikan',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const STATUS_COLORS = [
        'menunggu' => 'warning',
        'disetujui' => 'info',
        'ditolak' => 'danger',
        'dipinjam' => 'primary',
        'dikembalikan' => 'success',
        'dibatalkan' => 'secondary',
    ];

    protected $fillable = [
        'loan_code',
        'user_id',
        'purpose',
        'loan_date',
        'due_date',
        'status',
        'rejection_reason',
        'processed_by',
        'processed_at',
        'handed_over_by',
        'handed_over_at',
        'returned_to',
        'returned_at',
        'return_notes',
        'submission_key',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'date',
            'due_date' => 'date',
            'processed_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        return 'PJM-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    public function details(): HasMany
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'dipinjam' && $this->due_date->lt(now()->startOfDay());
    }
}
