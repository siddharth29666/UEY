<?php

namespace App\DTOs;

use App\Enums\DriverDocumentType;
use App\Http\Requests\UploadDocumentRequest;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class UploadDocumentDTO
{
    public function __construct(
        public DriverDocumentType $documentType,
        public UploadedFile $document,
        public ?Carbon $expiresAt
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(UploadDocumentRequest $request): self
    {
        return new self(
            documentType: DriverDocumentType::from($request->validated('document_type')),
            document: $request->file('document'),
            expiresAt: $request->validated('expires_at') ? Carbon::parse($request->validated('expires_at')) : null
        );
    }
}
