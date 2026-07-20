<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;
    protected User $admin;
    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Test Rider',
            'email' => 'rider@example.com',
            'phone' => '+447911111111',
            'password' => bcrypt('password'),
            'role' => \App\Enums\UserRole::RIDER,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);

        $this->admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'phone' => '+447999999999',
            'password' => bcrypt('password'),
            'role' => \App\Enums\UserRole::ADMIN,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);

        $this->vehicleType = VehicleType::create([
            'name' => 'Standard Sedan',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);
    }

    /**
     * Create a default promo code.
     */
    protected function createPromo(array $attributes = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'WELCOME50',
            'discount_type' => 'percentage',
            'discount_value' => 50.00,
            'expires_at' => Carbon::now()->addDays(7),
            'usage_limit' => null,
            'per_user_limit' => 1,
            'min_fare' => 5.00,
            'max_discount' => 100.00,
            'is_active' => true,
            'first_ride_only' => false,
            'ride_eligibility' => null,
        ], $attributes));
    }

    public function test_can_validate_active_percentage_promo()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['discount_value' => 50.00, 'max_discount' => 10.00]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 16.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.promo.code', 'WELCOME50')
            ->assertJsonPath('data.discount_amount', '8.00') // 50% of 16
            ->assertJsonPath('data.final_fare', '8.00');
    }

    public function test_validate_promo_caps_at_max_discount()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['discount_value' => 50.00, 'max_discount' => 5.00]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 20.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount_amount', '5.00') // capped at max_discount
            ->assertJsonPath('data.final_fare', '15.00');
    }

    public function test_can_validate_flat_promo()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo([
            'discount_type' => 'flat',
            'discount_value' => 5.00,
        ]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 12.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount_amount', '5.00')
            ->assertJsonPath('data.final_fare', '7.00');
    }

    public function test_validate_promo_fails_if_inactive()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['is_active' => false]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Promo code is invalid or unavailable.');
    }

    public function test_validate_promo_fails_if_expired()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['expires_at' => Carbon::now()->subDays(1)]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_validate_promo_fails_if_global_limit_reached()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['usage_limit' => 1]);

        // Create a completed usage
        PromoCodeUsage::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->rider->id,
            'discount_amount' => 5.00,
            'status' => 'completed',
        ]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_validate_promo_fails_if_per_user_limit_reached()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['per_user_limit' => 1]);

        PromoCodeUsage::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->rider->id,
            'discount_amount' => 5.00,
            'status' => 'reserved',
        ]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_validate_promo_fails_if_vehicle_mismatch()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['ride_eligibility' => [9999]]); // Mismatch

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_validate_promo_fails_if_not_first_ride()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['first_ride_only' => true]);

        // Create completed ride
        Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_address' => 'B',
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'status' => \App\Enums\RideStatus::COMPLETED,
            'otp' => '123456',
        ]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_validate_promo_fails_if_fare_below_minimum()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['min_fare' => 20.00]);

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_validate_promo_fails_if_soft_deleted()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo();
        $promo->delete(); // Soft delete

        $response = $this->postJson('/api/v1/promos/validate', [
            'promo_code' => $promo->code,
            'vehicle_type_id' => $this->vehicleType->id,
            'estimated_fare' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_successful_ride_booking_with_promo()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['discount_value' => 50.00]);

        // Bypass OTP verified check in test
        DB::table('otp_verifications')->insert([
            'phone' => $this->rider->phone,
            'code' => '123456',
            'type' => 'register',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Trigger request ride
        $response = $this->postJson('/api/v1/rides/request', [
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'pickup_address' => 'London Eye',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'destination_address' => 'Regent Park',
            'vehicle_type_id' => $this->vehicleType->id,
            'promo_code' => $promo->code,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['ride' => ['discount_amount', 'final_estimated_fare']]);

        $this->assertDatabaseHas('rides', [
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
        ]);

        $ride = Ride::latest()->first();
        $this->assertGreaterThan(0.00, (float) $ride->discount_amount);
        $this->assertEquals((float) $ride->estimated_fare - (float) $ride->discount_amount, (float) $ride->final_estimated_fare);

        // Verify promo_code_usages has status 'reserved'
        $this->assertDatabaseHas('promo_code_usages', [
            'promo_code_id' => $promo->id,
            'user_id' => $this->rider->id,
            'ride_id' => $ride->id,
            'status' => 'reserved',
        ]);
    }

    public function test_ride_cancellation_releases_promo_usage()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['discount_value' => 5.00]);

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_address' => 'B',
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'discount_amount' => 5.00,
            'final_estimated_fare' => 10.00,
            'status' => \App\Enums\RideStatus::PENDING,
            'otp' => '123456',
        ]);

        PromoCodeUsage::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->rider->id,
            'ride_id' => $ride->id,
            'discount_amount' => 5.00,
            'status' => 'reserved',
        ]);
        $promo->increment('used_count');

        $response = $this->postJson("/api/v1/rides/{$ride->id}/cancel", [
            'reason' => 'Changed my mind',
        ]);

        $response->assertStatus(200);

        // Verify usage status changed to cancelled and count decremented
        $this->assertDatabaseHas('promo_code_usages', [
            'ride_id' => $ride->id,
            'status' => 'cancelled',
        ]);
        $this->assertEquals(0, $promo->fresh()->used_count);
    }

    public function test_ride_completion_recalculates_fare_and_completes_promo()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo([
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
        ]);

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_address' => 'B',
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'discount_amount' => 3.00,
            'final_estimated_fare' => 12.00,
            'status' => \App\Enums\RideStatus::ACCEPTED,
            'otp' => '123456',
        ]);

        PromoCodeUsage::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->rider->id,
            'ride_id' => $ride->id,
            'discount_amount' => 3.00,
            'status' => 'reserved',
        ]);

        // Complete the ride using RideLifecycleService
        $lifecycleService = app(\App\Services\RideLifecycleService::class);
        
        // Mock driver profile to satisfy completeRide parameters
        $driver = User::create([
            'name' => 'Driver User',
            'email' => 'driver@example.com',
            'phone' => '+447911222222',
            'password' => bcrypt('password'),
            'role' => \App\Enums\UserRole::DRIVER,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $driverProfile = \App\Models\DriverProfile::create([
            'user_id' => $driver->id,
            'license_number' => 'DL-111111',
            'license_expiry' => '2028-01-01',
            'rating' => 5.0,
        ]);
        $ride->update(['driver_profile_id' => $driverProfile->id]);

        $completedRide = $lifecycleService->updateRideStatus($ride, \App\Enums\RideStatus::COMPLETED, 10.0, 15);

        // Assert recalculated fields
        $this->assertEquals(20.00, (float) $completedRide->actual_discount_amount * 100 / (float) $completedRide->actual_fare);
        $this->assertEquals((float) $completedRide->actual_fare - (float) $completedRide->actual_discount_amount, (float) $completedRide->final_actual_fare);

        // Verify promo usage marked completed
        $this->assertDatabaseHas('promo_code_usages', [
            'ride_id' => $ride->id,
            'status' => 'completed',
            'discount_amount' => $completedRide->actual_discount_amount,
        ]);
    }

    public function test_concurrency_safe_reservation_limits()
    {
        $promo = $this->createPromo(['usage_limit' => 1]);

        $rider2 = User::create([
            'name' => 'Rider 2',
            'email' => 'rider2@example.com',
            'phone' => '+447911111112',
            'password' => bcrypt('password'),
            'role' => \App\Enums\UserRole::RIDER,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);

        $promoService = app(\App\Services\PromoService::class);

        // First reserve succeeds
        $usage1 = $promoService->reservePromo($this->rider, $promo->code, $this->vehicleType->id, 10.00, 101);
        $this->assertNotNull($usage1);

        // Second reserve concurrently fails (throws Exception)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Promo code is invalid or unavailable.');
        $promoService->reservePromo($rider2, $promo->code, $this->vehicleType->id, 10.00, 102);
    }

    public function test_unauthenticated_rider_cannot_list_or_view_history()
    {
        $this->getJson('/api/v1/promos')->assertStatus(401);
        $this->getJson('/api/v1/promos/history')->assertStatus(401);
    }

    public function test_rider_can_list_active_eligible_promos_with_correct_sorting()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // Create multiple promos with different eligibilities and expiries
        // 1. Eligible promo expiring soonest
        $promo1 = $this->createPromo([
            'code' => 'PROMO1',
            'expires_at' => Carbon::now()->addDays(2),
            'first_ride_only' => false,
        ]);
        // 2. Eligible promo expiring later
        $promo2 = $this->createPromo([
            'code' => 'PROMO2',
            'expires_at' => Carbon::now()->addDays(5),
            'first_ride_only' => false,
        ]);
        // 3. Ineligible promo (first ride only, but rider has completed a ride)
        $promo3 = $this->createPromo([
            'code' => 'PROMO3',
            'expires_at' => Carbon::now()->addDays(1),
            'first_ride_only' => true,
        ]);

        // Create completed ride for the rider to make PROMO3 ineligible
        Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_address' => 'B',
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'status' => \App\Enums\RideStatus::COMPLETED,
            'otp' => '123456',
        ]);

        $response = $this->getJson('/api/v1/promos');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(4, $data); // Includes WELCOME50 from setUp or previous test since database is reset per test but our code creates WELCOME50 at test start. Let's make sure of positions.
        
        // Let's assert sorting order
        // PROMO1 (eligible: true, expires in 2 days) should come before PROMO2 (eligible: true, expires in 5 days)
        // PROMO3 (eligible: false, expires in 1 day) should come last among these three because eligible DESC is the first sort key.
        
        $promo1Index = -1;
        $promo2Index = -1;
        $promo3Index = -1;

        foreach ($data as $index => $item) {
            if ($item['code'] === 'PROMO1') {
                $promo1Index = $index;
            } elseif ($item['code'] === 'PROMO2') {
                $promo2Index = $index;
            } elseif ($item['code'] === 'PROMO3') {
                $promo3Index = $index;
            }
        }

        $this->assertLessThan($promo2Index, $promo1Index);
        $this->assertGreaterThan($promo1Index, $promo3Index);
        $this->assertGreaterThan($promo2Index, $promo3Index);

        $this->assertTrue($data[$promo1Index]['eligible']);
        $this->assertTrue($data[$promo2Index]['eligible']);
        $this->assertFalse($data[$promo3Index]['eligible']);
    }

    public function test_rider_promo_listing_filters_hidden_and_limits()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // Inactive promo
        $this->createPromo([
            'code' => 'INACTIVE',
            'is_active' => false,
        ]);
        // Expired promo
        $this->createPromo([
            'code' => 'EXPIRED',
            'expires_at' => Carbon::now()->subMinutes(1),
        ]);
        // Soft deleted promo
        $deletedPromo = $this->createPromo([
            'code' => 'DELETED',
        ]);
        $deletedPromo->delete();

        // Exhausted global limit promo
        $exhaustedGlobal = $this->createPromo([
            'code' => 'EXHAUSTGLOBAL',
            'usage_limit' => 2,
        ]);
        PromoCodeUsage::create([
            'promo_code_id' => $exhaustedGlobal->id,
            'user_id' => $this->rider->id,
            'discount_amount' => 5.0,
            'status' => 'completed',
        ]);
        PromoCodeUsage::create([
            'promo_code_id' => $exhaustedGlobal->id,
            'user_id' => 99999, // other user
            'discount_amount' => 5.0,
            'status' => 'reserved',
        ]);

        // Exhausted per-user limit promo
        $exhaustedUser = $this->createPromo([
            'code' => 'EXHAUSTUSER',
            'per_user_limit' => 1,
        ]);
        PromoCodeUsage::create([
            'promo_code_id' => $exhaustedUser->id,
            'user_id' => $this->rider->id,
            'discount_amount' => 5.0,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/v1/promos');
        $response->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('code');

        $this->assertNotContains('INACTIVE', $codes);
        $this->assertNotContains('EXPIRED', $codes);
        $this->assertNotContains('DELETED', $codes);
        $this->assertNotContains('EXHAUSTGLOBAL', $codes);
        $this->assertNotContains('EXHAUSTUSER', $codes);
    }

    public function test_rider_promo_history_and_pagination()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);
        $promo = $this->createPromo(['code' => 'HISTORYCODE']);

        // Create usage history
        for ($i = 1; $i <= 5; $i++) {
            PromoCodeUsage::create([
                'promo_code_id' => $promo->id,
                'user_id' => $this->rider->id,
                'ride_id' => $i,
                'discount_amount' => 10.00,
                'status' => $i % 2 === 0 ? 'completed' : 'cancelled',
                'created_at' => Carbon::now()->subMinutes(10 - $i),
            ]);
        }

        $response = $this->getJson('/api/v1/promos/history?per_page=3');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['ride_id', 'promo_code', 'discount_amount', 'status', 'created_at']
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page']
            ]);

        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.per_page'));
        $this->assertEquals(1, $response->json('meta.current_page'));

        // Assert newest first (highest ride_id / id first)
        $this->assertEquals(5, $response->json('data.0.ride_id'));
        $this->assertEquals('HISTORYCODE', $response->json('data.0.promo_code'));
    }
}
