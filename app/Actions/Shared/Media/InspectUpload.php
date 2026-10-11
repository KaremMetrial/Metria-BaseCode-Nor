<?php

namespace App\Actions\Shared\Media;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

final class InspectUpload
{
    /** @return array{contents: string, mime_type: string, extension: string, width: ?int, height: ?int, inspection: string} */
    public function execute(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $binary = config('media.clamav_binary');
        if ($mime === 'application/pdf' && ! $binary) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        if ($binary) {
            try {
                $result = Process::timeout((int) config('media.scan_timeout'))->run([(string) $binary, '--no-summary', '--', $file->getPathname()]);
            } catch (ProcessTimedOutException) {
                throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
            }
            if ($result->exitCode() === 1) {
                throw ValidationException::withMessages(['file' => [__('media.rejected')]]);
            }
            if (! $result->successful()) {
                throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
            }
        }
        $contents = file_get_contents($file->getPathname());
        if ($mime === 'application/pdf') {
            return ['contents' => $contents, 'mime_type' => $mime, 'extension' => 'pdf', 'width' => null, 'height' => null, 'inspection' => 'antivirus'];
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw ValidationException::withMessages(['file' => [__('media.rejected')]]);
        }
        imagesavealpha($image, true);
        ob_start();
        try {
            $encoded = imagepng($image);
            $clean = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (! $encoded || ! is_string($clean)) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return ['contents' => $clean, 'mime_type' => 'image/png', 'extension' => 'png', 'width' => imagesx($image), 'height' => imagesy($image), 'inspection' => $binary ? 'antivirus_and_reencoded' : 'reencoded'];
    }
}
