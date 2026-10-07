<?php

namespace App\Services\Phone;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\DTOs\Phone\PhoneNumberData;
use App\Enums\PhoneNumberType;
use App\Exceptions\Phone\InvalidPhoneNumberException;
use App\Exceptions\Phone\PhoneCountryMismatchException;
use App\Exceptions\Phone\UnsupportedPhoneCountryException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType as LibPhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * PhoneNumberServiceInterface backed by Google's libphonenumber metadata.
 *
 * This is the only class in the application allowed to reference libphonenumber
 * types; everything else sees PhoneNumberData and our own enums.
 */
final class LibPhoneNumberService implements PhoneNumberServiceInterface
{
    /**
     * libphonenumber's pseudo-region for non-geographic numbers (+800 etc.).
     */
    private const NON_GEOGRAPHICAL_REGION = '001';

    public function __construct(private readonly PhoneNumberUtil $util) {}

    public function parse(string $number, ?string $regionIso2 = null): PhoneNumberData
    {
        $number = trim($number);
        $region = $this->normalizeRegion($regionIso2);

        if ($region !== null && ! $this->isSupportedRegion($region)) {
            throw UnsupportedPhoneCountryException::make($region);
        }

        try {
            $parsed = $this->util->parse($number, $region);
        } catch (NumberParseException $e) {
            throw InvalidPhoneNumberException::make($region, $e);
        }

        if (! $this->util->isValidNumber($parsed)) {
            throw InvalidPhoneNumberException::make($region);
        }

        $parsedRegion = $this->util->getRegionCodeForNumber($parsed);

        // A number carrying its own country code wins over the hint, so when
        // both are present they must agree. This is what stops an Egyptian
        // number being submitted under the "SA" country selector.
        if ($region !== null && $parsedRegion !== null && $parsedRegion !== $region) {
            throw PhoneCountryMismatchException::make($region);
        }

        // Fall back to the requested region for numbers whose numbering plan is
        // non-geographic; without a region we cannot populate phone_country_id,
        // so such a number is not usable as an account identifier.
        $resolvedRegion = $parsedRegion ?? $region;

        if ($resolvedRegion === null) {
            throw InvalidPhoneNumberException::make(null);
        }

        $callingCode = $this->util->getCountryCodeForRegion($resolvedRegion);

        return new PhoneNumberData(
            e164: $this->util->format($parsed, PhoneNumberFormat::E164),
            national: $this->util->format($parsed, PhoneNumberFormat::NATIONAL),
            international: $this->util->format($parsed, PhoneNumberFormat::INTERNATIONAL),
            countryIso2: $resolvedRegion,
            callingCode: $callingCode > 0 ? '+'.$callingCode : '',
            type: $this->mapType($this->util->getNumberType($parsed)),
        );
    }

    public function normalize(string $number, ?string $regionIso2 = null): string
    {
        return $this->parse($number, $regionIso2)->e164;
    }

    public function isValid(string $number, ?string $regionIso2 = null): bool
    {
        try {
            $this->parse($number, $regionIso2);

            return true;
        } catch (InvalidPhoneNumberException|UnsupportedPhoneCountryException|PhoneCountryMismatchException) {
            return false;
        }
    }

    public function isSupportedRegion(string $regionIso2): bool
    {
        $region = strtoupper(trim($regionIso2));

        return $region !== self::NON_GEOGRAPHICAL_REGION
            && in_array($region, $this->util->getSupportedRegions(), true);
    }

    public function callingCode(string $regionIso2): ?string
    {
        $region = strtoupper(trim($regionIso2));

        // "001" is libphonenumber's pseudo-region for non-geographic numbers
        // (+800, +870...). It has a calling code but no country, so it must not
        // be mistaken for one.
        if ($region === '' || $region === self::NON_GEOGRAPHICAL_REGION) {
            return null;
        }

        $callingCode = $this->util->getCountryCodeForRegion($region);

        return $callingCode > 0 ? '+'.$callingCode : null;
    }

    public function exampleNumber(string $regionIso2, PhoneNumberType $type = PhoneNumberType::MOBILE): ?string
    {
        $region = strtoupper(trim($regionIso2));

        if (! $this->isSupportedRegion($region)) {
            return null;
        }

        $example = $this->util->getExampleNumberForType($region, $this->mapToLibraryType($type));

        // Deliberately no fallback to another type: an example that is not of
        // the requested type would train users to enter numbers the same
        // validation then rejects.
        return $example === null
            ? null
            : $this->util->format($example, PhoneNumberFormat::INTERNATIONAL);
    }

    public function supportedRegions(): array
    {
        $regions = array_values(array_diff($this->util->getSupportedRegions(), [self::NON_GEOGRAPHICAL_REGION]));
        sort($regions);

        return $regions;
    }

    private function normalizeRegion(?string $regionIso2): ?string
    {
        if ($regionIso2 === null) {
            return null;
        }

        $region = strtoupper(trim($regionIso2));

        return $region === '' ? null : $region;
    }

    /**
     * Map libphonenumber's native enum onto ours.
     *
     * Written as an explicit match rather than a value lookup: the library's
     * `PhoneNumberType` is an int-backed enum (not the int constants older
     * versions exposed), and any type we do not recognise must degrade to
     * UNKNOWN instead of throwing UnhandledMatchError at a user.
     */
    /**
     * The inverse of mapType(), for the two calls that must hand a type back to
     * the library (example-number lookup).
     *
     * UNKNOWN resolves to GENERAL_DESC behaviour in libphonenumber, which is the
     * only sane interpretation of "any valid number".
     */
    private function mapToLibraryType(PhoneNumberType $type): LibPhoneNumberType
    {
        return match ($type) {
            PhoneNumberType::FIXED_LINE => LibPhoneNumberType::FIXED_LINE,
            PhoneNumberType::MOBILE => LibPhoneNumberType::MOBILE,
            PhoneNumberType::FIXED_LINE_OR_MOBILE => LibPhoneNumberType::FIXED_LINE_OR_MOBILE,
            PhoneNumberType::TOLL_FREE => LibPhoneNumberType::TOLL_FREE,
            PhoneNumberType::PREMIUM_RATE => LibPhoneNumberType::PREMIUM_RATE,
            PhoneNumberType::SHARED_COST => LibPhoneNumberType::SHARED_COST,
            PhoneNumberType::VOIP => LibPhoneNumberType::VOIP,
            PhoneNumberType::PERSONAL_NUMBER => LibPhoneNumberType::PERSONAL_NUMBER,
            PhoneNumberType::PAGER => LibPhoneNumberType::PAGER,
            PhoneNumberType::UAN => LibPhoneNumberType::UAN,
            PhoneNumberType::VOICEMAIL => LibPhoneNumberType::VOICEMAIL,
            PhoneNumberType::UNKNOWN => LibPhoneNumberType::UNKNOWN,
        };
    }

    private function mapType(LibPhoneNumberType $libPhoneNumberType): PhoneNumberType
    {
        return match ($libPhoneNumberType) {
            LibPhoneNumberType::FIXED_LINE => PhoneNumberType::FIXED_LINE,
            LibPhoneNumberType::MOBILE => PhoneNumberType::MOBILE,
            LibPhoneNumberType::FIXED_LINE_OR_MOBILE => PhoneNumberType::FIXED_LINE_OR_MOBILE,
            LibPhoneNumberType::TOLL_FREE => PhoneNumberType::TOLL_FREE,
            LibPhoneNumberType::PREMIUM_RATE => PhoneNumberType::PREMIUM_RATE,
            LibPhoneNumberType::SHARED_COST => PhoneNumberType::SHARED_COST,
            LibPhoneNumberType::VOIP => PhoneNumberType::VOIP,
            LibPhoneNumberType::PERSONAL_NUMBER => PhoneNumberType::PERSONAL_NUMBER,
            LibPhoneNumberType::PAGER => PhoneNumberType::PAGER,
            LibPhoneNumberType::UAN => PhoneNumberType::UAN,
            LibPhoneNumberType::VOICEMAIL => PhoneNumberType::VOICEMAIL,
            default => PhoneNumberType::UNKNOWN,
        };
    }
}
