<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'container_dwell_warning_days'],  ['value' => '7',  'type' => 'integer']);
        \App\Models\Setting::updateOrCreate(['key' => 'container_dwell_critical_days'], ['value' => '14', 'type' => 'integer']);
        \App\Models\Setting::updateOrCreate(['key' => 'default_free_days'],             ['value' => '7',  'type' => 'integer']);
    }

    public function down(): void
    {
        \App\Models\Setting::whereIn('key', [
            'container_dwell_warning_days',
            'container_dwell_critical_days',
            'default_free_days',
        ])->delete();
    }
};
