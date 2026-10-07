<?php

namespace Tests\Unit\Phone;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\Enums\PhoneNumberType;
use Tests\TestCase;

/**
 * The metadata surface of the phone service.
 *
 * `parse()`/`normalize()` answer "is this number valid?"; these two answer "what
 * does this country's numbering plan look like?". The country seeder depends on
 * both, so a silent regression here would seed wrong calling codes into the table
 * that every stored phone number points at.
 */
class PhoneNumberMetadataTest extends TestCase
{
    private PhoneNumberServiceInterface $phone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phone = app(PhoneNumberServiceInterface::class);
    }

    public function test_calling_codes_are_returned_with_a_leading_plus(): void
    {
        $this->assertSame('+20', $this->phone->callingCode('EG'));
        $this->assertSame('+966', $this->phone->callingCode('SA'));
        $this->assertSame('+1', $this->phone->callingCode('US'));
    }

    public function test_a_lowercase_region_is_accepted(): void
    {
        // The `countries.iso2` column is upper-cased on write, but a seeder or a
        // command reading a config value should not have to care.
        $this->assertSame('+20', $this->phone->callingCode('eg'));
        $this->assertSame('+20', $this->phone->callingCode(' EG '));
    }

    public function test_unknown_and_non_geographic_regions_have_no_calling_code(): void
    {
        $this->assertNull($this->phone->callingCode('ZZ'));
        $this->assertNull($this->phone->callingCode(''));

        // "001" is libphonenumber's pseudo-region for non-geographic numbers such
        // as +800. It has a dialing prefix but is not a country, and treating it
        // as one would create a country row no user can belong to.
        $this->assertNull($this->phone->callingCode('001'));
    }

    public function test_every_supported_region_has_a_well_formed_calling_code(): void
    {
        $regions = $this->phone->supportedRegions();

        $this->assertNotEmpty($regions);
        $this->assertNotContains('001', $regions);

        foreach ($regions as $region) {
            $callingCode = $this->phone->callingCode($region);

            $this->assertIsString($callingCode, "Region {$region} has no calling code");
            $this->assertMatchesRegularExpression('/^\+[0-9]{1,4}$/', $callingCode, $region);
        }
    }

    public function test_an_example_number_parses_back_to_its_own_region(): void
    {
        foreach (['EG', 'SA', 'AE', 'GB', 'US'] as $region) {
            $example = $this->phone->exampleNumber($region);

            $this->assertNotNull($example, "No mobile example for {$region}");

            // The example is only useful if it survives the validation a real
            // submission goes through. Asserting on the parsed region rather than
            // on a hard-coded string keeps the expectation tied to the numbering
            // plan instead of to the library version.
            $this->assertSame(
                $region,
                $this->phone->parse($example, $region)->countryIso2,
                "Example for {$region} did not resolve back to {$region}",
            );
        }
    }

    public function test_the_example_carries_the_countrys_calling_code(): void
    {
        $example = $this->phone->exampleNumber('EG');

        $this->assertStringStartsWith('+20', (string) $example);
    }

    public function test_an_example_can_be_requested_for_another_number_type(): void
    {
        // Egypt publishes a mobile example but the default must not be assumed:
        // a caller asking for a fixed line gets one, or null.
        $this->assertNotNull($this->phone->exampleNumber('US', PhoneNumberType::FIXED_LINE));
    }

    public function test_there_is_no_example_for_a_region_without_a_numbering_plan(): void
    {
        $this->assertNull($this->phone->exampleNumber('ZZ'));
        $this->assertNull($this->phone->exampleNumber('001'));
    }
}
