<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected PromoCode $promoCode;

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

        $this->promoCode = PromoCode::create([
            'code' => 'HELLO50',
            'discount_type' => 'percentage',
            'discount_value' => 50.00,
            'expires_at' => now()->addDays(10),
            'usage_limit' => 100,
            'per_user_limit' => 1,
            'min_fare' => 10.00,
            'is_active' => true,
        ]);
    }

    /**
     * Test list promo codes.
     */
    public function test_admin_can_list_promo_codes()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/promo-codes?search=HELLO');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'promo_codes');
    }

    /**
     * Test create promo code.
     */
    public function test_admin_can_create_promo_code()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson('/api/v1/admin/promo-codes', [
            'code' => 'WELCOME10',
            'discount_type' => 'flat',
            'discount_value' => 10.00,
            'expires_at' => now()->addDays(5)->toDateTimeString(),
            'usage_limit' => 200,
            'per_user_limit' => 1,
            'min_fare' => 15.00,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('promo_code.code', 'WELCOME10');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'WELCOME10',
        ]);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'promo_create',
            'module' => 'promo_codes',
        ]);
    }

    /**
     * Test update promo code.
     */
    public function test_admin_can_update_promo_code()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->putJson("/api/v1/admin/promo-codes/{$this->promoCode->id}", [
            'discount_value' => 40.00,
            'discount_type' => 'percentage',
            'expires_at' => now()->addDays(5)->toDateTimeString(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('promo_code.discount_value', 40);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'promo_update',
            'module' => 'promo_codes',
        ]);
    }

    /**
     * Test delete promo code.
     */
    public function test_admin_can_delete_promo_code()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->deleteJson("/api/v1/admin/promo-codes/{$this->promoCode->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('promo_codes', [
            'id' => $this->promoCode->id,
        ]);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'promo_delete',
            'module' => 'promo_codes',
        ]);
    }
}
