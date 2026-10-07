<?php

namespace Tests\Unit\Phone;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\Enums\PhoneNumberType;
use App\Exceptions\Phone\InvalidPhoneNumberException;
use App\Exceptions\Phone\PhoneCountryMismatchException;
use App\Exceptions\Phone\UnsupportedPhoneCountryException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phone handling is authentication-critical, so it gets multi-country coverage.
 *
 * Expected values are derived from libphonenumber's own example numbers rather
 * than hard-coded strings: a hand-written expectation could agree with a broken
 * implementation while disagreeing with the numbering plan.
 */
class LibPhoneNumberServiceTest extends TestCase
{
    public function test_the_container_resolves_the_contract(): void
    {
        $service = app(PhoneNumberServiceInterface::class);

        $this->assertInstanceOf(PhoneNumberServiceInterface::class, $service);

        // Singleton: libphonenumber metadata is expensive to build.
        $this->assertSame($service, app(PhoneNumberServiceInterface::class));
    }

    #[DataProvider('regions')]
    public function test_national_and_international_forms_normalise_to_the_same_e164(string $region): void
    {
        $util = PhoneNumberUtil::getInstance();
        $example = $util->getExampleNumber($region);

        $this->assertNotNull($example, "libphonenumber has no example number for {$region}");

        $national = $util->format($example, PhoneNumberFormat::NATIONAL);
        $e164 = $util->format($example, PhoneNumberFormat::E164);

        $service = app(PhoneNumberServiceInterface::class);

        $this->assertSame($e164, $service->normalize($national, $region), "{$region}: national form");
        $this->assertSame($e164, $service->normalize($e164), "{$region}: already E.164");
        $this->assertSame($e164, $service->normalize($e164, $region), "{$region}: E.164 plus matching region");
    }

    public static function regions(): array
    {
        return [
            'Egypt' => ['EG'],
            'Saudi Arabia' => ['SA'],
            'United Arab Emirates' => ['AE'],
            'United Kingdom' => ['GB'],
            'United States' => ['US'],
            'Canada' => ['CA'],
        ];
    }

    public function test_differently_formatted_egyptian_numbers_resolve_to_one_identity(): void
    {
        // The core anti-duplicate-account guarantee: trunk prefix, spacing,
        // dashes, IDD prefix and E.164 must all collapse to one value.
        $service = app(PhoneNumberServiceInterface::class);

        $variants = [
            '01012345678',
            '0101 234 5678',
            '0101-234-5678',
            '+201012345678',
            '00201012345678',
        ];

        foreach ($variants as $variant) {
            $this->assertSame(
                '+201012345678',
                $service->normalize($variant, 'EG'),
                "Variant [{$variant}] did not normalise to the canonical E.164 value",
            );
        }
    }

    public function test_normalisation_is_idempotent(): void
    {
        $service = app(PhoneNumberServiceInterface::class);

        $once = $service->normalize('01012345678', 'EG');

        $this->assertSame($once, $service->normalize($once));
        $this->assertSame($once, $service->normalize($once, 'EG'));
    }

    public function test_it_reports_region_and_calling_code(): void
    {
        $service = app(PhoneNumberServiceInterface::class);

        $egypt = $service->parse('+201012345678');

        $this->assertSame('EG', $egypt->countryIso2);
        $this->assertSame('+20', $egypt->callingCode);
        $this->assertSame('+201012345678', $egypt->e164);

        $saudi = $service->parse('+966512345678');

        $this->assertSame('SA', $saudi->countryIso2);
        $this->assertSame('+966', $saudi->callingCode);
    }

    public function test_a_number_from_another_country_is_rejected_when_a_region_is_supplied(): void
    {
        // Stops an Egyptian number being submitted under the "SA" selector.
        $this->expectException(PhoneCountryMismatchException::class);

        app(PhoneNumberServiceInterface::class)->parse('+201012345678', 'SA');
    }

    public function test_a_shared_calling_code_is_not_country_identity(): void
    {
        $util = PhoneNumberUtil::getInstance();
        $service = app(PhoneNumberServiceInterface::class);

        $us = $util->format($util->getExampleNumber('US'), PhoneNumberFormat::E164);
        $ca = $util->format($util->getExampleNumber('CA'), PhoneNumberFormat::E164);

        // Both are +1, so the dialing code alone cannot identify the country.
        $this->assertSame('+1', substr($us, 0, 2));
        $this->assertSame('+1', substr($ca, 0, 2));
        $this->assertNotSame($us, $ca);

        $this->assertSame('US', $service->parse($us)->countryIso2);
        $this->assertSame('CA', $service->parse($ca)->countryIso2);

        $this->expectException(PhoneCountryMismatchException::class);
        $service->parse($us, 'CA');
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_numbers_are_rejected(string $invalid): void
    {
        $this->assertFalse(app(PhoneNumberServiceInterface::class)->isValid($invalid, 'EG'));
    }

    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'letters' => ['not-a-number'],
            'too short' => ['123'],
            'too long' => ['+2010123456789012345678'],
            'incomplete' => ['+2010'],
        ];
    }

    public function test_unknown_regions_are_rejected(): void
    {
        $service = app(PhoneNumberServiceInterface::class);

        $this->assertFalse($service->isSupportedRegion('ZZ'));

        $this->expectException(UnsupportedPhoneCountryException::class);

        $service->parse('01012345678', 'ZZ');
    }

    public function test_supported_regions_exclude_the_non_geographic_region(): void
    {
        // "001" is libphonenumber's pseudo-region for +800-style numbers, which
        // cannot be mapped to a country and so cannot be an account phone.
        $regions = app(PhoneNumberServiceInterface::class)->supportedRegions();

        $this->assertNotContains('001', $regions);
        $this->assertContains('EG', $regions);
        $this->assertContains('SA', $regions);
        $this->assertContains('US', $regions);
    }

    public function test_mobile_numbers_are_reported_as_sms_capable(): void
    {
        $data = app(PhoneNumberServiceInterface::class)->parse('+201012345678');

        $this->assertTrue($data->isMobile());
        $this->assertTrue($data->canReceiveSms());
        $this->assertContains($data->type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE]);
    }

    public function test_the_masked_form_hides_the_middle_digits(): void
    {
        $masked = app(PhoneNumberServiceInterface::class)->parse('+201012345678')->masked();

        $this->assertStringStartsWith('+2010', $masked);
        $this->assertStringEndsWith('678', $masked);
        $this->assertStringNotContainsString('1234', $masked);
    }

    public function test_exception_messages_never_contain_the_raw_number(): void
    {
        try {
            app(PhoneNumberServiceInterface::class)->parse('+2010123456789', 'EG');

            $this->fail('Expected an InvalidPhoneNumberException');
        } catch (InvalidPhoneNumberException $e) {
            // Exception messages are logged; a phone number is an identifier and
            // must be masked rather than written to disk.
            $this->assertStringNotContainsString('2010123456789', $e->getMessage());
        }
    }
}
