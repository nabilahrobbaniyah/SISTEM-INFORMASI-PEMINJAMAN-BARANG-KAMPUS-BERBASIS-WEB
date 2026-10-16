<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_disposal_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_disposal_proposals');
    }
};
