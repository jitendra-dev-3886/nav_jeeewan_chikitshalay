<?php

use App\Jobs\SendAppointmentNotification;
use App\Models\Appointment;
use App\Models\NotificationAttempt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('clinic:admin {email} {--name=Clinic Administrator}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('This user already exists.');

        return 1;
    }
    $password = $this->secret('Set a password (at least 12 characters)');
    if (strlen((string) $password) < 12) {
        $this->error('Password must contain at least 12 characters.');

        return 1;
    }
    User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'admin', 'active' => true]);
    $this->info('Administrator created.');
})->purpose('Create a clinic administrator without a default password');

Artisan::command('clinic:notifications', function () {
    $hours = Setting::getValue('booking', [])['reminder_hours'] ?? [24, 2];
    Appointment::where('status', 'confirmed')->where('communication', 'email')->whereNotNull('email')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours(max($hours ?: [24])))->each(function ($a) use ($hours) {
        foreach ($hours as $h) {
            $due = $a->starts_at->copy()->subHours($h);
            if ($due->lte(now()) && $due->gt(now()->subMinutes(5))) {
                NotificationAttempt::firstOrCreate(['appointment_id' => $a->id, 'template' => 'reminder_'.$h, 'scheduled_at' => $due], ['channel' => 'email', 'recipient_mask' => substr($a->email, 0, 1).'***@'.Str::after($a->email, '@')]);
            }
        }
    });
    NotificationAttempt::where('status', 'pending')->where('scheduled_at', '<=', now())->each(function ($n) {
        DB::transaction(function () use ($n) {
            if (NotificationAttempt::whereKey($n->id)->where('status', 'pending')->update(['status' => 'queued'])) {
                SendAppointmentNotification::dispatch($n->id);
            }
        });
    });
    $this->info('Due notifications queued.');
})->purpose('Queue appointment updates and reminders');
Schedule::command('clinic:notifications')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
