<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DriverDocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverBankAccount;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;
    protected DriverProfile $driverProfile;
    protected Vehicle $vehicle;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Create driver user and profile
        $this->driverUser = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447911999999',
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::PENDING_APPROVAL,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'license_number' => 'DL-999888',
            'license_expiry' => Carbon::now()->addYear(),
        ]);

        $vehicleType = VehicleType::create([
            'name' => 'Standard',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 5.00,
            'icon_url' => 'https://example.com/standard.png',
        ]);

        $this->vehicle = Vehicle::create([
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $vehicleType->id,
            'make' => 'Toyota',
            'model' => 'Prius',
            'year' => 2021,
            'color' => 'Silver',
            'plate_number' => 'ABC-999',
            'status' => VehicleStatus::PENDING,
        ]);

        // Create admin user
        $this->adminUser = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447911888888',
            'password' => Hash::make('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test a driver can upload a document.
     */
    public function test_driver_can_upload_document()
    {
        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $file = UploadedFile::fake()->create('license.pdf', 500);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/onboarding/documents', [
            'document_type' => 'driving_license',
            'document' => $file,
            'expires_at' => Carbon::now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Document uploaded successfully.',
            ])
            ->assertJsonStructure(['document' => ['id', 'document_type', 'document_path', 'status']]);

        $document = DriverDocument::first();
        $this->assertNotNull($document);
        $this->assertEquals(DriverDocumentType::DRIVING_LICENSE, $document->document_type);
        $this->assertEquals(DocumentStatus::PENDING, $document->status);

        // Verify file is stored in fake storage
        Storage::assertExists($document->document_path);
    }

    /**
     * Test upload validation fails if document expires in the past.
     */
    public function test_upload_fails_if_expired()
    {
        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $file = UploadedFile::fake()->create('license.pdf', 500);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/onboarding/documents', [
            'document_type' => 'driving_license',
            'document' => $file,
            'expires_at' => Carbon::now()->subDay()->toDateString(), // past date
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    }

    /**
     * Test drivers can re-upload a rejected document.
     */
    public function test_driver_can_reupload_rejected_document()
    {
        // 1. Pre-generate a rejected document in database
        $document = DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::DRIVING_LICENSE,
            'document_path' => 'driver_documents/old_license.pdf',
            'status' => DocumentStatus::REJECTED,
            'rejection_reason' => 'Blurry image.',
        ]);

        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;
        $file = UploadedFile::fake()->create('new_license.pdf', 500);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/onboarding/documents', [
            'document_type' => 'driving_license',
            'document' => $file,
        ]);

        $response->assertStatus(201);

        // Verify the document status resets to pending, and clears rejection reason
        $document->refresh();
        $this->assertEquals(DocumentStatus::PENDING, $document->status);
        $this->assertNull($document->rejection_reason);
        $this->assertNotEquals('driver_documents/old_license.pdf', $document->document_path);
        Storage::assertExists($document->document_path);
    }

    /**
     * Test onboarding status response contains correct keys.
     */
    public function test_onboarding_status_keys()
    {
        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/driver/onboarding/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'onboarding' => [
                    'overall_status' => 'pending_approval',
                    'vehicle_status' => 'pending',
                    'bank_account_completed' => false,
                    'can_go_online' => false,
                ],
            ]);
    }

    /**
     * Test saving and reading bank account.
     */
    public function test_bank_account_management()
    {
        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        // 1. Save Bank account
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/bank-account', [
            'bank_name' => 'Chase Bank',
            'account_holder_name' => 'Bob Driver',
            'account_number' => '1234567890',
            'routing_number' => '987654321',
            'swift_code' => 'CHASUS33',
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bank account saved successfully.',
            ])
            ->assertJsonStructure(['bank_account' => ['bank_name', 'account_holder_name', 'account_number_masked']]);

        // Verify details are encrypted in the database
        $this->assertDatabaseHas('driver_bank_accounts', [
            'driver_profile_id' => $this->driverProfile->id,
            'bank_name' => 'Chase Bank',
        ]);

        $dbRecord = DriverBankAccount::first();
        $this->assertNotEquals('1234567890', $dbRecord->getRawOriginal('account_number')); // must be encrypted in raw db column

        // Reset auth state for the next request
        Auth::forgetGuards();

        // 2. Read Bank account
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/driver/bank-account');

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'bank_account' => [
                    'bank_name' => 'Chase Bank',
                    'account_number_masked' => '******7890', // masked
                ],
            ]);
    }

    /**
     * Test admin can view pending documents list.
     */
    public function test_admin_can_view_pending_documents()
    {
        // 1. Create a pending document
        DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::DRIVING_LICENSE,
            'document_path' => 'driver_documents/license.pdf',
            'status' => DocumentStatus::PENDING,
        ]);

        $adminToken = $this->adminUser->createToken('admin-token', ['role:admin'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->getJson('/api/v1/admin/documents/pending');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'documents');
    }

    /**
     * Test admin can approve document and reject with reason.
     */
    public function test_admin_can_verify_document()
    {
        // 1. Create a pending document
        $doc = DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::DRIVING_LICENSE,
            'document_path' => 'driver_documents/license.pdf',
            'status' => DocumentStatus::PENDING,
        ]);

        $adminToken = $this->adminUser->createToken('admin-token', ['role:admin'])->plainTextToken;

        // 2. Reject document with reason
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->postJson("/api/v1/admin/documents/{$doc->id}/verify", [
            'status' => 'rejected',
            'rejection_reason' => 'Signature is missing.',
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Document has been rejected successfully.',
            ]);

        $doc->refresh();
        $this->assertEquals(DocumentStatus::REJECTED, $doc->status);
        $this->assertEquals('Signature is missing.', $doc->rejection_reason);

        // Reset auth state for the next request
        Auth::forgetGuards();

        // 3. Approve document
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->postJson("/api/v1/admin/documents/{$doc->id}/verify", [
            'status' => 'approved',
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Document has been approved successfully.',
            ]);

        $doc->refresh();
        $this->assertEquals(DocumentStatus::APPROVED, $doc->status);
        $this->assertNull($doc->rejection_reason);
    }

    /**
     * Test non-admin user cannot verify documents.
     */
    public function test_non_admin_cannot_verify_document()
    {
        $doc = DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::DRIVING_LICENSE,
            'document_path' => 'driver_documents/license.pdf',
            'status' => DocumentStatus::PENDING,
        ]);

        $driverToken = $this->driverUser->createToken('driver-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $driverToken,
        ])->postJson("/api/v1/admin/documents/{$doc->id}/verify", [
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test the driver auto-activation logic when all requirements are met.
     */
    public function test_driver_auto_activation()
    {
        // 1. Create 2 approved documents, and 1 pending document
        DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::DRIVING_LICENSE,
            'document_path' => 'path.pdf',
            'status' => DocumentStatus::APPROVED,
        ]);

        DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::VEHICLE_REGISTRATION,
            'document_path' => 'path.pdf',
            'status' => DocumentStatus::APPROVED,
        ]);

        $pendingDoc = DriverDocument::create([
            'driver_profile_id' => $this->driverProfile->id,
            'document_type' => DriverDocumentType::INSURANCE,
            'document_path' => 'path.pdf',
            'status' => DocumentStatus::PENDING,
        ]);

        // Initially Bob Driver is pending_approval and his vehicle is pending
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $this->driverUser->status);
        $this->assertEquals(VehicleStatus::PENDING, $this->vehicle->status);

        $adminToken = $this->adminUser->createToken('admin-token', ['role:admin'])->plainTextToken;

        // 2. Approve the final pending document
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->postJson("/api/v1/admin/documents/{$pendingDoc->id}/verify", [
            'status' => 'approved',
        ])->assertStatus(200);

        // Verify Bob Driver user status transitions to ACTIVE and his vehicle status to APPROVED
        $this->driverUser->refresh();
        $this->vehicle->refresh();

        $this->assertEquals(UserStatus::ACTIVE, $this->driverUser->status);
        $this->assertEquals(VehicleStatus::APPROVED, $this->vehicle->status);
    }
}
