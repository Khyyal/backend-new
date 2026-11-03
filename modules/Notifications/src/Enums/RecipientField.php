<?php

namespace Modules\Notifications\Enums;

/**
 * Recipient data a WhatsApp template variable can be bound to.
 */
enum RecipientField: string
{
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case FullName = 'full_name';
    case Phone = 'phone';
    case StaffName = 'staff_name';
    case CenterName = 'center_name';

    public function appliesTo(CampaignAudience $audience): bool
    {
        return match ($this) {
            self::FirstName, self::LastName, self::FullName => $audience === CampaignAudience::Clients,
            self::StaffName, self::CenterName => $audience === CampaignAudience::Centers,
            self::Phone => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(CampaignAudience $audience): array
    {
        return array_values(array_map(
            fn (self $field): string => $field->value,
            array_filter(self::cases(), fn (self $field): bool => $field->appliesTo($audience)),
        ));
    }
}
