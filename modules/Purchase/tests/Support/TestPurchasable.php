<?php

namespace Modules\Purchase\Tests\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Purchase\Contracts\Purchasable;
use Modules\Purchase\Traits\IsPurchasable;

/**
 * Test-only model implementing Purchasable.
 * Uses an in-memory 'test_services' table created in tests.
 */
class TestPurchasable extends Model implements Purchasable
{
    use HasFactory;
    use IsPurchasable;

    protected $table = 'test_services';

    protected $fillable = [
        'id',
        'name',
        'price',
    ];

    public $timestamps = false;
}
