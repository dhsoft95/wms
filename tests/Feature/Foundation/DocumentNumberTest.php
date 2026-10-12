<?php

namespace Tests\Feature\Foundation;

use App\Models\DocumentSequence;
use App\Services\Foundation\DocumentNumber;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        DocumentSequence::create([
            'type'         => 'GRN',
            'prefix'       => 'GRN',
            'include_year' => true,
            'padding'      => 4,
            'next_number'  => 1,
            'reset_yearly' => true,
        ]);

        DocumentSequence::create([
            'type'         => 'DO',
            'prefix'       => 'DO',
            'include_year' => false,
            'padding'      => 4,
            'next_number'  => 1,
            'reset_yearly' => false,
        ]);
    }

    public function test_formats_number_with_year_and_padding(): void
    {
        $result = DocumentNumber::next('GRN');

        expect($result)->toBe('GRN-' . now()->year . '-0001');
    }

    public function test_formats_number_without_year(): void
    {
        $result = DocumentNumber::next('DO');

        expect($result)->toBe('DO-0001');
    }

    public function test_increments_on_each_call(): void
    {
        DocumentNumber::next('GRN'); // first call → 0001

        $second = DocumentNumber::next('GRN');

        expect($second)->toBe('GRN-' . now()->year . '-0002');
    }

    public function test_yearly_reset_triggers_on_new_year(): void
    {
        // Simulate a sequence that was last reset in a previous year at number 50
        DocumentSequence::where('type', 'GRN')->update([
            'last_reset_year' => now()->year - 1,
            'next_number'     => 50,
        ]);

        $result = DocumentNumber::next('GRN');

        expect($result)->toBe('GRN-' . now()->year . '-0001');
    }

    public function test_concurrency_no_duplicates(): void
    {
        // SQLite doesn't support true parallelism, so we simulate by
        // issuing 10 sequential calls and confirming all numbers are unique.
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = DocumentNumber::next('GRN');
        }

        // All 10 should be distinct
        expect(array_unique($results))->toHaveCount(10);

        // The last result should be the tenth number
        expect(end($results))->toBe('GRN-' . now()->year . '-0010');
    }
}
