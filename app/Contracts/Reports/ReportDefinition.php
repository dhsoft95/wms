<?php

declare(strict_types=1);

namespace App\Contracts\Reports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface ReportDefinition
{
    public function key(): string;

    public function label(): string;

    public function group(): string;

    /** @return string 'range'|'as_of' */
    public function periodType(): string;

    /**
     * Column definitions.
     * Each entry: ['key' => string, 'label' => string, 'align' => 'left'|'center'|'right', 'format' => 'text'|'number'|'date'|'enum']
     *
     * @return array<int, array{key: string, label: string, align: string, format: string}>
     */
    public function columns(): array;

    public function query(array $filters, User $user): Builder;

    public function pdfEnabled(): bool;

    /**
     * KPI summary boxes for PDF header.
     * @return array<string, scalar> label => formatted value
     */
    public function summary(\Illuminate\Support\Collection $rows): array;

    /**
     * Column totals for the totals row (Excel + PDF).
     * @return array<string, scalar>|null column key => total, null = no totals row
     */
    public function totals(\Illuminate\Support\Collection $rows): ?array;
}
