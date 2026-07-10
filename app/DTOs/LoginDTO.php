<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class LoginDTO
{
    public function __construct(
        public readonly string $phone,
        public readonly string $password
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            phone: $request->input('phone'),
            password: $request->input('password')
        );
    }
}
