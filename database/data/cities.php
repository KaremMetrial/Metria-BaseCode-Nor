<?php

/**
 * Representative cities, keyed by their governorate's ISO 3166-2 code.
 *
 * Scope is intentional. Every Egyptian governorate contributes its capital, so
 * an address form is usable everywhere in the primary market, plus the well-known
 * cities a delivery or booking flow is actually tested against. This is not a
 * city census: a real deployment imports its own coverage list through
 * `POST /api/v1/admin/locations/cities`, which is why that endpoint exists.
 *
 * SEED-* codes are stable internal reference IDs. Never renumber them when
 * inserting, reordering, or translating cities. They are not official ISO codes.
 *
 * Coordinates are approximate centroids, good enough to centre a map. There are
 * no fabricated coordinates: a city we are not confident about is listed with
 * nulls, and the column is nullable precisely so that is expressible.
 */
return [

    // --- Cairo and the cities around it -------------------------------------
    'EG-C' => [
        ['code' => 'SEED-0001', 'en' => 'Cairo', 'ar' => 'القاهرة', 'lat' => 30.0444, 'lng' => 31.2357],
        ['code' => 'SEED-0002', 'en' => 'New Cairo', 'ar' => 'القاهرة الجديدة', 'lat' => 30.0300, 'lng' => 31.4700],
        ['code' => 'SEED-0003', 'en' => 'Nasr City', 'ar' => 'مدينة نصر', 'lat' => 30.0561, 'lng' => 31.3425],
        ['code' => 'SEED-0004', 'en' => 'Helwan', 'ar' => 'حلوان', 'lat' => 29.8414, 'lng' => 31.3342],
    ],

    'EG-GZ' => [
        ['code' => 'SEED-0005', 'en' => 'Giza', 'ar' => 'الجيزة', 'lat' => 30.0131, 'lng' => 31.2089],
        ['code' => 'SEED-0006', 'en' => '6th of October City', 'ar' => 'مدينة السادس من أكتوبر', 'lat' => 29.9285, 'lng' => 30.9188],
        ['code' => 'SEED-0007', 'en' => 'Sheikh Zayed City', 'ar' => 'مدينة الشيخ زايد', 'lat' => 30.0136, 'lng' => 30.9716],
    ],

    'EG-ALX' => [
        ['code' => 'SEED-0008', 'en' => 'Alexandria', 'ar' => 'الإسكندرية', 'lat' => 31.2001, 'lng' => 29.9187],
        ['code' => 'SEED-0009', 'en' => 'Borg El Arab', 'ar' => 'برج العرب', 'lat' => 30.9167, 'lng' => 29.5000],
    ],

    // --- Governorate capitals ----------------------------------------------
    'EG-ASN' => [
        ['code' => 'SEED-0010', 'en' => 'Aswan', 'ar' => 'أسوان', 'lat' => 24.0889, 'lng' => 32.8998],
    ],
    'EG-AST' => [
        ['code' => 'SEED-0011', 'en' => 'Asyut', 'ar' => 'أسيوط', 'lat' => 27.1809, 'lng' => 31.1837],
    ],
    'EG-BA' => [
        ['code' => 'SEED-0012', 'en' => 'Hurghada', 'ar' => 'الغردقة', 'lat' => 27.2579, 'lng' => 33.8116],
    ],
    'EG-BH' => [
        ['code' => 'SEED-0013', 'en' => 'Damanhour', 'ar' => 'دمنهور', 'lat' => 31.0341, 'lng' => 30.4682],
    ],
    'EG-BNS' => [
        ['code' => 'SEED-0014', 'en' => 'Beni Suef', 'ar' => 'بني سويف', 'lat' => 29.0661, 'lng' => 31.0994],
    ],
    'EG-DK' => [
        ['code' => 'SEED-0015', 'en' => 'Mansoura', 'ar' => 'المنصورة', 'lat' => 31.0409, 'lng' => 31.3785],
    ],
    'EG-DT' => [
        ['code' => 'SEED-0016', 'en' => 'Damietta', 'ar' => 'دمياط', 'lat' => 31.4165, 'lng' => 31.8133],
    ],
    'EG-FYM' => [
        ['code' => 'SEED-0017', 'en' => 'Faiyum', 'ar' => 'الفيوم', 'lat' => 29.3084, 'lng' => 30.8428],
    ],
    'EG-GH' => [
        ['code' => 'SEED-0018', 'en' => 'Tanta', 'ar' => 'طنطا', 'lat' => 30.7865, 'lng' => 31.0004],
        ['code' => 'SEED-0019', 'en' => 'El Mahalla El Kubra', 'ar' => 'المحلة الكبرى', 'lat' => 30.9706, 'lng' => 31.1669],
    ],
    'EG-IS' => [
        ['code' => 'SEED-0020', 'en' => 'Ismailia', 'ar' => 'الإسماعيلية', 'lat' => 30.5965, 'lng' => 32.2715],
    ],
    'EG-JS' => [
        ['code' => 'SEED-0021', 'en' => 'El Tor', 'ar' => 'الطور', 'lat' => 28.2417, 'lng' => 33.6222],
        ['code' => 'SEED-0022', 'en' => 'Sharm El Sheikh', 'ar' => 'شرم الشيخ', 'lat' => 27.9158, 'lng' => 34.3300],
    ],
    'EG-KB' => [
        ['code' => 'SEED-0023', 'en' => 'Banha', 'ar' => 'بنها', 'lat' => 30.4660, 'lng' => 31.1840],
    ],
    'EG-KFS' => [
        ['code' => 'SEED-0024', 'en' => 'Kafr El Sheikh', 'ar' => 'كفر الشيخ', 'lat' => 31.1107, 'lng' => 30.9380],
    ],
    'EG-KN' => [
        ['code' => 'SEED-0025', 'en' => 'Qena', 'ar' => 'قنا', 'lat' => 26.1551, 'lng' => 32.7160],
    ],
    'EG-LX' => [
        ['code' => 'SEED-0026', 'en' => 'Luxor', 'ar' => 'الأقصر', 'lat' => 25.6872, 'lng' => 32.6396],
    ],
    'EG-MN' => [
        ['code' => 'SEED-0027', 'en' => 'Minya', 'ar' => 'المنيا', 'lat' => 28.1099, 'lng' => 30.7503],
    ],
    'EG-MNF' => [
        ['code' => 'SEED-0028', 'en' => 'Shibin El Kom', 'ar' => 'شبين الكوم', 'lat' => 30.5528, 'lng' => 31.0093],
        ['code' => 'SEED-0029', 'en' => 'Sadat City', 'ar' => 'مدينة السادات', 'lat' => 30.3624, 'lng' => 30.5196],
    ],
    'EG-MT' => [
        ['code' => 'SEED-0030', 'en' => 'Marsa Matrouh', 'ar' => 'مرسى مطروح', 'lat' => 31.3543, 'lng' => 27.2373],
        ['code' => 'SEED-0031', 'en' => 'El Alamein', 'ar' => 'العلمين', 'lat' => 30.8300, 'lng' => 28.9500],
    ],
    'EG-PTS' => [
        ['code' => 'SEED-0032', 'en' => 'Port Said', 'ar' => 'بورسعيد', 'lat' => 31.2653, 'lng' => 32.3019],
    ],
    'EG-SHG' => [
        ['code' => 'SEED-0033', 'en' => 'Sohag', 'ar' => 'سوهاج', 'lat' => 26.5591, 'lng' => 31.6957],
    ],
    'EG-SHR' => [
        ['code' => 'SEED-0034', 'en' => 'Zagazig', 'ar' => 'الزقازيق', 'lat' => 30.5877, 'lng' => 31.5020],
    ],
    'EG-SIN' => [
        ['code' => 'SEED-0035', 'en' => 'Arish', 'ar' => 'العريش', 'lat' => 31.1249, 'lng' => 33.8006],
    ],
    'EG-SUZ' => [
        ['code' => 'SEED-0036', 'en' => 'Suez', 'ar' => 'السويس', 'lat' => 29.9668, 'lng' => 32.5498],
    ],
    'EG-WAD' => [
        ['code' => 'SEED-0037', 'en' => 'Kharga', 'ar' => 'الخارجة', 'lat' => 25.4514, 'lng' => 30.5464],
    ],

    // --- Gulf markets -------------------------------------------------------
    'SA-01' => [
        ['code' => 'SEED-0038', 'en' => 'Riyadh', 'ar' => 'الرياض', 'lat' => 24.7136, 'lng' => 46.6753],
    ],
    'SA-02' => [
        ['code' => 'SEED-0039', 'en' => 'Makkah', 'ar' => 'مكة المكرمة', 'lat' => 21.3891, 'lng' => 39.8579],
        ['code' => 'SEED-0040', 'en' => 'Jeddah', 'ar' => 'جدة', 'lat' => 21.4858, 'lng' => 39.1925],
    ],
    'SA-03' => [
        ['code' => 'SEED-0041', 'en' => 'Al Madinah', 'ar' => 'المدينة المنورة', 'lat' => 24.5247, 'lng' => 39.5692],
    ],
    'SA-04' => [
        ['code' => 'SEED-0042', 'en' => 'Dammam', 'ar' => 'الدمام', 'lat' => 26.4207, 'lng' => 50.0888],
    ],
    'AE-AZ' => [
        ['code' => 'SEED-0043', 'en' => 'Abu Dhabi', 'ar' => 'أبوظبي', 'lat' => 24.4539, 'lng' => 54.3773],
    ],
    'AE-DU' => [
        ['code' => 'SEED-0044', 'en' => 'Dubai', 'ar' => 'دبي', 'lat' => 25.2048, 'lng' => 55.2708],
    ],
    'AE-SH' => [
        ['code' => 'SEED-0045', 'en' => 'Sharjah', 'ar' => 'الشارقة', 'lat' => 25.3463, 'lng' => 55.4209],
    ],

];
