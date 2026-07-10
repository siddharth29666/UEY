<?php

namespace Tests\Feature;

use App\Events\RideRequested;
use App\Events\WalletUpdated;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test ride requested broadcast event dispatches and properties.
     */
    public function test_ride_requested_event_broadcasts_on_presence_channel()
    {
        Event::fake();

        $rider = User::create([
            'name' => 'John Rider',
            'phone' => '+447911111111',
            'email' => 'john.rider@example.com',
            'password' => bcrypt('password123'),
        ]);

        $vehicleType = VehicleType::create([
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

        $ride = Ride::create([
            'rider_id' => $rider->id,
            'vehicle_type_id' => $vehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regents Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => 'pending',
            'otp' => '123456',
            'estimated_distance' => 2.50,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
            'payment_method' => 'cash',
        ]);

        event(new RideRequested($ride));

        Event::assertDispatched(RideRequested::class, function ($event) use ($ride) {
            $this->assertEquals($ride->id, $event->ride->id);
            $this->assertEquals(['presence-admins'], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));
            $this->assertEquals('pending', $event->broadcastWith()['status']);

            return true;
        });
    }

    /**
     * Test wallet updated broadcast event dispatches and properties.
     */
    public function test_wallet_updated_event_broadcasts_on_private_channel()
    {
        Event::fake();

        $rider = User::create([
            'name' => 'John Rider',
            'phone' => '+447911111111',
            'email' => 'john.rider@example.com',
            'password' => bcrypt('password123'),
        ]);

        $wallet = Wallet::create([
            'user_id' => $rider->id,
            'balance' => 150.00,
        ]);

        event(new WalletUpdated($wallet, 50.00, 'credit', 'Stripe Top-up'));

        Event::assertDispatched(WalletUpdated::class, function ($event) use ($wallet) {
            $this->assertEquals($wallet->id, $event->wallet->id);
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            $this->assertContains('private-wallet.'.$wallet->id, $channels);
            $this->assertContains('presence-admins', $channels);

            return true;
        });
    }
}
