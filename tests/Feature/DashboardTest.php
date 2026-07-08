<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
    }

    /**
     * Test admin dashboard endpoint.
     */
    public function test_admin_can_retrieve_dashboard_summary_and_charts()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Create some sample data
        User::create([
            'name' => 'John Rider',
            'phone' => '+447911111111',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'metrics' => [
                        'total_riders',
                        'total_drivers',
                        'active_drivers',
                        'online_drivers',
                        'today_rides',
                        'total_rides',
                        'pending_rides',
                        'accepted_rides',
                        'ongoing_rides',
                        'completed_rides',
                        'cancelled_rides',
                        'today_revenue',
                        'monthly_revenue',
                        'total_wallet_balance',
                        'pending_withdrawals',
                        'approved_withdrawals',
                        'total_reviews',
                        'average_driver_rating',
                        'average_rider_rating',
                    ],
                    'charts' => [
                        'daily_rides',
                        'monthly_revenue',
                        'driver_registrations',
                        'rider_registrations',
                        'ride_status_distribution',
                        'payment_method_distribution',
                        'wallet_topup_chart',
                    ],
                ]
            ]);
    }
}
