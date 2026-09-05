<?php

namespace App\Models;

class ScheduleBlock extends ClinicModel
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
