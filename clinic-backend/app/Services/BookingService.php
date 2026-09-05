<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AuditLog;
use App\Models\AvailabilityRule;
use App\Models\NotificationAttempt;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public const STATUSES = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'];

    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['completed'], 'completed' => [], 'cancelled' => [], 'no_show' => [],
    ];

    public function policy(): array
    {
        return Setting::getValue('booking', []);
    }

    public function timezone(): string
    {
        return 'Asia/Kolkata';
    }

    // Serialize all schedule writes on an existing row, including the first booking of a day.
    // An UPDATE also obtains SQLite's write lock for the local development database.
    public function lock(): void
    {
        DB::table('settings')->where('key', 'booking')->update(['updated_at' => now()]);
        Setting::where('key', 'booking')->lockForUpdate()->firstOrFail();
    }

    public function slots(string $date, int $serviceId, ?int $ignoreId = null): array
    {
        $service = Service::where('published', true)->findOrFail($serviceId);
        $day = CarbonImmutable::parse($date, $this->timezone())->startOfDay();
        $now = CarbonImmutable::now($this->timezone());
        $policy = $this->policy();
        if ($day->lt($now->startOfDay()) || $day->gt($now->startOfDay()->addDays($policy['horizon_days'] ?? 30))) {
            return [];
        }
        $startDay = $day->utc();
        $endDay = $day->addDay()->utc();
        $bookings = Appointment::where('starts_at', '>=', $startDay)->where('starts_at', '<', $endDay)
            ->where('status', '!=', 'cancelled')->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->get();
        if ($bookings->count() >= ($policy['daily_capacity'] ?? 40)) {
            return [];
        }
        $blocks = ScheduleBlock::where('starts_at', '<', $endDay)->where('ends_at', '>', $startDay)->get();
        $slots = [];
        foreach (AvailabilityRule::where('weekday', $day->dayOfWeek)->where('active', true)->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))->get() as $rule) {
            $start = CarbonImmutable::parse($date.' '.$rule->start_time, $this->timezone());
            $end = CarbonImmutable::parse($date.' '.$rule->end_time, $this->timezone());
            $duration = max($service->duration, $rule->slot_minutes);
            for ($at = $start; $at->addMinutes($duration)->lte($end); $at = $at->addMinutes($duration + $rule->buffer_minutes)) {
                if ($at->lte($now->addMinutes($policy['lead_minutes'] ?? 30))) {
                    continue;
                }
                $until = $at->addMinutes($duration);
                $bufferEnd = $until->addMinutes($rule->buffer_minutes);
                if ($blocks->contains(fn ($b) => $b->starts_at->lt($bufferEnd) && $b->ends_at->gt($at))) {
                    continue;
                }
                $used = $bookings->filter(fn ($a) => $a->starts_at->lt($bufferEnd) && $a->ends_at->copy()->addMinutes($rule->buffer_minutes)->gt($at))->count();
                if ($used < $rule->capacity) {
                    $slots[$at->toIso8601String()] = ['starts_at' => $at->toIso8601String(), 'ends_at' => $until->toIso8601String(), 'label' => $at->format('h:i A'), 'remaining' => $rule->capacity - $used];
                }
            }
        }
        ksort($slots);

        return array_values($slots);
    }

    private function slot(string $startsAt, int $serviceId, ?int $ignoreId = null): array
    {
        $at = CarbonImmutable::parse($startsAt)->setTimezone($this->timezone());
        foreach ($this->slots($at->toDateString(), $serviceId, $ignoreId) as $slot) {
            if (CarbonImmutable::parse($slot['starts_at'])->equalTo($at)) {
                return $slot;
            }
        }
        throw ValidationException::withMessages(['starts_at' => 'This slot is no longer available. Please select another time.']);
    }

    public function create(array $data, bool $staff = false): array
    {
        return DB::transaction(function () use ($data, $staff) {
            $this->lock();
            $slot = $this->slot($data['starts_at'], (int) $data['service_id']);
            $start = CarbonImmutable::parse($slot['starts_at'])->utc();
            $duplicate = Appointment::whereHas('patient', fn ($q) => $q->where('phone', $data['phone']))
                ->where('status', '!=', 'cancelled')->whereBetween('starts_at', [$start->subMinutes(60), $start->addMinutes(60)])->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['phone' => 'A booking for this mobile number already exists near this time. Use its management link or contact reception.']);
            }
            $patient = Patient::firstOrCreate(['phone' => $data['phone'], 'name' => $data['name']], ['age_group' => $data['age_group'], 'gender' => $data['gender'] ?? null]);
            $token = Str::random(64);
            $appointment = Appointment::create([
                'reference' => 'NJC-'.strtoupper(Str::random(10)), 'token_hash' => hash('sha256', $token),
                'token_expires_at' => $start->addDays(2), 'patient_id' => $patient->id, 'service_id' => $data['service_id'],
                'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($slot['ends_at'])->utc(),
                'status' => ($this->policy()['instant_confirmation'] ?? false) ? 'confirmed' : 'pending',
                'source' => $staff ? ($data['source'] ?? 'phone') : 'website', 'reason' => $data['reason'] ?? null,
                'follow_up' => $data['follow_up'] ?? false, 'consent_at' => now(), 'email' => $data['email'] ?? null,
                'communication' => $data['communication'] ?? 'none',
            ]);
            $this->event($appointment, null, 'Booking requested');
            $this->notify($appointment, 'booking');

            return ['appointment' => $this->summary($appointment), 'token' => $token];
        }, 5);
    }

    public function change(Appointment $appointment, array $data, bool $patient = false): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $patient) {
            $this->lock();
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);
            $from = $appointment->status;
            if ($patient) {
                abort_unless(in_array($from, ['pending', 'confirmed']), 422, 'This booking can no longer be changed online.');
                abort_if($appointment->starts_at->lte(now()->addHours($this->policy()['cutoff_hours'] ?? 2)), 422, 'The online change window has closed. Please contact reception.');
            }
            if (isset($data['starts_at'])) {
                abort_unless(in_array($from, ['pending', 'confirmed']), 422, 'Only pending or confirmed appointments can be rescheduled.');
                $slot = $this->slot($data['starts_at'], $appointment->service_id, $appointment->id);
                $at = CarbonImmutable::parse($slot['starts_at'])->utc();
                $duplicate = Appointment::where('id', '!=', $appointment->id)->where('status', '!=', 'cancelled')->whereHas('patient', fn ($q) => $q->where('phone', $appointment->patient->phone))->whereBetween('starts_at', [$at->subMinutes(60), $at->addMinutes(60)])->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['starts_at' => 'This patient already has another booking near that time.']);
                }
                $appointment->starts_at = CarbonImmutable::parse($slot['starts_at'])->utc();
                $appointment->ends_at = CarbonImmutable::parse($slot['ends_at'])->utc();
                $appointment->token_expires_at = $appointment->starts_at->copy()->addDays(2);
                $appointment->status = ($this->policy()['instant_confirmation'] ?? false) ? 'confirmed' : 'pending';
            } elseif (isset($data['status']) && $data['status'] !== $from) {
                abort_unless(in_array($data['status'], self::TRANSITIONS[$from]), 422, 'This status transition is not allowed.');
                $appointment->status = $data['status'];
            }
            if (! $patient && array_key_exists('internal_notes', $data)) {
                $appointment->internal_notes = $data['internal_notes'];
            }
            $appointment->save();
            $this->event($appointment, $from, $patient ? 'Patient changed booking' : ($data['change_reason'] ?? 'Staff updated booking'));
            if ($appointment->status !== $from || isset($data['starts_at'])) {
                $this->notify($appointment, isset($data['starts_at']) ? 'rescheduled' : $appointment->status);
            }

            return $appointment->load('patient', 'service', 'events');
        }, 5);
    }

    private function event(Appointment $a, ?string $from, string $reason): void
    {
        AppointmentEvent::create(['appointment_id' => $a->id, 'actor_id' => auth()->id(), 'from_status' => $from, 'to_status' => $a->status, 'reason' => $reason]);
        AuditLog::record('appointment.updated', 'appointment', $a->id, ['from' => $from, 'to' => $a->status]);
    }

    private function notify(Appointment $a, string $template): void
    {
        if ($a->communication !== 'email' || ! $a->email) {
            return;
        }
        NotificationAttempt::create(['appointment_id' => $a->id, 'channel' => 'email', 'template' => $template, 'recipient_mask' => substr($a->email, 0, 1).'***@'.Str::after($a->email, '@'), 'scheduled_at' => now()]);
    }

    public function byToken(string $token): Appointment
    {
        abort_unless(strlen($token) === 64, 404);

        return Appointment::where('token_hash', hash('sha256', $token))->where('token_expires_at', '>', now())->firstOrFail();
    }

    public function summary(Appointment $a): array
    {
        return ['reference' => $a->reference, 'status' => $a->status, 'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at->toIso8601String(),
            'service' => $a->service->name, 'service_id' => $a->service_id, 'patient_name' => $a->patient->name,
            'phone_mask' => '******'.substr($a->patient->phone,-4), 'can_manage' => in_array($a->status,['pending', 'confirmed']) && $a->starts_at->gt(now()->addHours($this->policy()['cutoff_hours'] ?? 2))];
    }
}
