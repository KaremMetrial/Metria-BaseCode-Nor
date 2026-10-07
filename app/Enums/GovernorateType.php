<?php

namespace App\Enums;

/**
 * What a first-level administrative division is actually called locally.
 *
 * The table and models stay named `Governorate` for consistency, but storing the
 * real word keeps the data honest: Egypt has governorates, the UAE has emirates,
 * Saudi Arabia has regions and the US has states.
 */
enum GovernorateType: string
{
    case GOVERNORATE = 'governorate';
    case STATE = 'state';
    case PROVINCE = 'province';
    case EMIRATE = 'emirate';
    case REGION = 'region';

    public function label(): string
    {
        return __("enums.governorate_type.{$this->value}");
    }

    /**
     * Local division type for a country, used when importing reference data so
     * seeded rows describe themselves accurately.
     *
     * Returns null when the country is not in the map rather than guessing: a
     * wrong label is worse than an absent one.
     */
    public static function forCountry(string $countryIso2): ?self
    {
        return match (strtoupper($countryIso2)) {
            'EG', 'IQ', 'YE', 'JO', 'KW', 'OM' => self::GOVERNORATE,
            'SA', 'MA', 'DZ', 'TN', 'LY' => self::REGION,
            'AE', 'QA', 'BH' => self::EMIRATE,
            'US', 'GB', 'AU', 'IN', 'CA' => self::STATE,
            'TR', 'FR', 'IT', 'ES', 'NL', 'ID' => self::PROVINCE,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
