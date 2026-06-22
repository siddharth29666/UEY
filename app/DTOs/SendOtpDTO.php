<?php

namespace App\DTOs;

use App\Enums\OtpType;

class SendOtpDTO
{
    public function __construct(
        public readonly string $phone,
        public readonly OtpType $type
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            phone: $request->input('phone'),
            type: OtpType::from($request->input('type'))
        );
    }
}
