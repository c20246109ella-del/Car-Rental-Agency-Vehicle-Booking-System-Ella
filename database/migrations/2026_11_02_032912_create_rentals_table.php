<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('fleet_car_id')
                ->constrained('fleet_cars')
                ->restrictOnDelete();

            $table->date('rental_start_date');
            $table->date('rental_end_date');

            $table->unsignedInteger('total_days');

            $table->decimal('daily_rate', 10, 2);
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->decimal('total_rental_cost', 12, 2);

            $table->timestamps();

            $table->index([
                'fleet_car_id',
                'rental_start_date',
                'rental_end_date'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};