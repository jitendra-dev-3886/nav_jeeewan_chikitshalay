<?php

namespace App\Jobs;

use App\Models\NotificationAttempt;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAppointmentNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $n = NotificationAttempt::findOrFail($this->notificationId);
        if (in_array($n->status, ['sent', 'skipped', 'unconfigured'])) {
            return;
        }
        $a = $n->appointment;
        if (str_starts_with($n->template, 'reminder_')) {
            $hours = (int) substr($n->template, 9);
            if (! $n->scheduled_at->copy()->addHours($hours)->equalTo($a->starts_at)) {
                $n->update(['status' => 'skipped']);

                return;
            }
        }
        if (str_starts_with($n->template, 'reminder') && (! in_array($a->status, ['confirmed']) || $a->starts_at->isPast())) {
            $n->update(['status' => 'skipped']);

            return;
        }
        if (in_array(config('mail.default'), ['log', 'array'])) {
            $n->update(['status' => 'unconfigured', 'provider_response' => 'Configure a real mail transport to enable delivery.']);

            return;
        }
        $n->increment('attempts');
        $key = str_starts_with($n->template, 'reminder') ? 'reminder' : $n->template;
        $template = Setting::getValue('templates', [])[$key] ?? 'Appointment {reference}: {status}, {time}.';
        $message = strtr($template, ['{reference}' => $a->reference, '{status}' => str_replace('_', ' ', $a->status), '{time}' => $a->starts_at->setTimezone('Asia/Kolkata')->format('d M Y, h:i A').' IST']);
        try {
            Mail::raw($message, fn ($mail) => $mail->to($a->email)->subject('Clinic appointment update'));
            $n->update(['status' => 'sent', 'provider_response' => 'Mail transport accepted the message.']);
        } catch (\Throwable $e) {
            $n->update(['status' => 'failed', 'provider_response' => 'Mail delivery failed. Check the configured transport.']);
            throw new \RuntimeException('Clinic notification transport failed.');
        }
    }
}
