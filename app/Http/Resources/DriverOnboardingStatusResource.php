<?php

namespace App\Http\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DriverDocumentType;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverOnboardingStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $vehicle = $this->vehicles()->first();
        $bankAccount = $this->bankAccount;

        // Check required documents approval
        $requiredTypes = [
            DriverDocumentType::DRIVING_LICENSE,
            DriverDocumentType::VEHICLE_REGISTRATION,
            DriverDocumentType::INSURANCE,
        ];

        $uploadedDocs = $this->documents;

        $approvedDocsCount = $uploadedDocs->filter(function ($doc) use ($requiredTypes) {
            return in_array($doc->document_type, $requiredTypes) && $doc->status === DocumentStatus::APPROVED;
        })->count();

        $documentsApproved = ($approvedDocsCount === count($requiredTypes));
        $vehicleApproved = ($vehicle && $vehicle->status === VehicleStatus::APPROVED);
        $userActive = ($user && $user->status === UserStatus::ACTIVE);

        // Can go online: user is active, vehicle approved, required documents approved
        $canGoOnline = $userActive && $vehicleApproved && $documentsApproved;

        return [
            'driver_profile_id' => $this->id,
            'overall_status' => $user ? $user->status->value : null,
            'vehicle_status' => $vehicle ? $vehicle->status->value : null,
            'bank_account_completed' => !is_null($bankAccount),
            'can_go_online' => $canGoOnline,
            'requirements' => [
                'documents_approved' => $documentsApproved,
                'vehicle_approved' => $vehicleApproved,
                'bank_account_linked' => !is_null($bankAccount),
            ],
            'documents' => DriverDocumentResource::collection($uploadedDocs),
            'vehicle' => new VehicleResource($vehicle),
        ];
    }
}
