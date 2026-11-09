<?php

namespace Database\Seeders;

use App\Models\CarModel;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        CarModel::firstOrCreate(
            ['model_name' => 'Toyota Corolla'],
            ['daily_rate' => 65.00],
        );

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
