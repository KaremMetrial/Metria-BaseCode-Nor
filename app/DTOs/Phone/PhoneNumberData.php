<?php

namespace App\DTOs\Phone;

use App\Enums\PhoneNumberType;

/**
 * A parsed, validated phone number.
 *
 * Returning one value object instead of a dozen string-returning methods means
 * the number is parsed and validated exactly once per operation, and callers
 * cannot accidentally pass an unvalidated string to the next step.
 */
final readonly class PhoneNumberData
{
    /**
     * @param  string  $e164  Canonical storage form, e.g. "+201012345678".
     * @param  string  $countryIso2  ISO 3166-1 alpha-2 of the numbering plan.
     * @param  string  $callingCode  Display only, e.g. "+20".
     */
    public function __construct(
        public string $e164,
        public string $national,
        public string $international,
        public string $countryIso2,
        public string $callingCode,
        public PhoneNumberType $type,
    ) {}

    public function isMobile(): bool
    {
        return $this->type->isMobile();
    }

    public function canReceiveSms(): bool
    {
        return $this->type->canReceiveSms();
    }

    /**
     * Masked form for logs and UI, e.g. "+2010*****678".
     */
    public function masked(): string
    {
        $length = strlen($this->e164);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($this->e164, 0, 5)
            .str_repeat('*', max(0, $length - 8))
            .substr($this->e164, -3);
    }
}
