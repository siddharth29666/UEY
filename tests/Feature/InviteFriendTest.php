<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InviteFriendTest extends TestCase
{
    use RefreshDatabase;

    protected User $riderUser;

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
    }

    public function test_invite_friend_generates_share_details()
    {
        Sanctum::actingAs($this->riderUser);

        $response = $this->postJson('/api/v1/referrals/invite', [
            'phone' => '+447922222222',
        ]);

        $code = $this->riderUser->referral_code;

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'referral_code' => $code,
                'invitation_message' => "Use my referral code {$code} to sign up and get a bonus on UEY Premium Mobility! Download here: https://uey.mobility/download?code={$code}",
                'share_url' => "https://uey.mobility/download?code={$code}",
            ]);
    }
}
