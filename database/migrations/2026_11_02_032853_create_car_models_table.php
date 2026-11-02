<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_models', function (Blueprint $table) {
            $table->id();

            $table->string('model_name', 100);
            $table->decimal('daily_rate', 10, 2);

            $table->timestamps();

            $table->index('model_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_models');
    }
};