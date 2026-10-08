<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete()->after('phone');
            $table->string('status')->default('invited')->after('department_id'); // invited|active|suspended
            $table->string('two_factor_method')->nullable()->after('status');     // email|app
            $table->boolean('require_two_factor')->default(false)->after('two_factor_method');
            $table->string('invitation_token', 100)->nullable()->after('require_two_factor');
            $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
            $table->timestamp('last_login_at')->nullable()->after('invitation_sent_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete()->after('last_login_ip');
            $table->softDeletes();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropForeign(['invited_by']);
            $table->dropColumn([
                'phone', 'status', 'two_factor_method', 'require_two_factor',
                'invitation_token', 'invitation_sent_at',
                'last_login_at', 'last_login_ip', 'invited_by',
            ]);
            $table->dropSoftDeletes();
            $table->string('password')->nullable(false)->change();
        });
    }
};
