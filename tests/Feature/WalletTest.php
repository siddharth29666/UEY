<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WithdrawalStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\StripeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;
    protected Wallet $riderWallet;
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

        $this->riderWallet = Wallet::create([
            'user_id' => $this->rider->id,
            'balance' => 0.00,
            'currency' => 'USD',
            'status' => 'active',
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
            'license_number' => 'DL-' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2),
            'is_online' => true,
            'rating' => 5.00,
            'total_reviews' => 0,
            'experience_years' => 3,
        ]);

        $vehicle = Vehicle::create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'make' => 'Toyota',
            'model' => 'Prius',
            'year' => 2021,
            'color' => 'White',
            'plate_number' => 'PL-' . rand(1000, 9999),
            'status' => VehicleStatus::APPROVED,
        ]);

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 0.00,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        return compact('user', 'profile', 'vehicle', 'wallet');
    }

    public function test_view_wallet_balance()
    {
        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/v1/wallet');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'wallet' => [
                'balance' => 0.00,
                'currency' => 'USD',
                'last_transaction' => null,
            ]
        ]);
    }

    public function test_topup_intent_creation()
    {
        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // Mock Stripe
        $stripeMock = $this->mock(StripeService::class);
        $intentFake = \Stripe\PaymentIntent::constructFrom(['id' => 'pi_test_123']);
        $stripeMock->shouldReceive('createPaymentIntent')
            ->once()
            ->with(50.00, 'USD', \Mockery::any())
            ->andReturn($intentFake);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/v1/wallet/top-up', [
            'amount' => 50.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'payment_intent' => 'pi_test_123',
            'amount' => 50.00,
            'currency' => 'USD',
            'wallet_topup' => [
                'amount' => 50.00,
                'stripe_payment_intent' => 'pi_test_123',
                'payment_status' => 'pending',
            ]
        ]);

        $this->assertDatabaseHas('wallet_topups', [
            'stripe_payment_intent' => 'pi_test_123',
            'amount' => 50.00,
            'payment_status' => 'pending',
        ]);
    }

    public function test_successful_topup_via_webhook()
    {
        // 1. Create a pending topup
        $topup = WalletTopup::create([
            'wallet_id' => $this->riderWallet->id,
            'amount' => 100.00,
            'stripe_payment_intent' => 'pi_webhook_success',
            'payment_status' => 'pending',
        ]);

        // 2. Call Webhook with payment success event
        $payload = [
            'id' => 'evt_test_success_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_success',
                    'amount' => 10000,
                    'currency' => 'usd',
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/stripe/webhook', $payload);

        $response->assertStatus(200);

        // Assert topup is completed
        $topup->refresh();
        $this->assertEquals('completed', $topup->payment_status);

        // Assert rider wallet credited
        $this->riderWallet->refresh();
        $this->assertEquals(100.00, $this->riderWallet->balance);

        // Assert Wallet Transaction recorded with ledger balances
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->riderWallet->id,
            'type' => 'credit',
            'transaction_type' => WalletTransactionType::TOP_UP->value,
            'amount' => 100.00,
            'balance_before' => 0.00,
            'balance_after' => 100.00,
        ]);
    }

    public function test_failed_topup_via_webhook()
    {
        $topup = WalletTopup::create([
            'wallet_id' => $this->riderWallet->id,
            'amount' => 100.00,
            'stripe_payment_intent' => 'pi_webhook_failed',
            'payment_status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_test_failed_123',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_failed',
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/stripe/webhook', $payload);

        $response->assertStatus(200);

        $topup->refresh();
        $this->assertEquals('failed', $topup->payment_status);

        $this->riderWallet->refresh();
        $this->assertEquals(0.00, $this->riderWallet->balance);
    }

    public function test_duplicate_webhook_ignored()
    {
        $topup = WalletTopup::create([
            'wallet_id' => $this->riderWallet->id,
            'amount' => 100.00,
            'stripe_payment_intent' => 'pi_webhook_dup',
            'payment_status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_dup_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_dup',
                ]
            ]
        ];

        // First call
        $this->postJson('/api/v1/stripe/webhook', $payload)->assertStatus(200);

        // Second call
        $this->postJson('/api/v1/stripe/webhook', $payload)->assertStatus(200);

        // Balance should be exactly 100.00, NOT 200.00
        $this->riderWallet->refresh();
        $this->assertEquals(100.00, $this->riderWallet->balance);
    }

    public function test_invalid_webhook_signature_rejected()
    {
        // When Stripe-Signature header is present, it must trigger verifyWebhook which throws SignatureVerificationException
        $stripeMock = $this->mock(StripeService::class);
        $stripeMock->shouldReceive('verifyWebhook')
            ->andThrow(\Stripe\Exception\SignatureVerificationException::factory('Invalid signature', 'sig_header'));

        $response = $this->withHeaders([
            'Stripe-Signature' => 'invalid_sig'
        ])->postJson('/api/v1/stripe/webhook', []);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid signature.'
        ]);
    }

    public function test_withdrawal_request_and_validation()
    {
        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // Give balance first
        $this->riderWallet->update(['balance' => 200.00]);

        // 1. Valid request
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/v1/wallet/withdraw', [
            'amount' => 50.00,
            'bank_account_id' => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully.',
            'withdrawal' => [
                'amount' => 50.00,
                'status' => 'pending',
                'bank_account_id' => 1,
            ]
        ]);

        $this->assertDatabaseHas('withdrawal_requests', [
            'wallet_id' => $this->riderWallet->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        // 2. Insufficient balance withdrawal check
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/v1/wallet/withdraw', [
            'amount' => 300.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Withdrawal amount exceeds wallet balance.'
        ]);
    }

    public function test_admin_approves_withdrawal_request()
    {
        // Pre-fund wallet
        $this->riderWallet->update(['balance' => 200.00]);

        $withdrawal = WithdrawalRequest::create([
            'wallet_id' => $this->riderWallet->id,
            'amount' => 50.00,
            'status' => WithdrawalStatus::PENDING,
        ]);

        $walletService = app(WalletService::class);
        $walletService->approveWithdrawal($withdrawal, 'Approved by admin');

        $withdrawal->refresh();
        $this->assertEquals(WithdrawalStatus::COMPLETED, $withdrawal->status);
        $this->assertEquals('Approved by admin', $withdrawal->admin_note);
        $this->assertNotNull($withdrawal->processed_at);

        // Wallet debited and ledger updated
        $this->riderWallet->refresh();
        $this->assertEquals(150.00, $this->riderWallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->riderWallet->id,
            'type' => 'debit',
            'transaction_type' => WalletTransactionType::WITHDRAWAL->value,
            'amount' => 50.00,
            'balance_before' => 200.00,
            'balance_after' => 150.00,
        ]);
    }

    public function test_wallet_cannot_go_below_zero()
    {
        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // Try to withdraw when balance is 0
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/v1/wallet/withdraw', [
            'amount' => 50.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_transaction_history_and_pagination()
    {
        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // Seed 3 transactions
        $walletService = app(WalletService::class);
        for ($i = 1; $i <= 3; $i++) {
            $walletService->credit(
                $this->riderWallet,
                10.00,
                WalletTransactionType::ADMIN_CREDIT,
                'ref_' . $i,
                'Admin credit #' . $i
            );
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/v1/wallet/transactions?per_page=2&sort=latest');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'transactions');
        $response->assertJsonStructure([
            'success',
            'transactions',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['first', 'last', 'prev', 'next']
        ]);

        // Sorting: latest first (ID 3, then ID 2)
        $this->assertEquals(30.00, $response->json('transactions.0.balance_after'));
    }

    public function test_ride_payment_from_wallet()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');

        // Fund rider wallet
        $this->riderWallet->update(['balance' => 50.00]);

        // Complete the ride and charge wallet
        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::COMPLETED,
            'otp' => '123456',
            'estimated_distance' => 2.5,
            'estimated_duration' => 10,
            'estimated_fare' => 12.00,
            'payment_method' => \App\Enums\PaymentMethod::WALLET,
            'actual_fare' => 20.00,
            'completed_at' => now(),
        ]);

        $paymentService = app(\App\Services\PaymentService::class);
        $paymentService->processPaymentForRide($ride);

        // Rider debited
        $this->riderWallet->refresh();
        $this->assertEquals(30.00, $this->riderWallet->balance);

        // Driver credited
        $driver['wallet']->refresh();
        $this->assertEquals(17.00, $driver['wallet']->balance); // 20.00 - 15% commission
    }
}
