<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('station_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('storage_tank_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('fuel_product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('movement_type', 30);

            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_after', 14, 3);

            $table->string('reference', 100)->unique();

            $table->text('description')->nullable();

            $table->foreignId('related_movement_id')
                ->nullable()
                ->constrained('fuel_stock_movements')
                ->restrictOnDelete();

            $table->foreignId('performed_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'station_id', 'created_at']);
            $table->index(['storage_tank_id', 'created_at']);
            $table->index(['fuel_product_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_movements');
    }
};
