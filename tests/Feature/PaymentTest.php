<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected VehicleType $standardVehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Rider
        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Create Vehicle Type
        $this->standardVehicleType = VehicleType::create([
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

        // Configure platform commission
        config(['services.payments.commission_rate' => 10.0]);
    }

    protected function createDriver(string $name, string $phone): array
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $profile = DriverProfile::create([
            'user_id' => $user->id,
            'license_number' => 'DL-'.rand(100000, 999999),
            'license_expiry' => Carbon::now()->addYears(2),
            'is_online' => true,
            'rating' => 4.9,
            'acceptance_rate' => 98.0,
            'ontime_rate' => 99.0,
            'current_latitude' => 51.5074,
            'current_longitude' => -0.1278,
        ]);

        $vehicle = Vehicle::create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'make' => 'Toyota',
            'model' => 'Prius',
            'year' => 2021,
            'color' => 'White',
            'plate_number' => 'PL-'.rand(1000, 9999),
            'status' => VehicleStatus::APPROVED,
        ]);

        return compact('user', 'profile', 'vehicle');
    }

    protected function createInProgressRide(array $driver, PaymentMethod $method): Ride
    {
        return Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::IN_PROGRESS,
            'otp' => '123456',
            'estimated_distance' => 2.5,
            'estimated_duration' => 10,
            'estimated_fare' => 12.00,
            'payment_method' => $method,
            'payment_status' => 'pending',
        ]);
    }

    public function test_cash_payment_processing()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::CASH);

        $token = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/driver/rides/{$ride->id}/complete", [
            'actual_distance' => 5.0,
            'actual_duration' => 15,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'ride',
            'payment' => [
                'id',
                'payment_method',
                'payment_status',
            ],
        ]);

        // Verification Calculations
        // Base = 5.00. Per km = 1.50 * 5 = 7.50. Per min = 0.50 * 15 = 7.50. Total = 20.00
        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => RideStatus::COMPLETED->value,
            'actual_fare' => 20.00,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'ride_id' => $ride->id,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 20.00,
            'platform_commission' => 3.00, // 15% of 20.00
            'driver_earning' => 17.00,
            'total' => 20.00,
        ]);

        // Cash commission debited from driver wallet
        $driverWallet = $driver['user']->wallet;
        $this->assertNotNull($driverWallet);
        $this->assertEquals(-3.00, $driverWallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $driverWallet->id,
            'type' => 'debit',
            'amount' => 3.00,
        ]);
    }

    public function test_wallet_payment_processing()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::WALLET);

        // Fund rider wallet
        $riderWallet = $this->rider->wallet()->create(['balance' => 50.00]);

        $token = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/driver/rides/{$ride->id}/complete", [
            'actual_distance' => 5.0,
            'actual_duration' => 15,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'ride_id' => $ride->id,
            'payment_method' => 'wallet',
            'payment_status' => 'paid',
            'total' => 20.00,
        ]);

        $riderWallet->refresh();
        $this->assertEquals(30.00, $riderWallet->balance); // 50.00 - 20.00

        $driverWallet = $driver['user']->wallet;
        $this->assertNotNull($driverWallet);
        $this->assertEquals(17.00, $driverWallet->balance); // credited with earnings

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $riderWallet->id,
            'type' => 'debit',
            'amount' => 20.00,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $driverWallet->id,
            'type' => 'credit',
            'amount' => 17.00,
        ]);
    }

    public function test_wallet_payment_insufficient_funds()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::WALLET);

        // Rider has insufficient funds
        $riderWallet = $this->rider->wallet()->create(['balance' => 5.00]);

        $token = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/driver/rides/{$ride->id}/complete", [
            'actual_distance' => 5.0,
            'actual_duration' => 15,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Insufficient wallet balance.',
            'wallet_balance' => 5.00,
            'required_amount' => 20.00,
            'shortfall' => 15.00,
        ]);

        // Rollback asserts: Ride is still in progress
        $ride->refresh();
        $this->assertEquals(RideStatus::IN_PROGRESS, $ride->status);

        // Wallet balance untouched
        $riderWallet->refresh();
        $this->assertEquals(5.00, $riderWallet->balance);

        // Payment status failed or not paid
        $this->assertDatabaseHas('payments', [
            'ride_id' => $ride->id,
            'payment_status' => 'failed',
        ]);
    }

    public function test_view_payment_details()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::CASH);

        // Set completed state in database directly
        $ride->update([
            'status' => RideStatus::COMPLETED,
            'actual_distance' => 5.0,
            'actual_duration' => 15,
            'actual_fare' => 20.00,
            'completed_at' => now(),
            'payment_status' => 'paid',
        ]);

        $payment = Payment::create([
            'ride_id' => $ride->id,
            'rider_id' => $ride->rider_id,
            'driver_profile_id' => $ride->driver_profile_id,
            'payment_method' => PaymentMethod::CASH,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 20.00,
            'platform_commission' => 3.00,
            'driver_earning' => 17.00,
            'total' => 20.00,
            'transaction_reference' => 'PAY-20260704-000001',
            'paid_at' => now(),
        ]);

        // 1. Rider Access
        $riderToken = $this->rider->createToken('test', ['role:rider'])->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson("/api/v1/payments/{$ride->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'payment' => [
                'id',
                'ride_id',
                'payment_method',
                'payment_status',
                'total',
                'transaction_reference',
            ],
        ]);

        // 2. Driver Access
        $driverToken = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$driverToken}",
        ])->getJson("/api/v1/payments/{$ride->id}");

        $response->assertStatus(200);
    }

    public function test_unauthorized_view_payment_details()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::CASH);

        // Set completed state in database directly
        $ride->update([
            'status' => RideStatus::COMPLETED,
            'actual_distance' => 5.0,
            'actual_duration' => 15,
            'actual_fare' => 20.00,
            'completed_at' => now(),
            'payment_status' => 'paid',
        ]);

        $payment = Payment::create([
            'ride_id' => $ride->id,
            'rider_id' => $ride->rider_id,
            'driver_profile_id' => $ride->driver_profile_id,
            'payment_method' => PaymentMethod::CASH,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 20.00,
            'platform_commission' => 3.00,
            'driver_earning' => 17.00,
            'total' => 20.00,
            'transaction_reference' => 'PAY-20260704-000001',
            'paid_at' => now(),
        ]);

        // A separate rider attempts to access
        $maliciousUser = User::create([
            'name' => 'Hack Rider',
            'phone' => '+447933333333',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
        $maliciousToken = $maliciousUser->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$maliciousToken}",
        ])->getJson("/api/v1/payments/{$ride->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized.',
        ]);
    }

    public function test_view_invoice()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::CASH);

        // Set completed state in database directly
        $ride->update([
            'status' => RideStatus::COMPLETED,
            'actual_distance' => 5.0,
            'actual_duration' => 15,
            'actual_fare' => 20.00,
            'completed_at' => now(),
            'payment_status' => 'paid',
        ]);

        $payment = Payment::create([
            'ride_id' => $ride->id,
            'rider_id' => $ride->rider_id,
            'driver_profile_id' => $ride->driver_profile_id,
            'payment_method' => PaymentMethod::CASH,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 20.00,
            'platform_commission' => 3.00,
            'driver_earning' => 17.00,
            'total' => 20.00,
            'transaction_reference' => 'PAY-20260704-000001',
            'paid_at' => now(),
        ]);

        $riderToken = $this->rider->createToken('test', ['role:rider'])->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson("/api/v1/payments/invoice/{$ride->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'invoice' => [
                'ride_id',
                'pickup_address',
                'destination_address',
                'distance',
                'duration',
                'payment_method',
                'payment_status',
                'transaction_reference',
                'completed_at',
                'rider' => ['id', 'name'],
                'driver' => ['id', 'name'],
                'fare_breakdown' => [
                    'subtotal',
                    'tax',
                    'discount',
                    'platform_commission',
                    'driver_earning',
                    'total',
                ],
                'paid_at',
            ],
        ]);
    }

    public function test_payment_history()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::CASH);

        // Set completed state in database directly
        $ride->update([
            'status' => RideStatus::COMPLETED,
            'actual_distance' => 5.0,
            'actual_duration' => 15,
            'actual_fare' => 20.00,
            'completed_at' => now(),
            'payment_status' => 'paid',
        ]);

        $payment = Payment::create([
            'ride_id' => $ride->id,
            'rider_id' => $ride->rider_id,
            'driver_profile_id' => $ride->driver_profile_id,
            'payment_method' => PaymentMethod::CASH,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 20.00,
            'platform_commission' => 3.00,
            'driver_earning' => 17.00,
            'total' => 20.00,
            'transaction_reference' => 'PAY-20260704-000001',
            'paid_at' => now(),
        ]);

        $riderToken = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // 1. Basic check and Pagination Metadata
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson('/api/v1/payments/history?per_page=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'payments',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
        $response->assertJsonFragment([
            'current_page' => 1,
            'per_page' => 1,
            'total' => 1,
            'last_page' => 1,
        ]);

        // 2. Query Filters: status
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson('/api/v1/payments/history?status=paid');
        $response->assertJsonCount(1, 'payments');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson('/api/v1/payments/history?status=failed');
        $response->assertJsonCount(0, 'payments');

        // 3. Query Filters: payment_method
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson('/api/v1/payments/history?payment_method=cash');
        $response->assertJsonCount(1, 'payments');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson('/api/v1/payments/history?payment_method=wallet');
        $response->assertJsonCount(0, 'payments');

        // 4. Query Filters: from & to
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::now()->subDay()->format('Y-m-d');
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson("/api/v1/payments/history?from={$yesterday}&to={$tomorrow}");
        $response->assertJsonCount(1, 'payments');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$riderToken}",
        ])->getJson("/api/v1/payments/history?from={$tomorrow}");
        $response->assertJsonCount(0, 'payments');
    }

    public function test_payment_processing_idempotency()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createInProgressRide($driver, PaymentMethod::CASH);

        $driverToken = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;

        // First completion (completes + processes payment)
        $this->withHeaders([
            'Authorization' => "Bearer {$driverToken}",
        ])->postJson("/api/v1/driver/rides/{$ride->id}/complete", [
            'actual_distance' => 5.0,
            'actual_duration' => 15,
        ]);

        $this->assertEquals(1, Payment::where('ride_id', $ride->id)->count());
        $driverWallet = $driver['user']->wallet;
        $this->assertEquals(-3.00, $driverWallet->balance);

        // Call payment service directly a second time
        $paymentService = app(PaymentService::class);
        $paymentService->processPaymentForRide($ride);

        // Assert no new payments created and wallet is not double debited
        $this->assertEquals(1, Payment::where('ride_id', $ride->id)->count());
        $driverWallet->refresh();
        $this->assertEquals(-3.00, $driverWallet->balance);
    }
}
