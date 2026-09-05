<?php

namespace App\Models;

class AvailabilityRule extends ClinicModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
