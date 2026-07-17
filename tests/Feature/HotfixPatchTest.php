<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DriverDocumentType;
use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\OtpVerification;
use App\Models\User;
use App\Models\Wallet;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Stripe\PaymentIntent;
use Tests\TestCase;

class HotfixPatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Wallet Top-up API enhancement: response contains stripe_publishable_key matching STRIPE_KEY.
     */
    public function test_wallet_topup_response_contains_stripe_publishable_key()
    {
        $rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        Wallet::create([
            'user_id' => $rider->id,
            'balance' => 0.00,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        Sanctum::actingAs($rider, ['role:rider']);

        // Set Stripe key config
        config(['services.stripe.key' => 'pk_test_sample_stripe_publishable_key_12345']);

        // Mock Stripe Service
        $stripeMock = $this->mock(StripeService::class);
        $intentFake = PaymentIntent::constructFrom([
            'id' => 'pi_test_abc123',
            'client_secret' => 'pi_test_abc123_secret_actualstripeclientsecret99999'
        ]);
        $stripeMock->shouldReceive('createPaymentIntent')
            ->once()
            ->with(100.00, 'USD', \Mockery::any())
            ->andReturn($intentFake);

        $response = $this->postJson('/api/v1/wallet/top-up', [
            'amount' => 100.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'payment_intent' => 'pi_test_abc123',
            'client_secret' => 'pi_test_abc123_secret_actualstripeclientsecret99999',
            'stripe_publishable_key' => 'pk_test_sample_stripe_publishable_key_12345',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);
    }

    /**
     * Test Driver Online status validation: overall_status approved vs other states.
     */
    public function test_driver_cannot_go_online_unless_documents_approved()
    {
        $driverUser = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447922222222',
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE, // Active user but documents not approved yet
        ]);

        $driverProfile = DriverProfile::create([
            'user_id' => $driverUser->id,
            'license_number' => 'DL-111222',
            'license_expiry' => Carbon::now()->addYear(),
        ]);

        Sanctum::actingAs($driverUser, ['role:driver']);

        // 1. Initially overall_status is 'missing' (no documents uploaded) -> should block
        $this->assertEquals('missing', $driverProfile->overall_status);

        $response = $this->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Your documents must be approved before you can go online.',
        ]);

        // 2. Add some documents with 'pending' status -> should block
        $doc1 = DriverDocument::create([
            'driver_profile_id' => $driverProfile->id,
            'document_type' => DriverDocumentType::DRIVING_LICENSE,
            'document_path' => 'documents/license.jpg',
            'status' => DocumentStatus::PENDING,
            'expires_at' => Carbon::now()->addYear(),
        ]);
        $doc2 = DriverDocument::create([
            'driver_profile_id' => $driverProfile->id,
            'document_type' => DriverDocumentType::VEHICLE_REGISTRATION,
            'document_path' => 'documents/registration.jpg',
            'status' => DocumentStatus::PENDING,
            'expires_at' => Carbon::now()->addYear(),
        ]);
        $doc3 = DriverDocument::create([
            'driver_profile_id' => $driverProfile->id,
            'document_type' => DriverDocumentType::INSURANCE,
            'document_path' => 'documents/insurance.jpg',
            'status' => DocumentStatus::PENDING,
            'expires_at' => Carbon::now()->addYear(),
        ]);

        $driverProfile->refresh();
        $this->assertEquals('pending', $driverProfile->overall_status);

        $response = $this->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Your documents must be approved before you can go online.',
        ]);

        // 3. Mark one document as rejected -> overall_status becomes 'rejected' -> should block
        $doc1->update(['status' => DocumentStatus::REJECTED]);
        $doc2->update(['status' => DocumentStatus::APPROVED]);
        $doc3->update(['status' => DocumentStatus::APPROVED]);

        $driverProfile->refresh();
        $this->assertEquals('rejected', $driverProfile->overall_status);

        $response = $this->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Your documents must be approved before you can go online.',
        ]);

        // 4. Mark all as approved but one document expired -> overall_status becomes 'expired' -> should block
        $doc1->update([
            'status' => DocumentStatus::APPROVED,
            'expires_at' => Carbon::now()->subDay(), // expired
        ]);

        $driverProfile->refresh();
        $this->assertEquals('expired', $driverProfile->overall_status);

        $response = $this->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Your documents must be approved before you can go online.',
        ]);

        // 5. Mark all approved and valid -> overall_status becomes 'approved' -> should succeed
        $doc1->update([
            'status' => DocumentStatus::APPROVED,
            'expires_at' => Carbon::now()->addYear(),
        ]);

        $driverProfile->refresh();
        $this->assertEquals('approved', $driverProfile->overall_status);

        $response = $this->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_online' => true,
        ]);
    }

    /**
     * Test soft deleted phone numbers can be re-registered.
     */
    public function test_re_registration_succeeds_with_soft_deleted_phone()
    {
        $phone = '+447933333333';

        // 1. Create a user
        $user = User::create([
            'name' => 'Charlie Rider',
            'phone' => $phone,
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // 2. Soft delete the user
        $user->delete();

        // Verify it is soft-deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // 3. Setup OTP verification for same phone number
        OtpVerification::create([
            'phone' => $phone,
            'code' => '654321',
            'type' => OtpType::REGISTER,
            'created_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(10),
            'verified_at' => Carbon::now(),
        ]);

        // 4. Try registering again with the same phone number
        $response = $this->postJson('/api/v1/rider/register', [
            'name' => 'Charlie Rider Two',
            'phone' => $phone,
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'name' => 'Charlie Rider Two',
            'phone' => $phone,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test active user blocks registration with the same phone number.
     */
    public function test_active_user_blocks_registration_with_same_phone()
    {
        $phone = '+447944444444';

        // 1. Create active user
        User::create([
            'name' => 'David Rider',
            'phone' => $phone,
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // 2. Setup OTP verification
        OtpVerification::create([
            'phone' => $phone,
            'code' => '123456',
            'type' => OtpType::REGISTER,
            'created_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(10),
            'verified_at' => Carbon::now(),
        ]);

        // 3. Try registering again with the same phone number -> must fail unique check
        $response = $this->postJson('/api/v1/rider/register', [
            'name' => 'David Rider Duplicate',
            'phone' => $phone,
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }
}
