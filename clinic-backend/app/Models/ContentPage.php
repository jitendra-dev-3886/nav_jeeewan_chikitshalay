<?php

namespace App\Models;

class ContentPage extends ClinicModel
{
    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
