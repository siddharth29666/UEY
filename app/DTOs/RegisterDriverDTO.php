<?php

namespace App\DTOs;

class RegisterDriverDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $email,
        public readonly string $phone,
        public readonly string $password,
        public readonly string $license_number,
        public readonly string $license_expiry,
        
        // Vehicle details
        public readonly string $vehicle_make,
        public readonly string $vehicle_model,
        public readonly int $vehicle_year,
        public readonly string $vehicle_color,
        public readonly string $vehicle_plate,
        public readonly int $vehicle_type_id
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            name: $request->input('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            password: $request->input('password'),
            license_number: $request->input('license_number'),
            license_expiry: $request->input('license_expiry'),
            vehicle_make: $request->input('vehicle_make'),
            vehicle_model: $request->input('vehicle_model'),
            vehicle_year: (int) $request->input('vehicle_year'),
            vehicle_color: $request->input('vehicle_color'),
            vehicle_plate: $request->input('vehicle_plate'),
            vehicle_type_id: (int) $request->input('vehicle_type_id')
        );
    }
}
