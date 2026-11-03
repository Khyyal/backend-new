<?php

namespace Modules\Notifications\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Notifications\Enums\CampaignActionType;
use Modules\Notifications\Enums\CampaignAudience;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignTargetMode;
use Modules\Notifications\Enums\RecipientField;
use Modules\Notifications\Enums\TemplateVariableSource;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * Create (POST) or update (PUT/PATCH) a campaign. On update, missing fields keep their current value.
 */
class NotificationCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->campaign();

        return $campaign !== null
            ? Gate::forUser($this->user())->allows('update', $campaign)
            : Gate::forUser($this->user())->allows('create', NotificationCampaign::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $campaign = $this->campaign();
        $presence = $campaign !== null ? 'sometimes' : 'required';
        $channel = $this->channel();
        $isPush = $channel === CampaignChannel::Push;
        $contentPresence = $campaign !== null && ! $this->has('channel') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'channel' => [$presence, Rule::enum(CampaignChannel::class)],
            'audience' => [$presence, Rule::enum(CampaignAudience::class)],
            'target_mode' => [$presence, Rule::enum(CampaignTargetMode::class)],
            /** Client ids or center ids depending on `audience`; required when `target_mode` is `selected`. */
            'target_ids' => ['sometimes', 'array', 'max:5000'],
            'target_ids.*' => ['integer', 'min:1', 'distinct'],

            'title' => $isPush ? [$contentPresence, 'array'] : ['prohibited'],
            'title.en' => $isPush ? ['required_with:title', 'string', 'max:255'] : [],
            'title.ar' => $isPush ? ['required_with:title', 'string', 'max:255'] : [],
            'body' => $isPush ? [$contentPresence, 'array'] : ['prohibited'],
            'body.en' => $isPush ? ['required_with:body', 'string', 'max:1000'] : [],
            'body.ar' => $isPush ? ['required_with:body', 'string', 'max:1000'] : [],
            'action_type' => $isPush ? ['sometimes', Rule::enum(CampaignActionType::class)] : ['prohibited'],
            'action_target_id' => $isPush ? ['nullable', 'integer', 'min:1', 'required_if:action_type,center,service'] : ['prohibited'],
            'action_url' => $isPush ? ['nullable', 'url:http,https', 'max:2048', 'required_if:action_type,url'] : ['prohibited'],

            'whatsapp_template_id' => $isPush ? ['prohibited'] : [
                $contentPresence, 'integer',
                Rule::exists('whatsapp_templates', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            /** One binding per template variable, in order: `{source: fixed, value}` or `{source: recipient, field}`. */
            'template_parameters' => $isPush ? ['prohibited'] : [$contentPresence, 'array'],
            'template_parameters.*.source' => $isPush ? [] : ['required', Rule::enum(TemplateVariableSource::class)],
            'template_parameters.*.value' => $isPush ? [] : ['required_if:template_parameters.*.source,fixed', 'nullable', 'string', 'max:1000'],
            'template_parameters.*.field' => $isPush ? [] : ['required_if:template_parameters.*.source,recipient', 'nullable', Rule::enum(RecipientField::class)],

            'scheduled_at' => ['nullable', 'date', 'after:now', 'prohibited_if:send_now,true,1'],
            'send_now' => $campaign !== null ? ['prohibited'] : ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateTargets($validator),
            fn (Validator $validator) => $this->validateTemplateParameters($validator),
        ];
    }

    private function validateTargets(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $campaign = $this->campaign();
        $mode = CampaignTargetMode::tryFrom((string) $this->input('target_mode', $campaign?->target_mode?->value));
        $audience = CampaignAudience::tryFrom((string) $this->input('audience', $campaign?->audience?->value));

        if ($mode !== CampaignTargetMode::Selected || $audience === null) {
            return;
        }

        $ids = $this->has('target_ids') ? array_map('intval', (array) $this->input('target_ids')) : ($campaign?->targetIds() ?? []);

        if ($ids === []) {
            $validator->errors()->add('target_ids', __('notifications::campaigns.target_ids_required'));

            return;
        }

        $model = $audience->targetModel();

        if ($model::query()->whereKey($ids)->count() !== count(array_unique($ids))) {
            $validator->errors()->add('target_ids', __('notifications::campaigns.target_ids_invalid'));
        }
    }

    private function validateTemplateParameters(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || $this->channel() !== CampaignChannel::WhatsApp) {
            return;
        }

        $campaign = $this->campaign();
        $template = WhatsAppTemplate::query()->find($this->input('whatsapp_template_id', $campaign?->whatsapp_template_id));
        $parameters = (array) $this->input('template_parameters', $campaign?->template_parameters ?? []);
        $audience = CampaignAudience::tryFrom((string) $this->input('audience', $campaign?->audience?->value));

        if ($template === null || $audience === null) {
            return;
        }

        if (count($parameters) !== $template->variablesCount()) {
            $validator->errors()->add('template_parameters', __('notifications::campaigns.template_parameters_count', ['count' => $template->variablesCount()]));

            return;
        }

        foreach (array_values($parameters) as $index => $parameter) {
            $field = RecipientField::tryFrom((string) ($parameter['field'] ?? ''));

            if (($parameter['source'] ?? null) === TemplateVariableSource::Recipient->value && $field !== null && ! $field->appliesTo($audience)) {
                $validator->errors()->add("template_parameters.{$index}.field", __('notifications::campaigns.field_not_for_audience'));
            }
        }
    }

    private function channel(): ?CampaignChannel
    {
        return CampaignChannel::tryFrom((string) $this->input('channel', $this->campaign()?->channel?->value));
    }

    private function campaign(): ?NotificationCampaign
    {
        $campaign = $this->route('notification_campaign');

        return $campaign instanceof NotificationCampaign ? $campaign : null;
    }
}
