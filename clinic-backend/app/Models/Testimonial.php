<?php

namespace App\Models;

class Testimonial extends ClinicModel
{
    protected function casts(): array
    {
        return ['published' => 'boolean', 'consent' => 'boolean'];
    }
}
