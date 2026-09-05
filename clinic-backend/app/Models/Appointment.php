<?php

namespace App\Models;

class Appointment extends ClinicModel
{
    protected $hidden = ['token_hash', 'token_expires_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'token_expires_at' => 'datetime', 'consent_at' => 'datetime', 'follow_up' => 'boolean'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function events()
    {
        return $this->hasMany(AppointmentEvent::class);
    }
}
