<?php

namespace App\Contracts\Phone;

use App\DTOs\Phone\PhoneNumberData;
use App\Enums\PhoneNumberType;
use App\Exceptions\Phone\InvalidPhoneNumberException;
use App\Exceptions\Phone\PhoneCountryMismatchException;
use App\Exceptions\Phone\UnsupportedPhoneCountryException;

/**
 * Authoritative phone parsing, validation and normalization.
 *
 * Everything that touches a phone number (registration, login, OTP, phone
 * change, uniqueness checks, SMS delivery) goes through this contract, so the
 * application never contains a hand-maintained international-number regex.
 *
 * The implementation is backed by libphonenumber metadata, which is the
 * authority on numbering plans. The `countries` table is *not*: it only decides
 * which countries the product operates in.
 */
interface PhoneNumberServiceInterface
{
    /**
     * Parse and validate a number, returning its canonical forms.
     *
     * `$regionIso2` is the ISO 3166-1 alpha-2 region used to interpret a
     * national-format number (e.g. "01012345678" + "EG"). It is ignored for
     * numbers that already carry a country code -- but the two must then agree,
     * otherwise PhoneCountryMismatchException is thrown.
     *
     * @throws InvalidPhoneNumberException
     * @throws UnsupportedPhoneCountryException
     * @throws PhoneCountryMismatchException
     */
    public function parse(string $number, ?string $regionIso2 = null): PhoneNumberData;

    /**
     * Canonical E.164 form, e.g. "+201012345678".
     *
     * This is the only representation that may be compared, stored or used in
     * an OTP cache key.
     *
     * @throws InvalidPhoneNumberException
     * @throws UnsupportedPhoneCountryException
     * @throws PhoneCountryMismatchException
     */
    public function normalize(string $number, ?string $regionIso2 = null): string;

    /**
     * Non-throwing validity check.
     */
    public function isValid(string $number, ?string $regionIso2 = null): bool;

    /**
     * Whether the region is a known phone-numbering region at all.
     */
    public function isSupportedRegion(string $regionIso2): bool;

    /**
     * Display calling code for a region, e.g. "+20", or null when the region has
     * no numbering plan.
     *
     * Used to populate `countries.calling_code` from the numbering-plan authority
     * instead of a hand-maintained list that silently rots.
     */
    public function callingCode(string $regionIso2): ?string;

    /**
     * A valid example number for a region in international format, or null when
     * the numbering plan publishes none for that type.
     *
     * Used as a UI input hint (`countries.phone_example`). The number is
     * guaranteed to parse, so a form that pre-fills it always validates --
     * which is exactly what an example is for.
     */
    public function exampleNumber(string $regionIso2, PhoneNumberType $type = PhoneNumberType::MOBILE): ?string;

    /**
     * All known geographic region codes (excludes the non-geographic "001").
     *
     * @return list<string>
     */
    public function supportedRegions(): array;
}
