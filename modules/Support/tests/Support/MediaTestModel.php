<?php

namespace Modules\Support\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MediaTestModel extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_test_models';

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('single')->singleFile();
        $this->addMediaCollection('images')->acceptsMimeTypes(['image/png']);
    }
}
