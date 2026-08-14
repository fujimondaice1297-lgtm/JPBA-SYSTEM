<?php

namespace App\Services;

use App\Models\ProBowler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProBowlerPhotoService
{
    private const MAX_BYTES = 10_485_760;

    /**
     * Browser URL for the current public photo.
     *
     * Local photos are always served through the application so Windows
     * environments do not depend on a public/storage symbolic link.
     */
    public function publicUrl(ProBowler $bowler): ?string
    {
        $path = trim((string) $bowler->public_image_path);
        if ($path === '') {
            return null;
        }

        $relative = $this->relativeStoragePath($path);
        if ($relative !== null && Storage::disk('public')->exists($relative)) {
            return route('players.photo', [
                'bowler' => $bowler->getKey(),
                'v' => optional($bowler->updated_at)->timestamp ?: null,
            ]);
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if ($this->isLegacyOfficialPath($path)) {
            return $this->absoluteOfficialUrl($path);
        }

        if (str_starts_with($path, '/uploads/') && is_file(public_path(ltrim($path, '/')))) {
            return $path;
        }

        return null;
    }

    public function localPath(ProBowler $bowler): ?string
    {
        $relative = $this->relativeStoragePath((string) $bowler->public_image_path);

        return $relative !== null && Storage::disk('public')->exists($relative)
            ? Storage::disk('public')->path($relative)
            : null;
    }

    public function relativeStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return str_starts_with($normalized, 'profiles/') ? $normalized : null;
    }

    public function officialSourceUrl(ProBowler $bowler): ?string
    {
        $path = trim((string) $bowler->public_image_path);
        if ($this->isLegacyOfficialPath($path)) {
            return $this->absoluteOfficialUrl($path);
        }

        if (preg_match('#^https?://#i', $path) === 1 && $this->isAllowedOfficialUrl($path)) {
            return $path;
        }

        return null;
    }

    public function discoverOfficialSourceUrl(ProBowler $bowler): ?string
    {
        $profileUrl = trim((string) $bowler->official_profile_url);
        if ($profileUrl === '') {
            $profileUrl = JpbaOfficialPlayerProfileService::BASE_URL
                .'/player1/detail.html?id='.rawurlencode(strtoupper((string) $bowler->license_no));
        }

        if (! $this->isAllowedOfficialUrl($profileUrl)) {
            return null;
        }

        $response = $this->officialRequest()->get($profileUrl);
        if (! $response->successful()) {
            throw new RuntimeException('Official profile HTTP status '.$response->status());
        }

        return $this->extractOfficialPhotoUrl((string) $response->body());
    }

    public function extractOfficialPhotoUrl(string $html): ?string
    {
        preg_match_all('/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/iu', $html, $matches);

        foreach ($matches[1] ?? [] as $source) {
            $source = html_entity_decode(trim((string) $source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $path = (string) (parse_url($source, PHP_URL_PATH) ?: $source);
            if (! $this->isLegacyOfficialPath($path)) {
                continue;
            }

            return preg_match('#^https?://#i', $source) === 1
                ? $source
                : $this->absoluteOfficialUrl($source);
        }

        return null;
    }

    /**
     * @return array{path:string,source_url:string,bytes:int,mime:string,width:int,height:int,sha256:string}
     */
    public function importOfficialPhoto(ProBowler $bowler, ?string $sourceUrl = null): array
    {
        $sources = array_values(array_unique(array_filter([
            $sourceUrl,
            $this->officialSourceUrl($bowler),
        ])));

        $lastError = null;
        foreach ($sources as $source) {
            try {
                $image = $this->downloadImage($source);
                $path = $this->storeBytes(
                    $bowler,
                    $image['bytes'],
                    $image['mime'],
                    'official'
                );

                $bowler->forceFill(['public_image_path' => $path])->save();

                unset($image['bytes']);

                return [
                    'path' => $path,
                    'source_url' => $source,
                    ...$image,
                ];
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        try {
            $discovered = $this->discoverOfficialSourceUrl($bowler);
            if ($discovered !== null && ! in_array($discovered, $sources, true)) {
                return $this->importOfficialPhoto($bowler, $discovered);
            }
        } catch (\Throwable $e) {
            $lastError = $e;
        }

        throw new RuntimeException(
            $lastError?->getMessage() ?: 'Official profile photo was not found.'
        );
    }

    public function storeUploadedPhoto(ProBowler $bowler, UploadedFile $file): string
    {
        $bytes = $file->get();
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Uploaded profile photo is empty.');
        }

        $image = $this->inspectImage($bytes);
        $path = $this->storeBytes(
            $bowler,
            $bytes,
            $image['mime'],
            'upload-'.now()->format('Ymd-His')
        );

        $bowler->forceFill(['public_image_path' => $path])->save();

        return $path;
    }

    /**
     * @return array{bytes:string,mime:string,width:int,height:int,sha256:string}
     */
    private function downloadImage(string $url): array
    {
        if (! $this->isAllowedOfficialUrl($url)) {
            throw new RuntimeException('Official photo URL host is not allowed.');
        }

        $response = $this->officialRequest()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Official photo HTTP status '.$response->status());
        }

        $bytes = (string) $response->body();
        $image = $this->inspectImage($bytes);

        return ['bytes' => $bytes, ...$image];
    }

    /**
     * @return array{mime:string,width:int,height:int,sha256:string}
     */
    private function inspectImage(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length === 0 || $length > self::MAX_BYTES) {
            throw new RuntimeException('Profile photo size is invalid.');
        }

        $info = @getimagesizefromstring($bytes);
        $mime = strtolower((string) ($info['mime'] ?? ''));
        if ($info === false || ! isset($this->extensions()[$mime])) {
            throw new RuntimeException('Profile photo is not a supported JPEG, PNG, GIF, or WebP image.');
        }

        return [
            'mime' => $mime,
            'width' => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
            'sha256' => hash('sha256', $bytes),
        ];
    }

    private function storeBytes(ProBowler $bowler, string $bytes, string $mime, string $origin): string
    {
        $extension = $this->extensions()[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Profile photo MIME type is not supported.');
        }

        $license = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $bowler->license_no) ?: (string) $bowler->id);
        $hash = hash('sha256', $bytes);
        $path = sprintf(
            'profiles/%s/%d/%s-%s.%s',
            $license,
            (int) now()->year,
            $origin,
            substr($hash, 0, 16),
            $extension
        );

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $bytes);
        }

        return $path;
    }

    private function officialRequest()
    {
        $request = Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'User-Agent' => 'JPBA-SYSTEM official profile photo migration',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ]);

        return app()->environment('local') ? $request->withoutVerifying() : $request;
    }

    private function isLegacyOfficialPath(string $path): bool
    {
        $path = (string) (parse_url($path, PHP_URL_PATH) ?: $path);

        return str_starts_with($path, '/assets/img/prof/')
            || str_starts_with($path, '/.file/prof/');
    }

    private function absoluteOfficialUrl(string $path): string
    {
        $path = (string) (parse_url($path, PHP_URL_PATH) ?: $path);
        $segments = array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', ltrim($path, '/'))
        );

        return JpbaOfficialPlayerProfileService::BASE_URL.'/'.implode('/', $segments);
    }

    private function isAllowedOfficialUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['jpba1.jp', 'www.jpba1.jp'], true);
    }

    /** @return array<string,string> */
    private function extensions(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
    }
}
