<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrintOutput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabelPrintJob extends Model
{
    use LogsActivity;

    protected $table = 'label_print_jobs';

    public $timestamps = false;

    protected $fillable = [
        'label_template_id',
        'type',
        'printable_type',
        'printable_id',
        'copies',
        'printer_id',
        'output',
        'is_reprint',
        'reprint_reason_code_id',
        'reprint_note',
        'printed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'output'     => PrintOutput::class,
            'is_reprint' => 'bool',
            'copies'     => 'int',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(fn ($m) => $m->created_at = now());
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }

    public function labelTemplate(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reprint_reason_code_id');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
