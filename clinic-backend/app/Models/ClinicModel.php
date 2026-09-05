<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class ClinicModel extends Model
{
    protected $guarded = ['id'];
}
