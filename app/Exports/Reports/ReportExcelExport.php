<?php

declare(strict_types=1);

namespace App\Exports\Reports;

use App\Contracts\Reports\ReportDefinition;
use App\Models\User;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExcelExport
{
    public function stream(ReportDefinition $definition, array $filters, User $user): StreamedResponse
    {
        $headers = array_column($definition->columns(), 'label');

        return response()->streamDownload(function () use ($definition, $filters, $user, $headers) {
            $writer = SimpleExcelWriter::streamDownload($definition->label() . '.xlsx');

            $writer->addHeader($headers);

            $keys = array_column($definition->columns(), 'key');

            $definition->query($filters, $user)
                ->lazy(200)
                ->each(function ($row) use ($writer, $keys) {
                    $cells = [];
                    foreach ($keys as $key) {
                        $val = data_get($row, $key);
                        if ($val instanceof \BackedEnum) {
                            $val = method_exists($val, 'label') ? $val->label() : $val->value;
                        } elseif ($val instanceof \Carbon\Carbon) {
                            $val = $val->format('d/m/Y');
                        }
                        $cells[] = $val ?? '';
                    }
                    $writer->addRow($cells);
                });

            $writer->close();
        }, $definition->label() . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
