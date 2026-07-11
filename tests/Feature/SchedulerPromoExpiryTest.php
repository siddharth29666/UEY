<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerPromoExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected PromoCode $expiredPromo;

    protected PromoCode $activePromo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create expired active promo code
        $this->expiredPromo = PromoCode::create([
            'code' => 'EXPIRED10',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // Create active promo code not expired
        $this->activePromo = PromoCode::create([
            'code' => 'ACTIVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'expires_at' => now()->addDays(5),
            'is_active' => true,
        ]);
    }

    public function test_expire_promo_codes_command()
    {
        // Run command
        Artisan::call('app:expire-promo-codes');

        $this->expiredPromo->refresh();
        $this->assertFalse($this->expiredPromo->is_active);

        $this->activePromo->refresh();
        $this->assertTrue($this->activePromo->is_active);

        // Assert scheduler log exists
        $this->assertDatabaseHas('scheduler_logs', [
            'command' => 'app:expire-promo-codes',
            'status' => 'success',
        ]);
    }
}
