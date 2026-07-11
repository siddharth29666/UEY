<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $referrer;

    protected User $invitee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrer = User::create([
            'name' => 'Alice Referrer',
            'phone' => '+447911111111',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->invitee = User::create([
            'name' => 'Bob Invitee',
            'phone' => '+447922222222',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Apply referral code
        app(ReferralService::class)->applyReferralCode($this->invitee, $this->referrer->referral_code);
    }

    public function test_get_referrals_history()
    {
        Sanctum::actingAs($this->referrer);

        $response = $this->getJson('/api/v1/referrals/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'referrals' => [
                    '*' => [
                        'id',
                        'referred_user' => ['id', 'name', 'phone', 'email'],
                        'status',
                        'first_ride_completed',
                    ],
                ],
            ]);
    }

    public function test_get_referral_summary()
    {
        Sanctum::actingAs($this->referrer);

        $response = $this->getJson('/api/v1/referrals/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_referred' => 1,
                'completed_referrals' => 0,
                'pending_referrals' => 1,
                'total_earnings' => 0.0,
            ]);
    }

    public function test_get_referral_earnings_history()
    {
        Sanctum::actingAs($this->referrer);

        $response = $this->getJson('/api/v1/referrals/earnings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'earnings',
            ]);
    }
}
