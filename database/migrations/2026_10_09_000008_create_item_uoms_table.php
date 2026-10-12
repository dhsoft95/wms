<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('factor_to_base', 18, 4); // e.g. 1 CTN = 24 PCS so factor = 24
            $table->boolean('is_default_purchase')->default(false);
            $table->boolean('is_default_issue')->default(false);
            $table->timestamps();

            $table->unique(['item_id', 'uom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_uoms');
    }
};
