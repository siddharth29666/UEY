<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->rider = User::factory()->create([
            'role' => 'rider',
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->driver = User::factory()->create([
            'role' => 'driver',
            'status' => UserStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_can_access_daily_revenue_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily?date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'daily')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'date',
                    'summary' => [
                        'total_rides',
                        'completed_rides',
                        'cancelled_rides',
                        'gross_revenue',
                        'platform_commission',
                        'driver_earnings',
                        'promo_discounts',
                        'refunds',
                        'net_revenue',
                    ],
                ],
            ]);
    }

    public function test_admin_can_access_weekly_revenue_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/weekly?start_date=2026-07-20&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'weekly');
    }

    public function test_admin_can_access_monthly_revenue_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/monthly?year=2026&month=7');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'monthly');
    }

    public function test_admin_can_access_custom_date_range_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/custom?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'custom');
    }

    public function test_admin_can_access_platform_commission_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/platform-commission?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'start_date',
                    'end_date',
                    'summary' => [
                        'total_completed_rides',
                        'gross_ride_amount',
                        'platform_commission',
                        'driver_earnings',
                        'effective_commission_rate',
                    ],
                    'date_breakdown',
                ],
            ]);
    }

    public function test_admin_can_access_driver_earnings_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/driver-earnings?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_access_promo_discount_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/promo-discounts?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'promo_breakdown',
                    'date_breakdown',
                ],
            ]);
    }

    public function test_admin_can_access_referral_reward_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/referral-rewards?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'date_breakdown',
                ],
            ]);
    }

    public function test_admin_can_access_wallet_statement_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/wallet-statement?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'transactions',
                ],
            ]);
    }

    public function test_admin_can_access_credit_debit_history_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/wallet-credit-debit?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_access_cashout_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/cashouts?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_access_ledger_report(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/ledger?start_date=2026-07-01&end_date=2026-07-26');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_export_revenue_report_as_csv(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily?date=2026-07-26&export=csv');

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="revenue_report_', $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_export_wallet_statement_as_csv(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/wallet-statement?export=csv');

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="wallet_statement_', $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_export_daily_revenue_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily?date=2026-07-26&export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="revenue_report_', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertNotEmpty($response->getContent());
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_weekly_revenue_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/weekly?start_date=2026-07-20&end_date=2026-07-26&export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_monthly_revenue_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/monthly?year=2026&month=7&export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_custom_revenue_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/custom?start_date=2026-07-01&end_date=2026-07-26&export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_platform_commission_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/platform-commission?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_driver_earnings_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/driver-earnings?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_promo_discount_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/promo-discounts?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_referral_reward_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/referral-rewards?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_wallet_statement_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/wallet-statement?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_credit_debit_history_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/wallet-credit-debit?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_cashout_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/cashouts?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_admin_can_export_ledger_report_as_excel(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/ledger?export=excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_invalid_export_format_returns_422(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily?export=pdf');

        $response->assertStatus(422);
    }

    public function test_rider_cannot_export_excel_reports(): void
    {
        $response = $this->actingAs($this->rider, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily?export=excel');

        $response->assertStatus(403);
    }

    public function test_driver_cannot_export_excel_reports(): void
    {
        $response = $this->actingAs($this->driver, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily?export=excel');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_export_excel_reports(): void
    {
        $response = $this->getJson('/api/v1/admin/reports/revenue/daily?export=excel');

        $response->assertStatus(401);
    }

    public function test_invalid_date_range_returns_422(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/custom?start_date=2026-07-26&end_date=2026-07-20');

        $response->assertStatus(422);
    }

    public function test_rider_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->rider, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily');

        $response->assertStatus(403);
    }

    public function test_driver_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->driver, 'sanctum')
            ->getJson('/api/v1/admin/reports/revenue/daily');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/v1/admin/reports/revenue/daily');

        $response->assertStatus(401);
    }

    public function test_wallet_statement_csv_export_has_sequential_row_numbers(): void
    {
        // Create wallet transaction data
        $wallet = \App\Models\Wallet::create([
            'user_id' => $this->rider->id,
            'balance' => 500.00,
            'currency' => 'USD',
        ]);

        \App\Models\WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'transaction_type' => 'topup',
            'amount' => 100.00,
            'balance_before' => 0.00,
            'balance_after' => 100.00,
            'status' => 'completed',
            'reference' => 'TXN999888',
        ]);

        \App\Models\WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'transaction_type' => 'ride_payment',
            'amount' => 20.00,
            'balance_before' => 100.00,
            'balance_after' => 80.00,
            'status' => 'completed',
            'reference' => 'TXN999889',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/wallet-statement?export=csv');

        $response->assertStatus(200);

        $csvContent = $response->streamedContent();
        $lines = explode("\n", trim($csvContent));

        // Header line must start with No
        $this->assertStringStartsWith('"No",', $lines[0]);

        // First row must start with "1", second row with "2"
        $this->assertStringStartsWith('"1",', $lines[1]);
        $this->assertStringStartsWith('"2",', $lines[2]);
    }

    public function test_driver_earnings_csv_export_has_sequential_row_numbers(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/driver-earnings?export=csv');

        $response->assertStatus(200);

        $csvContent = $response->streamedContent();
        $lines = explode("\n", trim($csvContent));

        // Header line must start with No
        $this->assertStringStartsWith('"No",', $lines[0]);

        if (count($lines) > 1 && !empty($lines[1])) {
            $this->assertStringStartsWith('"1",', $lines[1]);
        }
    }
}
