<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('station_id')
                ->constrained('stations')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('employee_number', 50);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('job_title')->nullable();
            $table->date('employment_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
            $table->index(['station_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
