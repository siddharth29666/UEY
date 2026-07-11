<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoritePlaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_basic_favorite_places_features()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'home',
            'label' => 'Sweet Home',
            'address' => 'Baker St 221B',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);
        $response->assertStatus(201);

        $response = $this->getJson('/api/v1/favorite-places');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }
}
