<?php

namespace Tests\Feature;

use App\Models\CancellationReason;
use App\Models\ContactSubmission;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\LegalPage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCmsAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->rider = User::factory()->create([
            'role' => 'rider',
            'status' => 'active',
        ]);

        $this->driver = User::factory()->create([
            'role' => 'driver',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function admin_can_manage_dynamic_general_settings()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'app_name' => 'UEY Mobility Premium',
                'contact_email' => 'support@uey.com',
                'currency' => 'GBP',
                'night_charge_enabled' => true,
                'night_charge_type' => 'percentage',
                'night_charge_value' => 15,
                'night_start_time' => '22:00',
                'night_end_time' => '06:00',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('settings', [
            'key' => 'app_name',
            'value' => 'UEY Mobility Premium',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'night_charge_enabled',
            'value' => '1',
        ]);
    }

    /** @test */
    public function rider_cannot_access_admin_settings()
    {
        $response = $this->actingAs($this->rider, 'sanctum')
            ->getJson('/api/v1/admin/settings');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_crud_cancellation_reasons()
    {
        // 1. Create
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/cancellation-reasons', [
                'reason' => 'Driver is taking too long',
                'type' => 'rider',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reason', 'Driver is taking too long');

        $id = $response->json('data.id');

        // 2. Public List
        $publicRes = $this->getJson('/api/v1/cancellation-reasons?type=rider');
        $publicRes->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 3. Update
        $updateRes = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/cancellation-reasons/{$id}", [
                'reason' => 'Driver delayed over 10 mins',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.reason', 'Driver delayed over 10 mins');

        // 4. Toggle Status
        $statusRes = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/cancellation-reasons/{$id}/status");

        $statusRes->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Inactive reason hidden from public
        $publicHiddenRes = $this->getJson('/api/v1/cancellation-reasons?type=rider');
        $publicHiddenRes->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function admin_can_manage_faqs_and_audience_filtering()
    {
        // Create Category
        $catRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/faq-categories', [
                'name' => 'Driver Earnings & Billing',
                'audience' => 'driver',
            ]);

        $catRes->assertStatus(201);
        $catId = $catRes->json('data.id');

        // Create FAQ for Driver
        $faqRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/faqs', [
                'faq_category_id' => $catId,
                'question' => 'When do I get paid?',
                'answer' => 'Payouts process weekly every Monday.',
                'audience' => 'driver',
            ]);

        $faqRes->assertStatus(201);

        // Public filter: Rider receives 0 driver FAQs
        $riderFaqRes = $this->getJson('/api/v1/faqs?audience=rider');
        $riderFaqRes->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Public filter: Driver receives driver FAQ
        $driverFaqRes = $this->getJson('/api/v1/faqs?audience=driver');
        $driverFaqRes->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function public_user_can_submit_contact_form_and_admin_manage()
    {
        // Public Submission
        $submitRes = $this->postJson('/api/v1/contact-us', [
            'name' => 'Jane Rider',
            'email' => 'jane@example.com',
            'subject' => 'Payment Dispute',
            'message' => 'I was charged twice for ride #12.',
        ]);

        $submitRes->assertStatus(201)
            ->assertJsonPath('data.subject', 'Payment Dispute');

        $submissionId = $submitRes->json('data.id');

        // Admin List & Show
        $adminList = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-submissions');
        $adminList->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Admin Update Status
        $adminUpdate = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/contact-submissions/{$submissionId}", [
                'status' => 'resolved',
                'admin_notes' => 'Refund processed via Stripe.',
            ]);

        $adminUpdate->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');
    }

    /** @test */
    public function admin_can_manage_privacy_policy_and_terms_and_public_can_view()
    {
        // Admin Create Privacy Policy
        $privacyRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/legal-pages', [
                'slug' => 'privacy-policy',
                'title' => 'UEY Privacy Policy',
                'content' => 'We respect user privacy and encrypt data.',
                'version' => '1.0',
                'is_published' => true,
            ]);

        $privacyRes->assertStatus(201);

        // Public Get Privacy Policy
        $publicPrivacy = $this->getJson('/api/v1/privacy-policy');
        $publicPrivacy->assertStatus(200)
            ->assertJsonPath('data.title', 'UEY Privacy Policy');

        // Admin Create Terms & Conditions
        $termsRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/legal-pages', [
                'slug' => 'terms-and-conditions',
                'title' => 'UEY Terms & Conditions',
                'content' => 'By using UEY, you agree to these terms.',
                'version' => '1.0',
                'is_published' => true,
            ]);

        $termsRes->assertStatus(201);

        // Public Get Terms
        $publicTerms = $this->getJson('/api/v1/terms-and-conditions');
        $publicTerms->assertStatus(200)
            ->assertJsonPath('data.title', 'UEY Terms & Conditions');
    }
}
