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
            $table->unsignedInteger('light_damage_quantity')->default(0);
            $table->unsignedInteger('heavy_damage_quantity')->default(0);
            $table->unsignedInteger('disposed_quantity')->default(0);
        });

        DB::table('items')->update([
            'heavy_damage_quantity' => DB::raw('retired_quantity'),
            'retired_quantity' => 0,
        ]);

        DB::table('items')
            ->where('condition', 'rusak ringan')
            ->update([
                'light_damage_quantity' => DB::raw('light_damage_quantity + available_quantity'),
                'total_quantity' => DB::raw('total_quantity - available_quantity'),
                'available_quantity' => 0,
            ]);

        DB::table('items')
            ->where('condition', 'rusak berat')
            ->update([
                'heavy_damage_quantity' => DB::raw('heavy_damage_quantity + available_quantity'),
                'total_quantity' => DB::raw('total_quantity - available_quantity'),
                'available_quantity' => 0,
            ]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('retired_quantity');
        });

        Schema::table('inventory_events', function (Blueprint $table) {
            $table->string('state_before', 40)->nullable();
            $table->string('state_after', 40)->nullable();
            $table->unsignedInteger('quantity_before')->nullable();
            $table->unsignedInteger('quantity_after')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_events', function (Blueprint $table) {
            $table->dropColumn(['state_before', 'state_after', 'quantity_before', 'quantity_after']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('retired_quantity')->default(0);
        });

        DB::table('items')->update([
            'retired_quantity' => DB::raw('heavy_damage_quantity'),
        ]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['light_damage_quantity', 'heavy_damage_quantity', 'disposed_quantity']);
        });
    }
};
