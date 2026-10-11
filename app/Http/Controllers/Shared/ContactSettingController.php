<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\ContactSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ContactSettingController extends Controller
{
    /** Read public contact channels.
     * Localized address, introduction and opening hours. Phone and WhatsApp are nullable E.164 numbers; no account is required.
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success(ContactSetting::query()->findOrFail(1)->publicSettings());
    }
}
