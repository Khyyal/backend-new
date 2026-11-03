<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\Notification;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $fallback = (string) config('app.fallback_locale');

        return [
            'id' => $this->id,
            /** @var string */
            'type' => $this->data['type'] ?? null,
            /** @var string|null Title in the request locale. */
            'title' => $this->localized('title', $locale, $fallback),
            /** @var string|null Body in the request locale. */
            'body' => $this->localized('body', $locale, $fallback),
            /** @var array<string, mixed> Raw payload: every locale plus deep-link ids. */
            'payload' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    private function localized(string $key, string $locale, string $fallback): ?string
    {
        $value = $this->data[$key] ?? null;

        if (! is_array($value)) {
            return $value;
        }

        return $value[$locale] ?? $value[$fallback] ?? reset($value) ?: null;
    }
}
