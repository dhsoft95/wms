<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LabelTemplateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabelTemplate extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'type',
        'width_mm',
        'height_mm',
        'dpi',
        'elements',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type'       => LabelTemplateType::class,
            'elements'   => 'array',
            'is_default' => 'bool',
            'is_active'  => 'bool',
        ];
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeDefaultForType($query, LabelTemplateType $type): mixed
    {
        return $query->where('type', $type->value)->where('is_default', true);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(LabelPrintJob::class);
    }

    public static function availableFields(LabelTemplateType $type): array
    {
        return match($type) {
            LabelTemplateType::PalletLpn => [
                'lpn_code', 'grn_number', 'warehouse_name', 'item_sku', 'item_name',
                'batch_no', 'expiry_date', 'qty', 'uom_code', 'vw_name', 'receive_date', 'location_code',
            ],
            LabelTemplateType::Item => [
                'item_sku', 'item_name', 'barcode', 'batch_no', 'expiry_date', 'qty', 'uom_code',
            ],
            LabelTemplateType::Location => [
                'location_code', 'location_type', 'warehouse_name', 'barcode',
            ],
            LabelTemplateType::Container => [
                'container_no', 'iso_number', 'warehouse_name', 'status',
            ],
            LabelTemplateType::Dispatch => [
                'do_number', 'partner_name', 'warehouse_name', 'item_sku', 'qty',
            ],
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
