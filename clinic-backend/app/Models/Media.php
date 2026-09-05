<?php

namespace App\Models;

class Media extends ClinicModel
{
    protected $table = 'media';

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
