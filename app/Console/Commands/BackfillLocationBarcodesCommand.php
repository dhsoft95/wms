<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;

class BackfillLocationBarcodesCommand extends Command
{
    protected $signature   = 'locations:backfill-barcodes {--dry-run : Show what would be updated without saving}';
    protected $description = 'Set barcode = UPPER(code) for all locations where barcode is null';

    public function handle(): int
    {
        $query = Location::withTrashed()->whereNull('barcode');
        $count = $query->count();

        if ($count === 0) {
            $this->info('All locations already have barcodes.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Code', 'Would set barcode to'],
                $query->get(['id', 'code'])->map(fn ($l) => [
                    $l->id, $l->code, strtoupper($l->code),
                ])->toArray()
            );
            $this->line("Dry run — {$count} location(s) would be updated.");
            return self::SUCCESS;
        }

        $updated = 0;
        $query->chunkById(500, function ($locations) use (&$updated): void {
            foreach ($locations as $location) {
                $location->timestamps = false;
                $location->updateQuietly(['barcode' => strtoupper($location->code)]);
                $updated++;
            }
        });

        $this->info("Backfilled barcodes for {$updated} location(s).");
        return self::SUCCESS;
    }
}
