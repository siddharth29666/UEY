<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralValidationTest extends TestCase
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

    public function test_cannot_apply_referral_code_if_first_ride_completed()
    {
        // Mark first ride completed
        $this->riderUser->update(['first_ride_completed' => true]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot apply a referral code after completing your first ride.');

        app(ReferralService::class)->applyReferralCode($this->riderUser, $this->friendUser->referral_code);
    }

    public function test_cannot_apply_invalid_referral_code()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The referral code is invalid.');

        app(ReferralService::class)->applyReferralCode($this->riderUser, 'INVALID8');
    }
}
