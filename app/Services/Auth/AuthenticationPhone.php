<?php

namespace App\Services\Auth;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\DTOs\Phone\PhoneNumberData;
use App\Enums\ErrorCode;
use App\Enums\PhoneNumberType;
use App\Exceptions\DomainException;
use App\Models\Country;

final class AuthenticationPhone
{
    public function __construct(private readonly PhoneNumberServiceInterface $phones) {}

    public function parse(int $countryId, string $number): PhoneNumberData
    {
        $country = Country::query()->whereKey($countryId)->where('is_active', true)->first();
        if (! $country) {
            throw new DomainException(ErrorCode::UNSUPPORTED_PHONE_COUNTRY);
        }
        $phone = $this->phones->parse($number, $country->iso2);
        if (config('otp.mobile_only') && ! in_array($phone->type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true)) {
            throw new DomainException(ErrorCode::PHONE_TYPE_NOT_ALLOWED);
        }

        return $phone;
    }
}
