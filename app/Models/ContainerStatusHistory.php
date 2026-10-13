<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContainerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerStatusHistory extends Model
{
    protected $table = 'container_status_history';

    public $timestamps = false;

    protected $fillable = [
        'container_id',
        'from_status',
        'to_status',
        'user_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ContainerStatus::class,
            'to_status'   => ContainerStatus::class,
            'created_at'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
