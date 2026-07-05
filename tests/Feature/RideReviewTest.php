<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Tests\TestCase;

class RideReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;
    protected VehicleType $standardVehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Rider
        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
            'rating' => 5.00,
            'total_reviews' => 0,
        ]);

        // Create Vehicle Type
        $this->standardVehicleType = VehicleType::create([
            'name' => 'Standard',
            'capacity' => 4,
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.50,
            'minimum_fare' => 7.00,
            'commission_percentage' => 20.00,
            'icon_url' => 'https://example.com/standard.png',
            'active' => true,
        ]);
    }

    protected function createDriver(string $name, string $phone): array
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
            'rating' => 5.00,
            'total_reviews' => 0,
        ]);

        $profile = DriverProfile::create([
            'user_id' => $user->id,
            'license_number' => 'DL-' . rand(100000, 999999),
            'license_expiry' => Carbon::now()->addYears(2),
            'is_online' => true,
            'rating' => 5.00,
            'total_reviews' => 0,
            'experience_years' => 3,
        ]);

        $vehicle = Vehicle::create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'make' => 'Toyota',
            'model' => 'Prius',
            'year' => 2021,
            'color' => 'White',
            'plate_number' => 'PL-' . rand(1000, 9999),
            'status' => VehicleStatus::APPROVED,
        ]);

        return compact('user', 'profile', 'vehicle');
    }

    protected function createRide(array $driver, RideStatus $status): Ride
    {
        return Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => $status,
            'otp' => '123456',
            'estimated_distance' => 2.5,
            'estimated_duration' => 10,
            'estimated_fare' => 12.00,
            'actual_fare' => $status === RideStatus::COMPLETED ? 20.00 : null,
            'completed_at' => $status === RideStatus::COMPLETED ? now() : null,
        ]);
    }

    public function test_rider_can_rate_completed_ride()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // Driver starts with rating = 5.00, total_reviews = 0
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 4,
            'review' => 'Good driver but car was slightly dusty.',
            'review_tags' => ['polite'],
            'is_anonymous' => false,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'review' => [
                'ride_id' => $ride->id,
                'reviewer_id' => $this->rider->id,
                'reviewer_name' => 'Alice Rider',
                'reviewee_id' => $driver['user']->id,
                'rating' => 4,
                'review' => 'Good driver but car was slightly dusty.',
                'review_tags' => ['polite'],
                'is_anonymous' => false,
            ],
            'reviewee_stats' => [
                'average_rating' => 4.0, // (5.00 * 0 + 4) / 1 = 4.0
                'total_reviews' => 1
            ]
        ]);

        // Assert database values
        $this->assertDatabaseHas('ride_reviews', [
            'ride_id' => $ride->id,
            'reviewer_id' => $this->rider->id,
            'rating' => 4,
        ]);

        $driver['profile']->refresh();
        $this->assertEquals(4.00, $driver['profile']->rating);
        $this->assertEquals(1, $driver['profile']->total_reviews);
    }

    public function test_driver_can_rate_completed_ride()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        $token = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;

        // Rider starts with rating = 5.00, total = 0
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 3,
            'review' => 'Rider kept me waiting.',
            'is_anonymous' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'review' => [
                'ride_id' => $ride->id,
                'reviewer_id' => $driver['user']->id, // Not null anymore
                'reviewer_name' => 'Anonymous', // Hidden in UI, so "Anonymous"
                'reviewee_id' => $this->rider->id,
                'rating' => 3,
                'is_anonymous' => true,
            ],
            'reviewee_stats' => [
                'average_rating' => 3.0, // (5.00 * 0 + 3) / 1 = 3.0
                'total_reviews' => 1
            ]
        ]);

        $this->rider->refresh();
        $this->assertEquals(3.00, $this->rider->rating);
        $this->assertEquals(1, $this->rider->total_reviews);
    }

    public function test_cannot_rate_in_progress_ride()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::IN_PROGRESS);

        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ride']);
    }

    public function test_cannot_rate_twice()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        // Submit first
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/rides/{$ride->id}/review", ['rating' => 5]);

        // Try duplicate
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/rides/{$ride->id}/review", ['rating' => 4]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['review']);
    }

    public function test_unauthorized_user_cannot_rate_ride()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        // A third user
        $stranger = User::create([
            'name' => 'Charlie Stranger',
            'phone' => '+447933333333',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
        $token = $stranger->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 5,
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_rating_values_rejected()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 6, // Invalid > 5
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rating']);
    }

    public function test_soft_deleted_users_cannot_review()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        // Soft delete the rider
        $this->rider->delete();

        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 5,
        ]);

        // Sanctum will reject soft-deleted token auth or service blocks
        $response->assertStatus(401);
    }

    public function test_participants_can_view_ride_reviews()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $ride = $this->createRide($driver, RideStatus::COMPLETED);

        // Insert reviews directly in DB
        RideReview::create([
            'ride_id' => $ride->id,
            'reviewer_id' => $this->rider->id,
            'reviewee_id' => $driver['user']->id,
            'rating' => 5,
            'review' => 'Excellent driver!',
            'is_anonymous' => false,
        ]);

        RideReview::create([
            'ride_id' => $ride->id,
            'reviewer_id' => $driver['user']->id,
            'reviewee_id' => $this->rider->id,
            'rating' => 4,
            'review' => 'Great rider.',
            'is_anonymous' => true,
        ]);

        $token = $this->rider->createToken('test', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson("/api/v1/rides/{$ride->id}/review");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'rider_review' => ['id', 'rating', 'review', 'is_anonymous', 'reviewer_id', 'reviewer_name'],
            'driver_review' => ['id', 'rating', 'review', 'is_anonymous', 'reviewer_id', 'reviewer_name']
        ]);
        $response->assertJsonFragment([
            'reviewer_id' => $driver['user']->id,
            'reviewer_name' => 'Anonymous',
        ]);
    }

    public function test_driver_reviews_listing_pagination_and_sorting()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');

        // Create reviews directly
        // We will make review #2 anonymous
        for ($i = 1; $i <= 3; $i++) {
            $ride = $this->createRide($driver, RideStatus::COMPLETED);
            RideReview::create([
                'ride_id' => $ride->id,
                'reviewer_id' => $this->rider->id,
                'reviewee_id' => $driver['user']->id,
                'rating' => $i, // ratings: 1, 2, 3
                'review' => "Review #{$i}",
                'review_tags' => ['polite', "tag_{$i}"],
                'is_anonymous' => $i === 2, // review 2 is anonymous
                'created_at' => Carbon::now()->addMinutes($i),
            ]);

            // Update cached profile values incrementally (simulating service behavior)
            $driver['profile']->refresh();
            $currentTotal = $driver['profile']->total_reviews;
            $currentAvg = (float) $driver['profile']->rating;
            $newTotal = $currentTotal + 1;
            $newAvg = (($currentAvg * $currentTotal) + $i) / $newTotal;
            $driver['profile']->update([
                'rating' => round($newAvg, 2),
                'total_reviews' => $newTotal,
            ]);
        }

        $token = $driver['user']->createToken('test', ['role:driver'])->plainTextToken;

        // 1. Check pagination structure: page=1, per_page=2, sort=latest
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson("/api/v1/drivers/{$driver['user']->id}/reviews?per_page=2&sort=latest");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'reviews');
        $response->assertJsonStructure([
            'success',
            'reviews',
            'rating_summary' => [
                'average_rating',
                'total_reviews',
                'five_star',
                'four_star',
                'three_star',
                'two_star',
                'one_star',
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['first', 'last', 'prev', 'next']
        ]);

        // Default latest: reviews should be sorted newest first (ID 3, then ID 2)
        $this->assertEquals(3, $response->json('reviews.0.rating'));
        $this->assertEquals('Alice Rider', $response->json('reviews.0.reviewer_name'));

        // Review #2 is anonymous: check reviewer_name is "Anonymous" and reviewer_id is NOT in the listing response items
        $this->assertEquals('Anonymous', $response->json('reviews.1.reviewer_name'));
        $response->assertJsonMissing(['reviewer_id']);

        // Check Rating Summary totals
        $response->assertJsonFragment([
            'rating_summary' => [
                'average_rating' => 2.0, // (5*0 + 1 + 2 + 3)/3 = 6/3 = 2.0
                'total_reviews' => 3,
                'five_star' => 0,
                'four_star' => 0,
                'three_star' => 1,
                'two_star' => 1,
                'one_star' => 1,
            ]
        ]);

        // 2. Check sort = highest_rating
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson("/api/v1/drivers/{$driver['user']->id}/reviews?sort=highest_rating");
        $this->assertEquals(3, $response->json('reviews.0.rating'));

        // 3. Check sort = lowest_rating
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson("/api/v1/drivers/{$driver['user']->id}/reviews?sort=lowest_rating");
        $this->assertEquals(1, $response->json('reviews.0.rating'));

        // 4. Test validation of review tags limit (> 5 tags)
        $ride = $this->createRide($driver, RideStatus::COMPLETED);
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 5,
            'review_tags' => ['1', '2', '3', '4', '5', '6'], // 6 tags is too many
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['review_tags']);

        // 5. Test validation of tag character length (> 30 chars)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson("/api/v1/rides/{$ride->id}/review", [
            'rating' => 5,
            'review_tags' => [str_repeat('a', 31)],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['review_tags.0']);
    }
}
