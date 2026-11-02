<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('code', 30);
            $table->string('contact_person', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'suppliers_company_code_unique'
            );

            $table->index(
                ['company_id', 'status'],
                'suppliers_company_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};