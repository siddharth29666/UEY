<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Bike',
                'capacity' => 1,
                'base_fare' => 1.00,
                'per_km_rate' => 0.50,
                'per_minute_rate' => 0.10,
                'minimum_fare' => 2.00,
                'commission_percentage' => 10.00,
                'icon_url' => 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/bike.png',
                'active' => true,
            ],
            [
                'name' => 'Mini',
                'capacity' => 4,
                'base_fare' => 1.80,
                'per_km_rate' => 0.90,
                'per_minute_rate' => 0.20,
                'minimum_fare' => 4.00,
                'commission_percentage' => 12.00,
                'icon_url' => 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/mini.png',
                'active' => true,
            ],
            [
                'name' => 'Sedan',
                'capacity' => 4,
                'base_fare' => 2.50,
                'per_km_rate' => 1.20,
                'per_minute_rate' => 0.30,
                'minimum_fare' => 5.00,
                'commission_percentage' => 15.00,
                'icon_url' => 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/sedan.png',
                'active' => true,
            ],
            [
                'name' => 'SUV',
                'capacity' => 6,
                'base_fare' => 3.50,
                'per_km_rate' => 1.80,
                'per_minute_rate' => 0.40,
                'minimum_fare' => 8.00,
                'commission_percentage' => 15.00,
                'icon_url' => 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/suv.png',
                'active' => true,
            ],
            [
                'name' => 'XL',
                'capacity' => 7,
                'base_fare' => 4.00,
                'per_km_rate' => 2.00,
                'per_minute_rate' => 0.50,
                'minimum_fare' => 10.00,
                'commission_percentage' => 15.00,
                'icon_url' => 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/xl.png',
                'active' => true,
            ],
            [
                'name' => 'Luxury',
                'capacity' => 4,
                'base_fare' => 5.00,
                'per_km_rate' => 2.50,
                'per_minute_rate' => 0.60,
                'minimum_fare' => 12.00,
                'commission_percentage' => 20.00,
                'icon_url' => 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/luxury.png',
                'active' => true,
            ],
        ];

        foreach ($types as $type) {
            VehicleType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
