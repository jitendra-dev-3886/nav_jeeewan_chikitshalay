<?php

namespace App\Models;

class Banner extends ClinicModel
{
    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
