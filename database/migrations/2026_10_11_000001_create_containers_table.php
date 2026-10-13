<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('container_no', 11);
            $table->string('seal_no')->nullable();
            $table->string('size_type');
            $table->string('bl_no')->nullable();
            $table->string('vessel')->nullable();
            $table->string('voyage')->nullable();
            $table->foreignId('shipper_partner_id')->nullable()->references('id')->on('partners')->nullOnDelete();
            $table->foreignId('consignee_partner_id')->nullable()->references('id')->on('partners')->nullOnDelete();
            $table->foreignId('shipping_line_partner_id')->nullable()->references('id')->on('partners')->nullOnDelete();
            $table->date('eta')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->string('arrival_seal_no')->nullable();
            $table->boolean('seal_verified')->nullable();
            $table->unsignedInteger('free_days')->nullable();
            $table->foreignId('yard_location_id')->nullable()->references('id')->on('locations')->nullOnDelete();
            $table->string('status')->default('expected');
            $table->timestamp('unloading_started_at')->nullable();
            $table->timestamp('unloaded_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_to_partner_id')->nullable()->references('id')->on('partners')->nullOnDelete();
            $table->text('release_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // MySQL-only: generated column + unique index for active container_no uniqueness.
        // SQLite does not support generated columns; rely on validation + mysql-group tests instead.
        try {
            DB::statement("ALTER TABLE containers ADD COLUMN active_container_no VARCHAR(11) GENERATED ALWAYS AS (IF(status NOT IN ('released','cancelled'), container_no, NULL)) STORED");
            DB::statement("ALTER TABLE containers ADD UNIQUE INDEX containers_active_container_no_unique (active_container_no)");
        } catch (\Throwable) {
            // SQLite or unsupported driver — silently skip; uniqueness enforced in validation
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
