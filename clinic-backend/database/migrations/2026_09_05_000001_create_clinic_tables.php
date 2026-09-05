<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('receptionist');
            $t->boolean('active')->default(true);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('summary');
            $t->text('description')->nullable();
            $t->text('preparation')->nullable();
            $t->unsignedInteger('duration')->default(20);
            $t->string('icon')->default('stethoscope');
            $t->boolean('published')->default(false);
            $t->string('seo_title')->nullable();
            $t->string('seo_description')->nullable();
            $t->timestamps();
        });
        Schema::create('availability_rules', function (Blueprint $t) {
            $t->id();
            $t->unsignedTinyInteger('weekday');
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedInteger('slot_minutes')->default(20);
            $t->unsignedInteger('buffer_minutes')->default(0);
            $t->unsignedInteger('capacity')->default(1);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('schedule_blocks', function (Blueprint $t) {
            $t->id();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('reason');
            $t->timestamps();
        });
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->index();
            $t->string('age_group');
            $t->string('gender')->nullable();
            $t->timestamps();
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('token_hash', 64)->unique();
            $t->dateTime('token_expires_at');
            $t->foreignId('patient_id')->constrained();
            $t->foreignId('service_id')->constrained();
            $t->dateTime('starts_at')->index();
            $t->dateTime('ends_at');
            $t->string('status')->default('pending')->index();
            $t->string('source')->default('website');
            $t->text('reason')->nullable();
            $t->text('internal_notes')->nullable();
            $t->boolean('follow_up')->default(false);
            $t->dateTime('consent_at');
            $t->string('email')->nullable();
            $t->string('communication')->default('none');
            $t->timestamps();
        });
        Schema::create('appointment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('content_pages', function (Blueprint $t) {
            $t->id();
            $t->string('type')->default('article');
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('language')->default('en');
            $t->text('excerpt')->nullable();
            $t->longText('body');
            $t->boolean('published')->default(false);
            $t->string('seo_title')->nullable();
            $t->string('seo_description')->nullable();
            $t->timestamps();
        });
        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->text('message');
            $t->string('source')->default('website');
            $t->string('status')->default('new');
            $t->foreignId('assigned_to')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('quote');
            $t->boolean('consent')->default(false);
            $t->boolean('published')->default(false);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('path');
            $t->string('alt');
            $t->string('caption')->nullable();
            $t->boolean('published')->default(false);
            $t->timestamps();
        });
        Schema::create('notification_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained();
            $t->string('channel');
            $t->string('template');
            $t->string('recipient_mask');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('provider_response')->nullable();
            $t->dateTime('scheduled_at');
            $t->timestamps();
            $t->unique(['appointment_id', 'template', 'scheduled_at'], 'notification_dedup');
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('entity');
            $t->string('entity_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notification_attempts', 'media', 'testimonials', 'enquiries', 'content_pages', 'appointment_events', 'appointments', 'patients', 'schedule_blocks', 'availability_rules', 'services', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
