<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\FavoritePlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoritePlaceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected User $otherRider;

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

        $this->otherRider = User::create([
            'name' => 'Bob Rider',
            'phone' => '+447999999902',
            'email' => 'bob.rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_rider_can_create_favorite_place()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson('/api/v1/favorite-places', [
            'type' => 'saved',
            'label' => 'Gym',
            'nickname' => 'Fitness Club',
            'google_place_id' => 'place_id_gym_123',
            'address' => '10 Park Avenue',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
            'is_default' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'user_id', 'type', 'label', 'nickname', 'google_place_id', 'address', 'latitude', 'longitude', 'is_default',
                ],
            ]);

        $this->assertDatabaseHas('favorite_places', [
            'user_id' => $this->rider->id,
            'type' => 'saved',
            'label' => 'Gym',
            'google_place_id' => 'place_id_gym_123',
        ]);
    }

    public function test_rider_can_retrieve_default_places()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // Create Home & Work
        FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'home',
            'label' => 'Home',
            'address' => '221 Baker Street',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);

        FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'work',
            'label' => 'Office',
            'address' => '1 Canada Square',
            'latitude' => 51.5033,
            'longitude' => -0.0195,
        ]);

        // Create Saved
        FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'saved',
            'label' => 'Gym',
            'address' => '10 Park Avenue',
            'latitude' => 51.5200,
            'longitude' => -0.1500,
        ]);

        // Retrieve defaults near a specific location
        $response = $this->getJson('/api/v1/favorite-places/default?latitude=51.5210&longitude=-0.1510');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('home.address', '221 Baker Street')
            ->assertJsonPath('work.address', '1 Canada Square')
            ->assertJsonPath('nearest_saved_place.label', 'Gym');
    }

    public function test_rider_can_update_favorite_place()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $place = FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'saved',
            'label' => 'Gym',
            'address' => '10 Park Avenue',
            'latitude' => 51.5200,
            'longitude' => -0.1500,
        ]);

        $response = $this->putJson("/api/v1/favorite-places/{$place->id}", [
            'label' => 'Workout Gym',
            'nickname' => 'My Gym',
            'address' => '20 Park Avenue',
            'latitude' => 51.5205,
            'longitude' => -0.1505,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'Workout Gym')
            ->assertJsonPath('data.nickname', 'My Gym')
            ->assertJsonPath('data.address', '20 Park Avenue');

        $this->assertDatabaseHas('favorite_places', [
            'id' => $place->id,
            'label' => 'Workout Gym',
            'address' => '20 Park Avenue',
        ]);
    }

    public function test_rider_can_delete_favorite_place()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $place = FavoritePlace::create([
            'user_id' => $this->rider->id,
            'type' => 'saved',
            'label' => 'Gym',
            'address' => '10 Park Avenue',
            'latitude' => 51.5200,
            'longitude' => -0.1500,
        ]);

        $response = $this->deleteJson("/api/v1/favorite-places/{$place->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('favorite_places', ['id' => $place->id]);
    }
}
