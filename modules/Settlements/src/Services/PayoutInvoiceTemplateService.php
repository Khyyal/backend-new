<?php

namespace Modules\Settlements\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Modules\Support\Exceptions\InvalidTemporaryMediaException;
use Modules\Support\Models\TemporaryUpload;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Saves the invoice template and its logo, and resolves the logo of an
 * unsaved preview.
 */
class PayoutInvoiceTemplateService
{
    public const LOGO_MIME_TYPES = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly TemporaryMediaService $temporaryMedia,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  template fields
     * @param  array<string, mixed>  $validated  request input; the logo only changes when it has a `logo` key
     *
     * @throws InvalidTemporaryMediaException
     */
    public function update(PayoutInvoiceTemplate $template, array $attributes, array $validated, Model $actor): PayoutInvoiceTemplate
    {
        DB::transaction(function () use ($template, $attributes, $validated, $actor): void {
            $template->applyAttributes($attributes)->save();

            if (! array_key_exists('logo', $validated)) {
                return;
            }

            $current = $template->getFirstMedia('logo');

            if ($validated['logo'] === null) {
                if ($current !== null) {
                    DB::afterCommit(fn () => $this->mediaService->detach($template, $current));
                }

                return;
            }

            if ($current?->uuid !== $validated['logo']) {
                try {
                    // The collection is singleFile: promoting replaces the previous logo.
                    $this->temporaryMedia->promote($validated['logo'], $actor, $template, 'logo');
                } catch (InvalidArgumentException) {
                    throw new InvalidTemporaryMediaException;
                }
            }
        });

        return $template->refresh()->load('media');
    }

    /**
     * The logo an unsaved preview should draw: null keeps the saved one, false
     * draws none, otherwise the actor's own temporary JPEG or PNG upload.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws InvalidTemporaryMediaException
     */
    public function previewLogo(PayoutInvoiceTemplate $template, array $validated, Model $actor): Media|false|null
    {
        if (! array_key_exists('logo', $validated)) {
            return null;
        }

        if ($validated['logo'] === null) {
            return false;
        }

        if ($template->getFirstMedia('logo')?->uuid === $validated['logo']) {
            return null;
        }

        $media = Media::query()->where('uuid', $validated['logo'])->first();
        $upload = $media !== null && $media->model_type === (new TemporaryUpload)->getMorphClass()
            ? TemporaryUpload::query()->find($media->model_id)
            : null;

        if ($upload === null || ! $upload->isOwnedBy($actor) || $upload->isExpired() || ! in_array($media->mime_type, self::LOGO_MIME_TYPES, true)) {
            throw new InvalidTemporaryMediaException;
        }

        return $media;
    }
}
