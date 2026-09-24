<?php

namespace Modules\Clients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Support\Concerns\ActionActor;
use Modules\Support\Concerns\HasCity;
use Modules\Support\Concerns\HasDevices;
use Modules\Support\Concerns\Rater;

class Client extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, HasDevices, Rater, ActionActor, HasCity;



    protected string $guard_name = 'client';



    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'gender',
        'city_id',
    ];



    /// need onboarding where name is null
    public function getNeedsOnboardingAttribute(): bool
    {
        return $this->first_name === null || $this->last_name === null;
    }
}
