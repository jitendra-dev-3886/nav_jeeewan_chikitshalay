<?php

namespace App\Models;

class Service extends ClinicModel
{
    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
