<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\Models\TemporaryUpload;

class TemporaryUploadFactory extends Factory
{
    protected $model = TemporaryUpload::class;

    public function definition(): array
    {
        return [
            'uploader_id' => 1,
            'uploader_type' => 'uploader',
            'expires_at' => now()->addDay(),
        ];
    }

    public function forUploader(Model $uploader): static
    {
        return $this->state([
            'uploader_id' => $uploader->getKey(),
            'uploader_type' => $uploader->getMorphClass(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }
}
