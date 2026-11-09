<?php

namespace Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('file', 'file', 'The file (multipart/form-data). Max 10 MB (`SUPPORT_MEDIA_MAX_SIZE_KB`); JPEG, PNG, WebP or PDF, checked by content.')]
class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('support-media.max_size_kb'),
                'mimetypes:'.implode(',', config('support-media.allowed_mimetypes')),
            ],
        ];
    }
}
