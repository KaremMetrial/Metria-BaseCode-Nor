<?php

namespace App\Enums;

/**
 * Phone number classification, mirroring libphonenumber's own taxonomy.
 *
 * Kept as our own enum so that business code never carries the library's integer
 * constants around (see the "no third-party types outside Services" rule).
 */
enum PhoneNumberType: string
{
    case FIXED_LINE = 'fixed_line';
    case MOBILE = 'mobile';
    case FIXED_LINE_OR_MOBILE = 'fixed_line_or_mobile';
    case TOLL_FREE = 'toll_free';
    case PREMIUM_RATE = 'premium_rate';
    case SHARED_COST = 'shared_cost';
    case VOIP = 'voip';
    case PERSONAL_NUMBER = 'personal_number';
    case PAGER = 'pager';
    case UAN = 'uan';
    case VOICEMAIL = 'voicemail';
    case UNKNOWN = 'unknown';

    /**
     * Whether the number is (or may be) a mobile number.
     *
     * `FIXED_LINE_OR_MOBILE` is included because some numbering plans cannot
     * distinguish the two; excluding it would wrongly reject valid numbers in
     * those countries.
     */
    public function isMobile(): bool
    {
        return match ($this) {
            self::MOBILE, self::FIXED_LINE_OR_MOBILE => true,
            default => false,
        };
    }

    /**
     * Whether the number can plausibly receive an SMS OTP.
     *
     * This is the default policy only. Phase 3 introduces an explicit,
     * configurable PhoneValidationPolicy because one country's "mobile"
     * definition does not transfer to another's.
     */
    public function canReceiveSms(): bool
    {
        return $this->isMobile();
    }
}
