<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Department;
use App\Models\VirtualWarehouse;
use Illuminate\Support\Facades\DB;

class DepartmentVwImport extends BaseImport
{
    public function headers(): array
    {
        return ['department_code', 'department_name', 'report_email', 'vw_code', 'vw_name'];
    }

    public function templateRows(): array
    {
        return [
            ['department_code' => 'IT', 'department_name' => 'IT Department', 'report_email' => 'it@example.com', 'vw_code' => 'IT-VW', 'vw_name' => 'IT Virtual Warehouse'],
        ];
    }

    public function columnInstructions(): array
    {
        return [
            'department_code' => 'Required. Unique code for the department.',
            'department_name' => 'Required. Display name.',
            'report_email'    => 'Optional. Email for stock reports.',
            'vw_code'         => 'Required. Unique code for the virtual warehouse.',
            'vw_name'         => 'Required. Display name for the virtual warehouse.',
        ];
    }

    protected function validateRow(int $rowNumber, array $row): array
    {
        $errors = [];
        foreach (['department_code', 'department_name', 'vw_code', 'vw_name'] as $col) {
            if ($this->str($row, $col) === '') {
                $errors[] = $this->err($col, "'{$col}' is required.");
            }
        }
        return $errors;
    }

    protected function importRow(array $row): string
    {
        $dept = Department::firstOrNew(['code' => $this->str($row, 'department_code')]);
        $isNew = ! $dept->exists;
        $dept->name         = $this->str($row, 'department_name');
        $dept->report_email = $this->str($row, 'report_email') ?: null;

        if ($this->import->mode->value === 'create_only' && ! $isNew) {
            return 'updated'; // skip
        }
        $dept->save();

        $vw = VirtualWarehouse::firstOrNew(['code' => $this->str($row, 'vw_code')]);
        $isNewVw = ! $vw->exists;
        $vw->name          = $this->str($row, 'vw_name');
        $vw->department_id = $dept->id;

        if ($this->import->mode->value === 'create_only' && ! $isNewVw) {
            return 'updated';
        }
        $vw->save();

        return ($isNew || $isNewVw) ? 'created' : 'updated';
    }
}
