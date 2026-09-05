<?php

namespace App\Models;

class Redirect extends ClinicModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
