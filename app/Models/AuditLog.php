<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip', 'created_at',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * يسجّل إجراء حساس. لو التسجيل فشل ما بيوقّفش العملية الأساسية.
     */
    public static function record(string $action, ?Model $subject = null, array $meta = []): void
    {
        try {
            static::create([
                'user_id'      => auth()->id(),
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'meta'         => $meta ?: null,
                'ip'           => request()?->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
