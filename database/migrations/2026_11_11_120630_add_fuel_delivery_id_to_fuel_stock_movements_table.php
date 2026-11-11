<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_stock_movements', function (Blueprint $table) {
            $table->foreignId('fuel_delivery_id')
                ->nullable()
                ->constrained('fuel_deliveries')
                ->restrictOnDelete();

            $table->unique(
                'fuel_delivery_id',
                'fuel_stock_movements_fuel_delivery_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fuel_stock_movements', function (Blueprint $table) {
            $table->dropUnique('fuel_stock_movements_fuel_delivery_unique');
            $table->dropForeign(['fuel_delivery_id']);
            $table->dropColumn('fuel_delivery_id');
        });
    }
};
