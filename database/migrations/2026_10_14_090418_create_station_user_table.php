<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_user', function (Blueprint $table) {
            $table->foreignId('station_id')
                ->constrained('stations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary(['station_id', 'user_id']);
            $table->index(['user_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_user');
    }
};
