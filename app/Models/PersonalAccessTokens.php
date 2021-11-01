<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalAccessTokens extends Model
{
    use HasFactory;
    protected $connection='olla_popular';
    protected $table='personal_access_tokens';
    public $timestamps=false;
}
