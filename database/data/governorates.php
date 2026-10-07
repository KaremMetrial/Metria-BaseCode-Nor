<?php

/**
 * First-level administrative divisions for the markets we operate in.
 *
 * Unlike countries, this cannot be generated: no numbering plan knows that Egypt
 * has 27 governorates, and no locale dataset ships the hierarchy. It is a closed,
 * slowly-changing set, so it is declared here.
 *
 * Codes are the real ISO 3166-2 subdivision codes (`EG-C`, `SA-01`, `AE-DU`), not
 * invented slugs. That matters twice: `UNIQUE(country_id, code)` stops the same
 * division being imported twice, and an external data source can be matched to
 * our rows without a hand-maintained lookup table.
 *
 * The local word for the division (governorate / region / emirate) is not stored
 * per row here -- it is derived from the country by
 * App\Enums\GovernorateType::forCountry(), so it cannot drift from the country
 * it belongs to.
 *
 * `sample_cities` is deliberate scope: the capitals and the handful of cities an
 * address form is actually tested against. Cities are the largest and
 * fastest-changing of the three tables, and an organisation imports its own
 * coverage list anyway -- shipping a fabricated city census would be worse than
 * shipping none.
 */
return [

    'EG' => [
        ['code' => 'EG-ALX', 'en' => 'Alexandria', 'ar' => 'الإسكندرية'],
        ['code' => 'EG-ASN', 'en' => 'Aswan', 'ar' => 'أسوان'],
        ['code' => 'EG-AST', 'en' => 'Asyut', 'ar' => 'أسيوط'],
        ['code' => 'EG-BA', 'en' => 'Red Sea', 'ar' => 'البحر الأحمر'],
        ['code' => 'EG-BH', 'en' => 'Beheira', 'ar' => 'البحيرة'],
        ['code' => 'EG-BNS', 'en' => 'Beni Suef', 'ar' => 'بني سويف'],
        ['code' => 'EG-C', 'en' => 'Cairo', 'ar' => 'القاهرة'],
        ['code' => 'EG-DK', 'en' => 'Dakahlia', 'ar' => 'الدقهلية'],
        ['code' => 'EG-DT', 'en' => 'Damietta', 'ar' => 'دمياط'],
        ['code' => 'EG-FYM', 'en' => 'Faiyum', 'ar' => 'الفيوم'],
        ['code' => 'EG-GH', 'en' => 'Gharbia', 'ar' => 'الغربية'],
        ['code' => 'EG-GZ', 'en' => 'Giza', 'ar' => 'الجيزة'],
        ['code' => 'EG-IS', 'en' => 'Ismailia', 'ar' => 'الإسماعيلية'],
        ['code' => 'EG-JS', 'en' => 'South Sinai', 'ar' => 'جنوب سيناء'],
        ['code' => 'EG-KB', 'en' => 'Qalyubia', 'ar' => 'القليوبية'],
        ['code' => 'EG-KFS', 'en' => 'Kafr El Sheikh', 'ar' => 'كفر الشيخ'],
        ['code' => 'EG-KN', 'en' => 'Qena', 'ar' => 'قنا'],
        ['code' => 'EG-LX', 'en' => 'Luxor', 'ar' => 'الأقصر'],
        ['code' => 'EG-MN', 'en' => 'Minya', 'ar' => 'المنيا'],
        ['code' => 'EG-MNF', 'en' => 'Monufia', 'ar' => 'المنوفية'],
        ['code' => 'EG-MT', 'en' => 'Matrouh', 'ar' => 'مطروح'],
        ['code' => 'EG-PTS', 'en' => 'Port Said', 'ar' => 'بورسعيد'],
        ['code' => 'EG-SHG', 'en' => 'Sohag', 'ar' => 'سوهاج'],
        ['code' => 'EG-SHR', 'en' => 'Sharqia', 'ar' => 'الشرقية'],
        ['code' => 'EG-SIN', 'en' => 'North Sinai', 'ar' => 'شمال سيناء'],
        ['code' => 'EG-SUZ', 'en' => 'Suez', 'ar' => 'السويس'],
        ['code' => 'EG-WAD', 'en' => 'New Valley', 'ar' => 'الوادي الجديد'],
    ],

    'SA' => [
        ['code' => 'SA-01', 'en' => 'Riyadh', 'ar' => 'منطقة الرياض'],
        ['code' => 'SA-02', 'en' => 'Makkah', 'ar' => 'منطقة مكة المكرمة'],
        ['code' => 'SA-03', 'en' => 'Al Madinah', 'ar' => 'منطقة المدينة المنورة'],
        ['code' => 'SA-04', 'en' => 'Eastern Province', 'ar' => 'المنطقة الشرقية'],
        ['code' => 'SA-05', 'en' => 'Al Qassim', 'ar' => 'منطقة القصيم'],
        ['code' => 'SA-06', 'en' => "Ha'il", 'ar' => 'منطقة حائل'],
        ['code' => 'SA-07', 'en' => 'Tabuk', 'ar' => 'منطقة تبوك'],
        ['code' => 'SA-08', 'en' => 'Northern Borders', 'ar' => 'منطقة الحدود الشمالية'],
        ['code' => 'SA-09', 'en' => 'Jazan', 'ar' => 'منطقة جازان'],
        ['code' => 'SA-10', 'en' => 'Najran', 'ar' => 'منطقة نجران'],
        ['code' => 'SA-11', 'en' => 'Al Bahah', 'ar' => 'منطقة الباحة'],
        ['code' => 'SA-12', 'en' => 'Al Jawf', 'ar' => 'منطقة الجوف'],
        ['code' => 'SA-14', 'en' => 'Asir', 'ar' => 'منطقة عسير'],
    ],

    'AE' => [
        ['code' => 'AE-AJ', 'en' => 'Ajman', 'ar' => 'عجمان'],
        ['code' => 'AE-AZ', 'en' => 'Abu Dhabi', 'ar' => 'أبوظبي'],
        ['code' => 'AE-DU', 'en' => 'Dubai', 'ar' => 'دبي'],
        ['code' => 'AE-FU', 'en' => 'Fujairah', 'ar' => 'الفجيرة'],
        ['code' => 'AE-RK', 'en' => 'Ras Al Khaimah', 'ar' => 'رأس الخيمة'],
        ['code' => 'AE-SH', 'en' => 'Sharjah', 'ar' => 'الشارقة'],
        ['code' => 'AE-UQ', 'en' => 'Umm Al Quwain', 'ar' => 'أم القيوين'],
    ],

];
