<?php

namespace App\Services\Rewards;

use App\Models\RewardCampaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Private-disk campaign banner/video storage.
 * Never credits wallets. Never changes qualification.
 */
class RewardCampaignMedia
{
    public const IMAGE_PREFIX = 'rewards/';

    public const VIDEO_PREFIX = 'rewards/videos/';

    public function storeBanner(RewardCampaign $campaign, UploadedFile $file): void
    {
        $maxKb = (int) config('deltasupport.rewards.image_max_kb', 2048);
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'image' => 'Banner image must be '.$maxKb.' KB or smaller.',
            ]);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (! $this->uploadMimeAllowed($file, $allowed)) {
            throw ValidationException::withMessages([
                'image' => 'Upload a JPG, PNG, or WebP banner image.',
            ]);
        }

        $path = $file->store(rtrim(self::IMAGE_PREFIX, '/'), 'local');
        if (! is_string($path) || ! $this->isSafeImagePath($path) || ! $this->storedMimeAllowed($path, $allowed)) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages([
                'image' => 'Upload a JPG, PNG, or WebP banner image.',
            ]);
        }

        $previous = $campaign->gift_image_path;
        $campaign->forceFill(['gift_image_path' => $path])->save();
        $this->deleteIfUnreferenced($previous, (int) $campaign->id, 'image');
    }

    public function removeBanner(RewardCampaign $campaign): void
    {
        $previous = $campaign->gift_image_path;
        $campaign->forceFill(['gift_image_path' => null])->save();
        $this->deleteIfUnreferenced($previous, (int) $campaign->id, 'image');
    }

    public function storeVideo(RewardCampaign $campaign, UploadedFile $file): void
    {
        $maxKb = (int) config('deltasupport.rewards.video_max_kb', 204800);
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'video' => 'Campaign video must be '.$maxKb.' KB or smaller.',
            ]);
        }

        $allowedMimes = (array) config('deltasupport.rewards.video_mimetypes', ['video/mp4', 'video/webm', 'application/mp4']);
        if (! $this->uploadMimeAllowed($file, $allowedMimes)) {
            throw ValidationException::withMessages([
                'video' => 'Upload an MP4 or WebM video.',
            ]);
        }

        $path = $file->store(rtrim(self::VIDEO_PREFIX, '/'), 'local');
        if (! is_string($path) || ! $this->isSafeVideoPath($path) || ! $this->storedMimeAllowed($path, $allowedMimes)) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages([
                'video' => 'Upload an MP4 or WebM video.',
            ]);
        }

        $previous = $campaign->video_path;
        $campaign->forceFill(['video_path' => $path])->save();
        $this->deleteIfUnreferenced($previous, (int) $campaign->id, 'video');
    }

    public function removeVideo(RewardCampaign $campaign): void
    {
        $previous = $campaign->video_path;
        $campaign->forceFill(['video_path' => null])->save();
        $this->deleteIfUnreferenced($previous, (int) $campaign->id, 'video');
    }

    /**
     * Permanent campaign delete: remove owned media after DB row is gone.
     * Caller must delete the campaign row first, then pass former paths.
     *
     * @param  list<string|null>  $paths
     */
    public function deleteOrphanPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (! $this->isSafeImagePath($path) && ! $this->isSafeVideoPath($path)) {
                continue;
            }
            if ($this->pathReferenced($path, null)) {
                continue;
            }
            $this->safeDelete($path);
        }
    }

    public function isSafeImagePath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::IMAGE_PREFIX)
            && ! str_starts_with($path, self::VIDEO_PREFIX)
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    public function isSafeVideoPath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::VIDEO_PREFIX)
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    private function deleteIfUnreferenced(?string $path, int $exceptCampaignId, string $kind): void
    {
        if ($path === null || $path === '') {
            return;
        }
        if ($kind === 'video' && ! $this->isSafeVideoPath($path)) {
            return;
        }
        if ($kind === 'image' && ! $this->isSafeImagePath($path)) {
            return;
        }
        if ($this->pathReferenced($path, $exceptCampaignId)) {
            return;
        }
        $this->safeDelete($path);
    }

    private function pathReferenced(string $path, ?int $exceptCampaignId): bool
    {
        $query = RewardCampaign::query()
            ->where(function ($inner) use ($path): void {
                $inner->where('gift_image_path', $path)->orWhere('video_path', $path);
            });
        if ($exceptCampaignId !== null) {
            $query->where('id', '!=', $exceptCampaignId);
        }

        return $query->exists();
    }

    private function safeDelete(string $path): void
    {
        try {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Reward campaign media delete failed', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function uploadMimeAllowed(UploadedFile $file, array $allowed): bool
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        return is_string($mime) && in_array($mime, $allowed, true);
    }

    /**
     * Reject executable / clearly wrong stored payloads. Allow octet-stream when
     * the upload already passed extension + UploadedFile MIME checks (common on Windows).
     *
     * @param  list<string>  $allowed
     */
    private function storedMimeAllowed(string $path, array $allowed): bool
    {
        $stored = @mime_content_type(Storage::disk('local')->path($path));
        if (! is_string($stored) || $stored === '') {
            return true;
        }
        if (in_array($stored, $allowed, true)) {
            return true;
        }
        if (in_array($stored, ['application/octet-stream', 'application/mp4', 'video/quicktime'], true)) {
            return true;
        }

        return false;
    }
}
