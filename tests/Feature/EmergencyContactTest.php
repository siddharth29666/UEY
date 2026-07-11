<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EmergencyContact;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyContactTest extends TestCase
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

        // Seed settings
        Setting::updateOrCreate(
            ['key' => 'maximum_emergency_contacts'],
            ['value' => '5']
        );
        app(SettingService::class)->refreshCache();
    }

    public function test_rider_can_create_emergency_contact()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson('/api/v1/emergency-contacts', [
            'name' => 'John Doe',
            'phone' => '+447911111111',
            'relationship' => 'Brother',
            'priority' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'user_id', 'name', 'phone', 'relationship', 'priority'],
            ]);

        $this->assertDatabaseHas('emergency_contacts', [
            'user_id' => $this->rider->id,
            'phone' => '+447911111111',
        ]);
    }

    public function test_rider_cannot_save_duplicate_phone()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        EmergencyContact::create([
            'user_id' => $this->rider->id,
            'name' => 'John Doe',
            'phone' => '+447911111111',
            'relationship' => 'Brother',
            'priority' => 1,
        ]);

        // Try second time
        $response = $this->postJson('/api/v1/emergency-contacts', [
            'name' => 'Johnny Doe',
            'phone' => '+447911111111',
            'relationship' => 'Cousin',
            'priority' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_rider_respects_max_contacts_limit()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        // Create 5 contacts (max limit)
        for ($i = 1; $i <= 5; $i++) {
            EmergencyContact::create([
                'user_id' => $this->rider->id,
                'name' => "Contact {$i}",
                'phone' => "+44791111111{$i}",
                'relationship' => 'Friend',
                'priority' => $i,
            ]);
        }

        // Try adding 6th contact
        $response = $this->postJson('/api/v1/emergency-contacts', [
            'name' => 'Extra Contact',
            'phone' => '+447999999999',
            'relationship' => 'Friend',
            'priority' => 6,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_rider_can_retrieve_contacts_ordered_by_priority()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        EmergencyContact::create([
            'user_id' => $this->rider->id,
            'name' => 'Secondary Contact',
            'phone' => '+447922222222',
            'relationship' => 'Friend',
            'priority' => 2,
        ]);

        EmergencyContact::create([
            'user_id' => $this->rider->id,
            'name' => 'Primary Contact',
            'phone' => '+447911111111',
            'relationship' => 'Mother',
            'priority' => 1,
        ]);

        $response = $this->getJson('/api/v1/emergency-contacts/default');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('contacts.0.name', 'Primary Contact')
            ->assertJsonPath('contacts.1.name', 'Secondary Contact');
    }
}
