<?php

namespace App\DTOs;

class LoginDTO
{
    public function __construct(
        public readonly string $phone,
        public readonly string $password
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            phone: $request->input('phone'),
            password: $request->input('password')
        );
    }
}
