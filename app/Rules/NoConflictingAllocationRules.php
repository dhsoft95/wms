<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoConflictingAllocationRules implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rules = is_array($value) ? $value : [];

        if (in_array('fifo', $rules, true) && in_array('lifo', $rules, true)) {
            $fail('FIFO and LIFO cannot both be selected.');
        }
    }
}
