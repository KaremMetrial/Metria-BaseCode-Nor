<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = json_decode('[["EG-C", "Cairo", "SEED-0001"], ["EG-C", "New Cairo", "SEED-0002"], ["EG-C", "Nasr City", "SEED-0003"], ["EG-C", "Helwan", "SEED-0004"], ["EG-GZ", "Giza", "SEED-0005"], ["EG-GZ", "6th of October City", "SEED-0006"], ["EG-GZ", "Sheikh Zayed City", "SEED-0007"], ["EG-ALX", "Alexandria", "SEED-0008"], ["EG-ALX", "Borg El Arab", "SEED-0009"], ["EG-ASN", "Aswan", "SEED-0010"], ["EG-AST", "Asyut", "SEED-0011"], ["EG-BA", "Hurghada", "SEED-0012"], ["EG-BH", "Damanhour", "SEED-0013"], ["EG-BNS", "Beni Suef", "SEED-0014"], ["EG-DK", "Mansoura", "SEED-0015"], ["EG-DT", "Damietta", "SEED-0016"], ["EG-FYM", "Faiyum", "SEED-0017"], ["EG-GH", "Tanta", "SEED-0018"], ["EG-GH", "El Mahalla El Kubra", "SEED-0019"], ["EG-IS", "Ismailia", "SEED-0020"], ["EG-JS", "El Tor", "SEED-0021"], ["EG-JS", "Sharm El Sheikh", "SEED-0022"], ["EG-KB", "Banha", "SEED-0023"], ["EG-KFS", "Kafr El Sheikh", "SEED-0024"], ["EG-KN", "Qena", "SEED-0025"], ["EG-LX", "Luxor", "SEED-0026"], ["EG-MN", "Minya", "SEED-0027"], ["EG-MNF", "Shibin El Kom", "SEED-0028"], ["EG-MNF", "Sadat City", "SEED-0029"], ["EG-MT", "Marsa Matrouh", "SEED-0030"], ["EG-MT", "El Alamein", "SEED-0031"], ["EG-PTS", "Port Said", "SEED-0032"], ["EG-SHG", "Sohag", "SEED-0033"], ["EG-SHR", "Zagazig", "SEED-0034"], ["EG-SIN", "Arish", "SEED-0035"], ["EG-SUZ", "Suez", "SEED-0036"], ["EG-WAD", "Kharga", "SEED-0037"], ["SA-01", "Riyadh", "SEED-0038"], ["SA-02", "Makkah", "SEED-0039"], ["SA-02", "Jeddah", "SEED-0040"], ["SA-03", "Al Madinah", "SEED-0041"], ["SA-04", "Dammam", "SEED-0042"], ["AE-AZ", "Abu Dhabi", "SEED-0043"], ["AE-DU", "Dubai", "SEED-0044"], ["AE-SH", "Sharjah", "SEED-0045"]]', true, 512, JSON_THROW_ON_ERROR);
        foreach ($rows as [$governorateCode, $oldName, $code]) {
            $ids = DB::table('cities')->join('governorates', 'cities.governorate_id', '=', 'governorates.id')->join('city_translations', 'cities.id', '=', 'city_translations.city_id')->where('governorates.code', $governorateCode)->whereNull('cities.code')->where('city_translations.locale', 'en')->where('city_translations.name', $oldName)->pluck('cities.id');
            if ($ids->count() === 1) {
                DB::table('cities')->where('id', $ids->first())->update(['code' => $code]);
            }
        }
    }

    public function down(): void
    { /* Stable identities are intentionally retained on rollback. */
    }
};
