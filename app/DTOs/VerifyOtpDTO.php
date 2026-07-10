<?php

namespace App\DTOs;

use App\Enums\OtpType;
use Illuminate\Http\Request;

class VerifyOtpDTO
{
    public function __construct(
        public readonly string $phone,
        public readonly string $code,
        public readonly OtpType $type
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            phone: $request->input('phone'),
            code: $request->input('code'),
            type: OtpType::from($request->input('type'))
        );
    }
}
