<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRideTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected Ride $ride;
    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447999999999',
            'email' => 'alice.admin@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->rider = User::create([
            'name' => 'John Rider',
            'phone' => '+447911111111',
            'email' => 'john.rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->vehicleType = VehicleType::create([
            'name' => 'Standard',
            'capacity' => 4,
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.50,
            'minimum_fare' => 7.00,
            'commission_percentage' => 20.00,
            'icon_url' => 'https://example.com/standard.png',
            'active' => true,
        ]);

        $this->ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'London Eye, London',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent\'s Park, London',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 2.50,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Test list rides.
     */
    public function test_admin_can_list_rides_with_filters()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/rides?status=pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'rides');
    }

    /**
     * Test get ride details.
     */
    public function test_admin_can_get_ride_details()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson("/api/v1/admin/rides/{$this->ride->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('ride.id', $this->ride->id);
    }

    /**
     * Test admin can cancel ride.
     */
    public function test_admin_can_cancel_ride()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/rides/{$this->ride->id}/cancel", [
            'cancel_reason' => 'Admin cancellation test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(RideStatus::CANCELLED, $this->ride->refresh()->status);
    }

    /**
     * Test admin can refund ride payment.
     */
    public function test_admin_can_refund_ride_payment()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Set payment for ride
        $payment = Payment::create([
            'ride_id' => $this->ride->id,
            'rider_id' => $this->rider->id,
            'payment_method' => 'wallet',
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 10.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'platform_commission' => 2.00,
            'driver_earning' => 8.00,
            'total' => 10.00,
            'paid_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/admin/rides/{$this->ride->id}/refund");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(PaymentStatus::REFUNDED, $payment->refresh()->payment_status);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->rider->id,
            'balance' => 10.00,
        ]);
    }

    /**
     * Test get ride timeline.
     */
    public function test_admin_can_get_ride_timeline()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson("/api/v1/admin/rides/{$this->ride->id}/timeline");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'timeline']);
    }
}
