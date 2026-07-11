<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoritePlaceValidationTest extends TestCase
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

    public function test_favorite_place_validation_fails_with_invalid_type()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'invalid_type',
            'label' => 'Gym',
            'address' => '10 Park Avenue',
            'latitude' => 51.5200,
            'longitude' => -0.1500,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_favorite_place_validation_fails_with_missing_address()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'saved',
            'label' => 'Gym',
            'latitude' => 51.5200,
            'longitude' => -0.1500,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }
}
