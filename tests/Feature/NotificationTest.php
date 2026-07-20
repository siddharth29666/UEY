<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\ReviewReceivedEvent;
use App\Events\RideAcceptedEvent;
use App\Events\RideCompletedEvent;
use App\Events\RideRequestedEvent;
use App\Events\RideStartedEvent;
use App\Events\WalletTopupCompletedEvent;
use App\Events\WithdrawalRequestedEvent;
use App\Listeners\SendRideNotification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'name' => 'John Notification',
            'phone' => '+447911111111',
            'email' => 'john.notif@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test device registration endpoint.
     */
    public function test_device_registration_success()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'device_type' => 'android',
            'device_name' => 'Pixel 7 Pro',
            'device_token' => 'test_fcm_token_123',
            'platform' => 'Android',
            'os_version' => '13.0',
            'app_version' => '1.0.0',
            'language' => 'en',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/devices/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'device' => [
                    'id',
                    'device_type',
                    'device_name',
                    'device_token',
                    'platform',
                    'os_version',
                    'app_version',
                    'language',
                    'timezone',
                ],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'device_token' => 'test_fcm_token_123',
        ]);
    }

    /**
     * Test registering the same device token updates the existing record.
     */
    public function test_device_registration_updates_duplicate_token()
    {
        Sanctum::actingAs($this->user);

        // Pre-create device for this token
        UserDevice::create([
            'user_id' => $this->user->id,
            'device_type' => 'ios',
            'device_name' => 'iPhone 13',
            'device_token' => 'dup_token',
            'platform' => 'iOS',
            'os_version' => '15.0',
            'app_version' => '0.9.0',
            'language' => 'fr',
            'timezone' => 'GMT',
            'last_used_at' => now()->subDay(),
        ]);

        $payload = [
            'device_type' => 'ios',
            'device_name' => 'iPhone 14 Pro',
            'device_token' => 'dup_token',
            'platform' => 'iOS',
            'os_version' => '16.0',
            'app_version' => '1.0.0',
            'language' => 'en',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/devices/register', $payload);

        $response->assertStatus(201);

        // Assert no duplicates (only 1 record in user_devices for this token)
        $this->assertEquals(1, UserDevice::where('device_token', 'dup_token')->count());
        $this->assertDatabaseHas('user_devices', [
            'device_token' => 'dup_token',
            'device_name' => 'iPhone 14 Pro',
            'app_version' => '1.0.0',
        ]);
    }

    /**
     * Test updating device token.
     */
    public function test_device_update()
    {
        Sanctum::actingAs($this->user);

        $device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_type' => 'web',
            'device_name' => 'Chrome',
            'device_token' => 'web_token_abc',
            'platform' => 'Web',
        ]);

        $response = $this->putJson("/api/v1/devices/{$device->id}", [
            'device_name' => 'Chrome Safari Hybrid',
            'app_version' => '2.0.1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_devices', [
            'id' => $device->id,
            'device_name' => 'Chrome Safari Hybrid',
            'app_version' => '2.0.1',
        ]);
    }

    /**
     * Test deleting a device.
     */
    public function test_device_deletion()
    {
        Sanctum::actingAs($this->user);

        $device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_type' => 'android',
            'device_name' => 'Pixel 6',
            'device_token' => 'delete_token_xyz',
            'platform' => 'Android',
        ]);

        $response = $this->deleteJson("/api/v1/devices/{$device->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_devices', [
            'id' => $device->id,
        ]);
    }

    /**
     * Test unread count endpoint.
     */
    public function test_unread_notifications_count()
    {
        Sanctum::actingAs($this->user);

        // Pre-create notification logs
        NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Title 1',
            'body' => 'Body 1',
            'type' => NotificationType::RIDE_ACCEPTED,
            'category' => NotificationCategory::RIDE,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::SENT,
        ]);

        NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Title 2',
            'body' => 'Body 2',
            'type' => NotificationType::RIDE_COMPLETED,
            'category' => NotificationCategory::RIDE,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::READ,
            'read_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 1,
            ]);
    }

    /**
     * Test marking notification as read.
     */
    public function test_mark_notification_as_read()
    {
        Sanctum::actingAs($this->user);

        $notif = NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Title 1',
            'body' => 'Body 1',
            'type' => NotificationType::RIDE_ACCEPTED,
            'category' => NotificationCategory::RIDE,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::SENT,
        ]);

        $response = $this->postJson("/api/v1/notifications/{$notif->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($notif->refresh()->read_at);
        $this->assertEquals(NotificationStatus::READ, $notif->status);
    }

    /**
     * Test marking all notifications read.
     */
    public function test_mark_all_notifications_as_read()
    {
        Sanctum::actingAs($this->user);

        NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Title 1',
            'body' => 'Body 1',
            'type' => NotificationType::RIDE_ACCEPTED,
            'category' => NotificationCategory::RIDE,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::SENT,
        ]);

        $response = $this->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200);
        $this->assertEquals(0, NotificationLog::where('user_id', $this->user->id)->whereNull('read_at')->count());
    }

    /**
     * Test deleting and restoring a notification.
     */
    public function test_delete_and_restore_notification()
    {
        Sanctum::actingAs($this->user);

        $notif = NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Title 1',
            'body' => 'Body 1',
            'type' => NotificationType::RIDE_ACCEPTED,
            'category' => NotificationCategory::RIDE,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::SENT,
        ]);

        // Delete (Soft delete)
        $response = $this->deleteJson("/api/v1/notifications/{$notif->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('notification_logs', ['id' => $notif->id]);

        // Restore
        $response = $this->postJson("/api/v1/notifications/{$notif->id}/restore");
        $response->assertStatus(200);
        $this->assertDatabaseHas('notification_logs', [
            'id' => $notif->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test notification filtering and sorting.
     */
    public function test_notifications_filters_and_sorting()
    {
        Sanctum::actingAs($this->user);

        // Created older
        NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Alpha Ride Log',
            'body' => 'First message body',
            'type' => NotificationType::RIDE_ACCEPTED,
            'category' => NotificationCategory::RIDE,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::SENT,
            'created_at' => now()->subHours(2),
        ]);

        // Created newer
        NotificationLog::create([
            'user_id' => $this->user->id,
            'title' => 'Beta Wallet Log',
            'body' => 'Second message body',
            'type' => NotificationType::WALLET_TOPUP,
            'category' => NotificationCategory::WALLET,
            'priority' => NotificationPriority::HIGH,
            'status' => NotificationStatus::READ,
            'created_at' => now(),
        ]);

        // 1. Filter by category
        $response = $this->getJson('/api/v1/notifications?category=wallet');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Beta Wallet Log');

        // 2. Filter by status
        $response = $this->getJson('/api/v1/notifications?status=read');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Beta Wallet Log');

        // 3. Search query
        $response = $this->getJson('/api/v1/notifications?search=Alpha');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Alpha Ride Log');

        // 4. Sort Oldest first
        $response = $this->getJson('/api/v1/notifications?sort=oldest');
        $response->assertStatus(200)
            ->assertJsonPath('notifications.0.title', 'Alpha Ride Log')
            ->assertJsonPath('notifications.1.title', 'Beta Wallet Log');
    }

    /**
     * Verify event listening dispatches correctly onto the notifications queue.
     */
    public function test_queued_listeners_registered()
    {
        Queue::fake();

        event(new RideRequestedEvent(
            $this->user,
            NotificationType::RIDE_REQUESTED,
            'Test Title',
            'Test Body',
            ['ride_id' => 99]
        ));

        // In Laravel, listener handles of events are wrapped in CallQueuedListener instances when pushed to the queue
        Queue::assertPushedOn('notifications', CallQueuedListener::class, function ($job) {
            return $job->class === SendRideNotification::class;
        });
    }

    /**
     * Verify automatic notification on Ride Accepted event.
     */
    public function test_automatic_ride_accepted_notification()
    {
        event(new RideAcceptedEvent(
            $this->user,
            NotificationType::RIDE_ACCEPTED,
            null,
            null,
            ['ride_id' => 123]
        ));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'type' => NotificationType::RIDE_ACCEPTED->value,
            'status' => NotificationStatus::SENT->value,
        ]);

        $log = NotificationLog::where('user_id', $this->user->id)
            ->where('type', NotificationType::RIDE_ACCEPTED)
            ->first();
        $this->assertEquals(123, $log->payload['ride_id']);
    }

    /**
     * Verify automatic notification on Ride Started event.
     */
    public function test_automatic_ride_started_notification()
    {
        event(new RideStartedEvent(
            $this->user,
            NotificationType::RIDE_STARTED,
            null,
            null,
            ['ride_id' => 456]
        ));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'type' => NotificationType::RIDE_STARTED->value,
            'status' => NotificationStatus::SENT->value,
        ]);

        $log = NotificationLog::where('user_id', $this->user->id)
            ->where('type', NotificationType::RIDE_STARTED)
            ->first();
        $this->assertEquals(456, $log->payload['ride_id']);
    }

    /**
     * Verify automatic notification on Ride Completed event.
     */
    public function test_automatic_ride_completed_notification()
    {
        event(new RideCompletedEvent(
            $this->user,
            NotificationType::RIDE_COMPLETED,
            null,
            null,
            ['ride_id' => 789]
        ));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'type' => NotificationType::RIDE_COMPLETED->value,
            'status' => NotificationStatus::SENT->value,
        ]);

        $log = NotificationLog::where('user_id', $this->user->id)
            ->where('type', NotificationType::RIDE_COMPLETED)
            ->first();
        $this->assertEquals(789, $log->payload['ride_id']);
    }

    /**
     * Verify automatic notification on Wallet Top-up Completed event.
     */
    public function test_automatic_wallet_topup_completed_notification()
    {
        event(new WalletTopupCompletedEvent(
            $this->user,
            NotificationType::WALLET_TOPUP,
            null,
            null,
            ['amount' => 100.00]
        ));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'type' => NotificationType::WALLET_TOPUP->value,
            'status' => NotificationStatus::SENT->value,
        ]);

        $log = NotificationLog::where('user_id', $this->user->id)
            ->where('type', NotificationType::WALLET_TOPUP)
            ->first();
        $this->assertEquals(100.00, $log->payload['amount']);
    }

    /**
     * Verify automatic notification on Withdrawal Requested event.
     */
    public function test_automatic_withdrawal_requested_notification()
    {
        event(new WithdrawalRequestedEvent(
            $this->user,
            NotificationType::WITHDRAW_REQUESTED,
            null,
            null,
            ['amount' => 50.00]
        ));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'type' => NotificationType::WITHDRAW_REQUESTED->value,
            'status' => NotificationStatus::SENT->value,
        ]);

        $log = NotificationLog::where('user_id', $this->user->id)
            ->where('type', NotificationType::WITHDRAW_REQUESTED)
            ->first();
        $this->assertEquals(50.00, $log->payload['amount']);
    }

    /**
     * Verify automatic notification on Review Received / Submitted event.
     */
    public function test_automatic_review_submitted_notification()
    {
        event(new ReviewReceivedEvent(
            $this->user,
            NotificationType::REVIEW_RECEIVED,
            null,
            null,
            ['rating' => 5, 'ride_id' => 999]
        ));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'type' => NotificationType::REVIEW_RECEIVED->value,
            'status' => NotificationStatus::SENT->value,
        ]);

        $log = NotificationLog::where('user_id', $this->user->id)
            ->where('type', NotificationType::REVIEW_RECEIVED)
            ->first();
        $this->assertEquals(5, $log->payload['rating']);
        $this->assertEquals(999, $log->payload['ride_id']);
    }

    /**
     * Test config/services.php throws exception when service-account file does not exist.
     */
    public function test_firebase_config_throws_exception_if_service_account_file_missing()
    {
        $_ENV['FIREBASE_SERVICE_ACCOUNT'] = 'storage/app/firebase/missing-file.json';
        putenv('FIREBASE_SERVICE_ACCOUNT=storage/app/firebase/missing-file.json');

        try {
            require base_path('config/services.php');
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Firebase service account file not found', $e->getMessage());
        } finally {
            // Restore default
            $_ENV['FIREBASE_SERVICE_ACCOUNT'] = 'storage/app/firebase/service-account.json';
            putenv('FIREBASE_SERVICE_ACCOUNT=storage/app/firebase/service-account.json');
        }
    }

    /**
     * Test local environment + force disabled => mock mode (isEnabled returns false)
     */
    public function test_firebase_is_enabled_local_force_disabled()
    {
        app()->bind('env', fn() => 'local');

        config([
            'services.firebase.enabled' => true,
            'services.firebase.force_enable' => false,
            'services.firebase.testing_allow_real_calls' => false,
            'services.firebase.project_id' => 'ueyy-8c691',
            'services.firebase.client_email' => 'laravel-fcm@ueyy-8c691.iam.gserviceaccount.com',
            'services.firebase.private_key' => '-----BEGIN PRIVATE KEY----- fake key -----END PRIVATE KEY-----',
        ]);

        $firebaseService = app(\App\Services\FirebaseService::class);
        $this->assertFalse($firebaseService->isEnabled());

        // Restore env
        app()->bind('env', fn() => 'testing');
    }

    /**
     * Test local environment + force enabled => real FCM enabled (isEnabled returns true)
     */
    public function test_firebase_is_enabled_local_force_enabled()
    {
        app()->bind('env', fn() => 'local');

        config([
            'services.firebase.enabled' => true,
            'services.firebase.force_enable' => true,
            'services.firebase.project_id' => 'ueyy-8c691',
            'services.firebase.client_email' => 'laravel-fcm@ueyy-8c691.iam.gserviceaccount.com',
            'services.firebase.private_key' => '-----BEGIN PRIVATE KEY----- fake key -----END PRIVATE KEY-----',
        ]);

        $firebaseService = app(\App\Services\FirebaseService::class);
        $this->assertTrue($firebaseService->isEnabled());

        // Restore env
        app()->bind('env', fn() => 'testing');
    }

    /**
     * Test production environment + valid credentials => enabled (isEnabled returns true)
     */
    public function test_firebase_is_enabled_production_with_valid_credentials()
    {
        app()->bind('env', fn() => 'production');

        config([
            'services.firebase.enabled' => true,
            'services.firebase.project_id' => 'ueyy-8c691',
            'services.firebase.client_email' => 'laravel-fcm@ueyy-8c691.iam.gserviceaccount.com',
            'services.firebase.private_key' => '-----BEGIN PRIVATE KEY----- fake key -----END PRIVATE KEY-----',
        ]);

        $firebaseService = app(\App\Services\FirebaseService::class);
        $this->assertTrue($firebaseService->isEnabled());

        // Restore env
        app()->bind('env', fn() => 'testing');
    }

    /**
     * Test missing credentials => disabled (isEnabled returns false)
     */
    public function test_firebase_is_enabled_missing_credentials()
    {
        app()->bind('env', fn() => 'production');

        config([
            'services.firebase.enabled' => true,
            'services.firebase.project_id' => '',
            'services.firebase.client_email' => '',
            'services.firebase.private_key' => '',
        ]);

        $firebaseService = app(\App\Services\FirebaseService::class);
        $this->assertFalse($firebaseService->isEnabled());

        // Restore env
        app()->bind('env', fn() => 'testing');
    }
}
