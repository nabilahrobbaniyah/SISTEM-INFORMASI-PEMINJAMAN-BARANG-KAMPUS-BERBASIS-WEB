<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'target_user_id',
        'action',
        'description',
        'old_value',
        'new_value',
        'reason',
        'result',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(string $action, string $description, ?int $userId = null): void
    {
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
        ]);
    }

    public static function recordRoleChange(
        int $actorId,
        int $targetId,
        string $oldRole,
        string $newRole,
        string $reason,
        string $result
    ): void {
        static::create([
            'user_id' => $actorId,
            'target_user_id' => $targetId,
            'action' => 'perubahan-role',
            'description' => "Role target #{$targetId}: {$oldRole} -> {$newRole} ({$result}).",
            'old_value' => $oldRole,
            'new_value' => $newRole,
            'reason' => mb_substr($reason, 0, 2000),
            'result' => $result,
        ]);
    }
}
