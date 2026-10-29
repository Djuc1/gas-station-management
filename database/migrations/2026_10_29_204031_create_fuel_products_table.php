<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->string('name', 100);
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('litre');
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'fuel_products_company_code_unique'
            );

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_products');
    }
};
