<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\FavoritePlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoritePlaceDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice.rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_rider_cannot_save_multiple_homes()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // First Home
        FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'home',
            'label' => 'Home',
            'address' => '221 Baker Street',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);

        // Second Home
        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'home',
            'label' => 'My Home 2',
            'address' => '222 Baker Street',
            'latitude' => 51.5240, // More than 20m away to bypass proximity check, but type unique check should trigger
            'longitude' => -0.1600,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_rider_cannot_save_multiple_works()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // First Work
        FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'work',
            'label' => 'Office',
            'address' => '1 Canada Square',
            'latitude' => 51.5033,
            'longitude' => -0.0195,
        ]);

        // Second Work
        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'work',
            'label' => 'My Office 2',
            'address' => '2 Canada Square',
            'latitude' => 51.5040,
            'longitude' => -0.0210,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_rider_cannot_save_location_within_20_meters()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // First Place
        FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'saved',
            'label' => 'Gym',
            'address' => '10 Park Avenue',
            'latitude' => 51.5200,
            'longitude' => -0.1500,
        ]);

        // Second Place - 10 meters away (approx)
        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'saved',
            'label' => 'Second Gym',
            'address' => '12 Park Avenue',
            'latitude' => 51.52005, // very close latitude
            'longitude' => -0.15005, // very close longitude
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordinates']);
    }
}
