<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Support\Concerns\SyncsParentSoftDeletes;

class RecreationalRiding extends Model
{

    use HasFactory, SoftDeletes , SyncsParentSoftDeletes;


    public $timestamps = false;

    protected function getParentForSync(): ?Model
    {
        return $this->service;
    }
}
