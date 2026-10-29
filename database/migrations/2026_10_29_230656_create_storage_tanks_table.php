<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_tanks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('station_id')
                ->constrained('stations')
                ->restrictOnDelete();

            $table->foreignId('fuel_product_id')
                ->constrained('fuel_products')
                ->restrictOnDelete();

            $table->string('name', 100);
            $table->string('code', 30);

            $table->decimal('capacity_litres', 14, 3);
            $table->decimal('current_quantity_litres', 14, 3)
                ->default(0);

            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->unique(
                ['station_id', 'code'],
                'storage_tanks_station_code_unique'
            );

            $table->index(
                ['company_id', 'station_id', 'status'],
                'storage_tanks_company_station_status_index'
            );

            $table->index(
                ['fuel_product_id', 'status'],
                'storage_tanks_product_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_tanks');
    }
};
