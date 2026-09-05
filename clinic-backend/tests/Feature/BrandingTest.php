<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_change_and_restore_public_logo(): void
    {
        $this->seed(ClinicSeeder::class);
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $this->getJson('/api/public/clinic')->assertJsonPath('branding.logo_url', '/brand-logo.jpg');
        $response = $this->post('/api/admin/branding/logo', ['image' => UploadedFile::fake()->image('new-logo.png', 300, 300)], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('custom', true);
        $path = $response->json('logo_url');
        $this->getJson('/api/public/clinic')->assertJsonPath('branding.logo_url', $path);
        $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertDatabaseHas('audit_logs', ['action' => 'branding.logo.updated']);
        $this->deleteJson('/api/admin/branding/logo')->assertOk()->assertJsonPath('custom', false);
        $this->getJson('/api/public/clinic')->assertJsonPath('branding.logo_url', '/brand-logo.jpg');
        $this->assertDatabaseHas('audit_logs', ['action' => 'branding.logo.restored']);
    }

    public function test_logo_writes_require_admin_and_safe_images(): void
    {
        $this->seed(ClinicSeeder::class);
        $this->postJson('/api/admin/branding/logo')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'receptionist', 'active' => true]));
        $this->postJson('/api/admin/branding/logo')->assertForbidden();
        $this->deleteJson('/api/admin/branding/logo')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $this->post('/api/admin/branding/logo', ['image' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/admin/branding/logo', ['image' => UploadedFile::fake()->image('huge.png', 4001, 10)], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
