<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_cars', function (Blueprint $table) {
            $table->id();

            $table->string('vehicle_vin', 50)->unique();

            $table->foreignId('car_model_id')
                ->constrained('car_models')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('car_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_cars');
    }
};