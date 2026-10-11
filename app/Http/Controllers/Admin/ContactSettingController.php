<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactSettingRequest;
use App\Models\ContactSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class ContactSettingController extends Controller
{
    /** Read contact settings.
     * Returns structured public contact channels, bilingual text and an optimistic concurrency version.
     */
    public function show(): JsonResponse
    {
        $settings = ContactSetting::query()->findOrFail(1);

        return ApiResponse::success(['version' => $settings->version, 'settings' => (object) $settings->settings]);
    }

    /** Update contact settings.
     * Supply version and changed fields. Social links replace the existing list; translation fields merge by locale.
     */
    public function update(ContactSettingRequest $request, AuditLoggerInterface $audit): JsonResponse
    {
        return DB::transaction(function () use ($request, $audit): JsonResponse {
            $row = ContactSetting::query()->lockForUpdate()->findOrFail(1);
            $data = $request->validated();
            if ($row->version !== (int) $data['version']) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            unset($data['version']);
            $settings = $row->settings;
            $translations = array_replace_recursive($settings['translations'] ?? [], $data['translations'] ?? []);
            $settings = array_replace($settings, $data);
            $settings['translations'] = $translations;
            $row->forceFill(['settings' => $settings, 'version' => $row->version + 1])->save();
            $audit->record(new AuditEntry(AuditAction::CONTACT_SETTINGS_UPDATED, $row, actor: $request->user()));

            return ApiResponse::success(['version' => $row->version, 'settings' => $row->settings]);
        }, 5);
    }
}
