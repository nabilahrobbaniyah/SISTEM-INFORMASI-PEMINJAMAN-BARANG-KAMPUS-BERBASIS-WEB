<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('target_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('old_value', 30)->nullable()->after('description');
            $table->string('new_value', 30)->nullable()->after('old_value');
            $table->text('reason')->nullable()->after('new_value');
            $table->string('result', 30)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_user_id');
            $table->dropColumn(['old_value', 'new_value', 'reason', 'result']);
        });
    }
};
