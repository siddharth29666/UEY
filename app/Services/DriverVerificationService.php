<?php

namespace App\Services;

use App\DTOs\SaveBankAccountDTO;
use App\DTOs\UploadDocumentDTO;
use App\Enums\DocumentStatus;
use App\Enums\DriverDocumentType;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverBankAccount;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DriverVerificationService
{
    /**
     * Upload or re-upload a driver document.
     */
    public function uploadDocument(DriverProfile $driver, UploadDocumentDTO $dto): DriverDocument
    {
        // Store the file securely (Laravel Storage default disk)
        $path = $dto->document->store('driver_documents');

        return DB::transaction(function () use ($driver, $dto, $path) {
            // Find existing document of the same type to update/overwrite
            $document = DriverDocument::where('driver_profile_id', $driver->id)
                ->where('document_type', $dto->documentType)
                ->first();

            if ($document) {
                // Delete the old file if it exists
                if (Storage::exists($document->document_path)) {
                    Storage::delete($document->document_path);
                }

                // Update the document record, resetting verification status to pending
                $document->update([
                    'document_path' => $path,
                    'status' => DocumentStatus::PENDING,
                    'rejection_reason' => null,
                    'expires_at' => $dto->expiresAt,
                ]);
            } else {
                // Create a new document record
                $document = DriverDocument::create([
                    'driver_profile_id' => $driver->id,
                    'document_type' => $dto->documentType,
                    'document_path' => $path,
                    'status' => DocumentStatus::PENDING,
                    'rejection_reason' => null,
                    'expires_at' => $dto->expiresAt,
                ]);
            }

            return $document;
        });
    }

    /**
     * Admin verifies (approves/rejects) a driver document.
     */
    public function verifyDocument(int $documentId, DocumentStatus $status, ?string $reason = null): DriverDocument
    {
        return DB::transaction(function () use ($documentId, $status, $reason) {
            $document = DriverDocument::findOrFail($documentId);

            $document->update([
                'status' => $status,
                'rejection_reason' => $status === DocumentStatus::REJECTED ? $reason : null,
            ]);

            // If the document is approved, check if we can activate the driver
            if ($status === DocumentStatus::APPROVED) {
                $this->checkAndActivateDriver($document->driverProfile);
            }

            return $document;
        });
    }

    /**
     * Create or update driver bank account details.
     */
    public function saveBankAccount(DriverProfile $driver, SaveBankAccountDTO $dto): DriverBankAccount
    {
        return DriverBankAccount::updateOrCreate(
            ['driver_profile_id' => $driver->id],
            [
                'bank_name' => $dto->bankName,
                'account_holder_name' => $dto->accountHolderName,
                'account_number' => $dto->accountNumber,
                'routing_number' => $dto->routingNumber,
                'swift_code' => $dto->swiftCode,
            ]
        );
    }

    /**
     * Check if all required onboarding criteria are met, and activate driver if they are.
     */
    public function checkAndActivateDriver(DriverProfile $driver): void
    {
        $requiredTypes = [
            DriverDocumentType::DRIVING_LICENSE,
            DriverDocumentType::VEHICLE_REGISTRATION,
            DriverDocumentType::INSURANCE,
        ];

        // Check if all required documents are approved
        $approvedCount = DriverDocument::where('driver_profile_id', $driver->id)
            ->whereIn('document_type', $requiredTypes)
            ->where('status', DocumentStatus::APPROVED)
            ->count();

        $allDocsApproved = ($approvedCount === count($requiredTypes));

        if ($allDocsApproved) {
            // Automatically approve driver's first vehicle if it is pending
            $vehicle = $driver->vehicles()->first();
            if ($vehicle && $vehicle->status === VehicleStatus::PENDING) {
                $vehicle->update(['status' => VehicleStatus::APPROVED]);
            }

            // Update user status to active
            $user = $driver->user;
            if ($user && $user->status !== UserStatus::ACTIVE) {
                $user->update(['status' => UserStatus::ACTIVE]);
            }
        }
    }
}
