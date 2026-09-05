<?php

namespace Tests\Feature;

use App\Jobs\SendAppointmentNotification;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ContentPage;
use App\Models\NotificationAttempt;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\ClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClinicSeeder::class);
        CarbonImmutable::setTestNow('2026-09-07 01:00:00 UTC');
        Carbon::setTestNow('2026-09-07 01:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Test Patient', 'phone' => '9876543210', 'age_group' => '18_35', 'gender' => 'prefer_not', 'service_id' => Service::first()->id, 'starts_at' => '2026-09-08T09:00:00+05:30', 'consent' => true, 'communication' => 'none', 'follow_up' => false], $overrides);
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function book(array $data = []): array
    {
        return $this->postJson('/api/public/appointments', $this->payload($data))->assertCreated()->json();
    }

    public function test_public_profile_and_published_content(): void
    {
        $this->getJson('/api/public/clinic')->assertOk()->assertJsonPath('profile.doctor', 'Dr. Parmesh Kumar');
        ContentPage::create(['type' => 'article', 'title' => 'Secret draft', 'slug' => 'draft-test', 'body' => 'Draft body', 'published' => false]);
        $this->getJson('/api/public/content')->assertOk()->assertJsonMissing(['slug' => 'draft-test']);
    }

    public function test_booking_and_secure_summary_excludes_private_fields(): void
    {
        $data = $this->book();
        $a = Appointment::first();
        $this->assertNotEquals($data['token'], $a->token_hash);
        $this->assertEquals('2026-09-08 03:30:00', $a->starts_at->format('Y-m-d H:i:s'));
        $this->getJson('/api/public/appointments/'.$data['token'])->assertOk()->assertJsonPath('phone_mask', '******3210')->assertJsonMissingPath('internal_notes')->assertJsonMissingPath('token_hash')->assertJsonMissingPath('reason');
        $this->getJson('/api/public/appointments/'.str_repeat('x', 64))->assertNotFound();
        $this->assertDatabaseCount('appointment_events', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_occupied_slot_is_rejected_without_second_patient_write(): void
    {
        $this->book();
        $this->postJson('/api/public/appointments', $this->payload(['phone' => '9876543211']))->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_capacity_two_allocates_exactly_two_places(): void
    {
        AvailabilityRule::where('weekday', 2)->update(['capacity' => 2]);
        $this->book();
        $this->book(['phone' => '9876543211']);
        $this->postJson('/api/public/appointments', $this->payload(['phone' => '9876543212']))->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_closure_sunday_horizon_and_invalid_grid_are_unbookable(): void
    {
        ScheduleBlock::create(['starts_at' => '2026-09-08 03:30:00', 'ends_at' => '2026-09-08 04:00:00', 'reason' => 'Leave']);
        foreach (['2026-09-08T09:00:00+05:30', '2026-09-13T09:00:00+05:30', '2026-12-08T09:00:00+05:30', '2026-09-08T11:07:00+05:30'] as $at) {
            $this->postJson('/api/public/appointments', $this->payload(['starts_at' => $at]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_duplicate_phone_window_and_normalization(): void
    {
        $this->book(['phone' => '+91 98765 43210']);
        $this->postJson('/api/public/appointments', $this->payload(['starts_at' => '2026-09-08T09:20:00+05:30']))->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_public_validation_and_consent(): void
    {
        $this->postJson('/api/public/appointments', $this->payload(['consent' => false, 'phone' => '123', 'name' => '']))->assertUnprocessable()->assertJsonValidationErrors(['consent', 'phone', 'name']);
        $this->postJson('/api/public/enquiries', ['name' => 'Bot', 'phone' => '9876543210', 'message' => 'Spam submission', 'consent' => true, 'website' => 'spam'])->assertUnprocessable();
    }

    public function test_public_reschedule_and_cancel_release_capacity(): void
    {
        $data = $this->book();
        $url = '/api/public/appointments/'.$data['token'];
        $this->patchJson($url, ['starts_at' => '2026-09-08T10:00:00+05:30'])->assertOk();
        $this->patchJson($url, ['status' => 'cancelled'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->book(['starts_at' => '2026-09-08T10:00:00+05:30']);
    }

    public function test_cutoff_and_expired_token(): void
    {
        $data = $this->book();
        CarbonImmutable::setTestNow('2026-09-08 03:00:00 UTC');
        Carbon::setTestNow('2026-09-08 03:00:00 UTC');
        $this->patchJson('/api/public/appointments/'.$data['token'], ['status' => 'cancelled'])->assertUnprocessable();
        Appointment::first()->update(['token_expires_at' => now()->subMinute()]);
        $this->getJson('/api/public/appointments/'.$data['token'])->assertNotFound();
    }

    public function test_reception_flow_and_invalid_transition(): void
    {
        $this->book();
        $this->actingAs($this->user('receptionist'));
        $url = '/api/admin/appointments/'.Appointment::first()->id.'/status';
        $this->patchJson($url, ['status' => 'completed'])->assertUnprocessable();
        foreach (['confirmed', 'checked_in', 'completed'] as $status) {
            $this->patchJson($url, ['status' => $status])->assertOk()->assertJsonPath('status', $status);
        }
        $this->patchJson($url, ['status' => 'pending'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_events', 4);
    }

    public function test_guest_and_role_permissions_are_enforced(): void
    {
        $this->getJson('/api/admin/appointments')->assertUnauthorized();
        $doctor = $this->user('doctor');
        $this->actingAs($doctor);
        $this->getJson('/api/admin/appointments')->assertOk();
        foreach (['/api/admin/settings', '/api/admin/patients', '/api/admin/services', '/api/admin/reports/export'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->postJson('/api/admin/appointments', $this->payload())->assertForbidden();
        $this->actingAs($this->user('receptionist'));
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/admin/enquiries')->assertOk();
    }

    public function test_inactive_account_and_failed_login(): void
    {
        $user = $this->user();
        $user->update(['active' => false]);
        $this->actingAs($user);
        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_publish_draft_and_audit_export(): void
    {
        $this->actingAs($this->user());
        $data = ['title' => 'Clinic update', 'slug' => 'clinic-update', 'type' => 'article', 'language' => 'en', 'body' => 'Approved clinic update.', 'published' => false];
        $id = $this->postJson('/api/admin/content', $data)->assertCreated()->json('id');
        $this->getJson('/api/public/content')->assertJsonMissing(['slug' => 'clinic-update']);
        $this->putJson('/api/admin/content/'.$id, [...$data, 'published' => true])->assertOk();
        $this->getJson('/api/public/content')->assertJsonFragment(['slug' => 'clinic-update']);
        $this->get('/api/admin/reports/export')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointments.exported']);
    }

    public function test_testimonial_consent_and_self_demotion(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $this->postJson('/api/admin/testimonials', ['name' => 'Patient', 'quote' => 'A patient comment', 'consent' => false, 'published' => true, 'sort_order' => 0])->assertUnprocessable();
        $this->putJson('/api/admin/users/'.$user->id, ['name' => $user->name, 'email' => $user->email, 'role' => 'doctor', 'active' => true])->assertUnprocessable();
    }

    public function test_overlapping_schedule_and_daily_capacity(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/api/admin/availability', ['weekday' => 2, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 20, 'buffer_minutes' => 0, 'capacity' => 1, 'active' => true])->assertUnprocessable();
        $policy = Setting::getValue('booking');
        $policy['daily_capacity'] = 1;
        Setting::where('key', 'booking')->first()->update(['value' => $policy]);
        $service = app(BookingService::class);
        $service->create($this->payload());
        $this->assertEmpty($service->slots('2026-09-08', Service::first()->id));
    }

    public function test_notification_is_recorded_and_unconfigured_mail_is_not_marked_sent(): void
    {
        $this->book(['communication' => 'email', 'email' => 'patient@example.test']);
        $n = NotificationAttempt::first();
        $this->assertEquals('p***@example.test', $n->recipient_mask);
        config(['mail.default' => 'log']);
        (new SendAppointmentNotification($n->id))->handle();
        $this->assertEquals('unconfigured', $n->fresh()->status);
    }

    public function test_notification_message_omits_visit_reason_and_internal_notes(): void
    {
        $this->book(['communication' => 'email', 'email' => 'patient@example.test', 'reason' => 'PRIVATE REASON']);
        $a = Appointment::first();
        $a->update(['internal_notes' => 'PRIVATE NOTE']);
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->withArgs(function ($message, $callback) {
            $this->assertStringNotContainsString('PRIVATE', $message);

            return true;
        });
        $n = NotificationAttempt::first();
        (new SendAppointmentNotification($n->id))->handle();
        $this->assertEquals('sent', $n->fresh()->status);
    }

    public function test_public_form_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/appointments', [])->assertUnprocessable();
        }
        $this->postJson('/api/public/appointments', [])->assertStatus(429);
    }

    public function test_effective_dates_prevent_booking_outside_schedule_period(): void
    {
        AvailabilityRule::where('weekday', 2)->update(['effective_from' => '2026-09-15', 'effective_to' => '2026-09-22']);
        $this->postJson('/api/public/appointments', $this->payload())->assertUnprocessable();
        $this->assertNotEmpty(app(BookingService::class)->slots('2026-09-15', Service::first()->id));
    }

    public function test_redirects_are_local_permanent_and_cannot_loop(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/api/admin/redirects', ['from_path' => '/old-clinic', 'to_path' => '/about', 'active' => true])->assertCreated();
        $this->get('/old-clinic')->assertStatus(301)->assertRedirect('/about');
        $this->postJson('/api/admin/redirects', ['from_path' => '/about', 'to_path' => '/old-clinic', 'active' => true])->assertUnprocessable();
        $this->postJson('/api/admin/redirects', ['from_path' => '/elsewhere', 'to_path' => '//example.com', 'active' => true])->assertUnprocessable();
    }

    public function test_gallery_reencodes_images_and_rejects_active_content(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user());
        $response = $this->post('/api/admin/media', ['image' => UploadedFile::fake()->image('clinic.jpg', 200, 200), 'alt' => 'Clinic waiting area', 'published' => '1'], ['Accept' => 'application/json'])->assertCreated();
        $this->get($response->json('path'))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->getJson('/api/public/clinic')->assertJsonFragment(['alt'=>'Clinic waiting area']);
        $this->putJson('/api/admin/media/'.$response->json('id'), ['alt'=>'Clinic waiting area','caption'=>'Reception','published'=>false])->assertOk();
        $this->getJson('/api/public/clinic')->assertJsonMissing(['alt'=>'Clinic waiting area']);
        $this->getJson('/api/admin/media')->assertJsonFragment(['alt'=>'Clinic waiting area','published'=>false]);
        $this->putJson('/api/admin/media/'.$response->json('id'), ['alt'=>'Clinic waiting area','caption'=>'Reception','published'=>true])->assertOk();
        $this->getJson('/api/public/clinic')->assertJsonFragment(['alt'=>'Clinic waiting area']);
        $this->get('/gallery')->assertOk();
        $this->post('/api/admin/media', ['image' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml'), 'alt' => 'Unsafe', 'published' => '1'], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_built_public_html_has_server_content_and_unknown_page_is_404(): void
    {
        if (! is_file(base_path('../clinic-frontend/dist/index.html'))) {
            $this->markTestSkipped('Build frontend first to verify server HTML.');
        }
        $this->get('/about')->assertOk()->assertSee('Dr. Parmesh Kumar')->assertSee('BAMS, DETCT');
        $this->get('/no-such-public-page')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/services/general-consultation');
    }

    public function test_settings_reject_unexpected_secret_keys_and_audit_valid_update(): void
    {
        $this->actingAs($this->user());
        $data = ['clinic' => Setting::getValue('clinic'), 'booking' => Setting::getValue('booking'), 'templates' => Setting::getValue('templates')];
        $this->putJson('/api/admin/settings',$data)->assertOk();
        $this->assertDatabaseHas('audit_logs',['action' => 'settings.updated']);
        $data['clinic']['password'] = 'not-a-setting';
        $this->putJson('/api/admin/settings',$data)->assertUnprocessable();
    }
}
