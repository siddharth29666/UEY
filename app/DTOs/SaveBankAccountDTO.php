<?php

namespace App\DTOs;

use App\Http\Requests\SaveBankAccountRequest;

class SaveBankAccountDTO
{
    public function __construct(
        public string $bankName,
        public string $accountHolderName,
        public string $accountNumber,
        public ?string $routingNumber,
        public ?string $swiftCode
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(SaveBankAccountRequest $request): self
    {
        return new self(
            bankName: $request->validated('bank_name'),
            accountHolderName: $request->validated('account_holder_name'),
            accountNumber: $request->validated('account_number'),
            routingNumber: $request->validated('routing_number'),
            swiftCode: $request->validated('swift_code')
        );
    }
}
