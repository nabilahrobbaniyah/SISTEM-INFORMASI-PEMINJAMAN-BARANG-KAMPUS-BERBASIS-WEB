<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('lost_quantity')->default(0);
            $table->unsignedInteger('inspection_quantity')->default(0);
            $table->unsignedInteger('repair_quantity')->default(0);
            $table->unsignedInteger('retired_quantity')->default(0);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable()->unique();
        });

        Schema::table('loan_details', function (Blueprint $table) {
            $table->unsignedInteger('returned_good_quantity')->default(0);
            $table->unsignedInteger('returned_light_damage_quantity')->default(0);
            $table->unsignedInteger('returned_heavy_damage_quantity')->default(0);
            $table->unsignedInteger('returned_lost_quantity')->default(0);
            $table->foreignId('return_inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('return_inspected_at')->nullable();
            $table->string('return_photo')->nullable();
        });

        DB::table('loan_details')
            ->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->where('loans.status', 'dikembalikan')
            ->whereIn('loan_details.return_condition', ['baik', 'rusak ringan', 'rusak berat'])
            ->select([
                'loan_details.id as id',
                'loan_details.quantity',
                'loan_details.return_condition',
                'loans.returned_to',
                'loans.returned_at',
            ])
            ->orderBy('loan_details.id')
            ->chunkById(500, function ($details): void {
                foreach ($details as $detail) {
                    $conditionColumn = match ($detail->return_condition) {
                        'baik' => 'returned_good_quantity',
                        'rusak ringan' => 'returned_light_damage_quantity',
                        'rusak berat' => 'returned_heavy_damage_quantity',
                    };

                    DB::table('loan_details')
                        ->where('id', $detail->id)
                        ->update([
                            $conditionColumn => $detail->quantity,
                            'return_inspected_by' => $detail->returned_to,
                            'return_inspected_at' => $detail->returned_at,
                        ]);
                }
            }, 'loan_details.id', 'id');

        Schema::create('inventory_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('loan_detail_id')->nullable()->constrained('loan_details')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->unsignedInteger('quantity');
            $table->timestamp('occurred_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('description');
            $table->string('evidence_path')->nullable();
            $table->string('result', 40)->nullable();
            $table->timestamps();

            $table->index(['item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_events');
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_inspected_by');
            $table->dropColumn([
                'returned_good_quantity',
                'returned_light_damage_quantity',
                'returned_heavy_damage_quantity',
                'returned_lost_quantity',
                'return_inspected_at',
                'return_photo',
            ]);
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique(['submission_key']);
            $table->dropColumn('submission_key');
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['lost_quantity', 'inspection_quantity', 'repair_quantity', 'retired_quantity']);
        });
    }
};
