<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'loan_id',
        'title',
        'message',
        'notification_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Kirim notifikasi dalam aplikasi ke satu atau banyak pengguna.
     *
     * @param  int|array<int>  $userIds
     */
    public static function send(int|array $userIds, string $title, string $message, ?int $loanId = null): void
    {
        foreach ((array) $userIds as $userId) {
            static::create([
                'user_id' => $userId,
                'loan_id' => $loanId,
                'title' => $title,
                'message' => $message,
            ]);
        }
    }

    /**
     * Deliver the same scheduled event at most once to each recipient.
     *
     * @param  int|array<int>  $userIds
     */
    public static function sendOnce(
        int|array $userIds,
        string $notificationKey,
        string $title,
        string $message,
        ?int $loanId = null
    ): void {
        foreach (array_unique((array) $userIds) as $userId) {
            static::firstOrCreate(
                ['user_id' => $userId, 'notification_key' => $notificationKey],
                [
                    'loan_id' => $loanId,
                    'title' => $title,
                    'message' => $message,
                ]
            );
        }
    }
}
