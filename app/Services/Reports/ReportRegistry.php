<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Contracts\Reports\ReportDefinition;
use Illuminate\Support\Facades\Log;

final class ReportRegistry
{
    /** @var ReportDefinition[]|null */
    private ?array $instances = null;

    /** @return ReportDefinition[] */
    public function all(): array
    {
        return $this->loadInstances();
    }

    /** @return array<string, ReportDefinition[]> keyed by group */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->loadInstances() as $def) {
            $grouped[$def->group()][] = $def;
        }

        return $grouped;
    }

    public function find(string $key): ?ReportDefinition
    {
        foreach ($this->loadInstances() as $def) {
            if ($def->key() === $key) {
                return $def;
            }
        }

        return null;
    }

    /** @return ReportDefinition[] */
    private function loadInstances(): array
    {
        if ($this->instances !== null) {
            return $this->instances;
        }

        $this->instances = [];

        foreach (config('reports', []) as $group => $classes) {
            if (! is_array($classes)) {
                continue;
            }
            foreach ($classes as $fqcn) {
                if (! class_exists($fqcn)) {
                    Log::warning("ReportRegistry: class {$fqcn} not found, skipping.");
                    continue;
                }

                $instance = new $fqcn();

                if (! $instance instanceof ReportDefinition) {
                    Log::warning("ReportRegistry: {$fqcn} does not implement ReportDefinition, skipping.");
                    continue;
                }

                $this->instances[] = $instance;
            }
        }

        return $this->instances;
    }
}
