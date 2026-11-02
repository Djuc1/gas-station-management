<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('station_id')
                ->constrained('stations')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('storage_tank_id')
                ->constrained('storage_tanks')
                ->restrictOnDelete();

            $table->foreignId('fuel_product_id')
                ->constrained('fuel_products')
                ->restrictOnDelete();

            $table->string('reference', 100);

            $table->string('invoice_number', 100);

            $table->decimal('quantity_litres', 14, 3);

            $table->decimal('unit_price', 14, 2);

            $table->decimal('total_cost', 16, 2);

            $table->dateTime('delivered_at');

            $table->string('status', 20)->default('pending');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->dateTime('approved_at')->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->dateTime('rejected_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['company_id', 'invoice_number'],
                'fuel_deliveries_company_invoice_unique'
            );

            $table->unique(
                'reference',
                'fuel_deliveries_reference_unique'
            );

            $table->index(
                ['company_id', 'station_id', 'status'],
                'fuel_deliveries_company_station_status_index'
            );

            $table->index(
                ['storage_tank_id', 'created_at'],
                'fuel_deliveries_tank_created_index'
            );

            $table->index(
                ['supplier_id', 'delivered_at'],
                'fuel_deliveries_supplier_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_deliveries');
    }
};