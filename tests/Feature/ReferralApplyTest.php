<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralApplyTest extends TestCase
{
    use RefreshDatabase;

    protected User $riderUser;

    protected User $friendUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->riderUser = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->friendUser = User::create([
            'name' => 'Bob Friend',
            'phone' => '+447922222222',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_apply_valid_referral_code()
    {
        Sanctum::actingAs($this->riderUser);

        $response = $this->postJson('/api/v1/referrals/apply', [
            'referral_code' => $this->friendUser->referral_code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Referral code has been successfully applied to your account.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->riderUser->id,
            'referred_by' => $this->friendUser->id,
        ]);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $this->friendUser->id,
            'referred_user_id' => $this->riderUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_self_refer()
    {
        Sanctum::actingAs($this->riderUser);

        $response = $this->postJson('/api/v1/referrals/apply', [
            'referral_code' => $this->riderUser->referral_code,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot refer yourself.',
            ]);
    }

    public function test_cannot_apply_referral_twice()
    {
        Sanctum::actingAs($this->riderUser);

        // Apply first time
        $this->postJson('/api/v1/referrals/apply', [
            'referral_code' => $this->friendUser->referral_code,
        ]);

        // Try second time
        $response = $this->postJson('/api/v1/referrals/apply', [
            'referral_code' => $this->friendUser->referral_code,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You have already applied a referral code.',
            ]);
    }
}
