<?php

namespace App\Models;

class NotificationAttempt extends ClinicModel
{
    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
