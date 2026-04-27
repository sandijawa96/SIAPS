<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class NextcloudStorageService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.nextcloud.enabled')
            && trim((string) config('services.nextcloud.username')) !== ''
            && trim((string) config('services.nextcloud.password')) !== ''
            && trim((string) config('services.nextcloud.base_url')) !== '';
    }

    public function upload(UploadedFile $file, string $relativePath): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Nextcloud belum dikonfigurasi di server.');
        }

        $relativePath = $this->normalizePath($relativePath);
        $directory = trim((string) dirname($relativePath), '.\\/');
        if ($directory !== '') {
            $this->ensureDirectory($directory);
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Gagal membaca file upload.');
        }

        $response = $this->client()
            ->withBody($contents, $file->getMimeType() ?: 'application/octet-stream')
            ->put($this->urlForPath($relativePath));

        if (!$response->successful() && $response->status() !== 201 && $response->status() !== 204) {
            throw new RuntimeException('Upload ke Nextcloud gagal: HTTP ' . $response->status());
        }

        return [
            'provider' => 'nextcloud',
            'path' => $relativePath,
            'url' => $this->publicUrlForPath($relativePath),
            'etag' => $response->header('ETag'),
        ];
    }

    public function delete(string $relativePath): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = $this->client()->delete($this->urlForPath($this->normalizePath($relativePath)));

        return $response->successful() || $response->status() === 404;
    }

    private function ensureDirectory(string $directory): void
    {
        $segments = array_values(array_filter(explode('/', $this->normalizePath($directory))));
        $current = '';

        foreach ($segments as $segment) {
            $current = trim($current . '/' . $segment, '/');
            $response = $this->client()->send('MKCOL', $this->urlForPath($current));

            if (!$this->isAllowedMkcolResponse($response)) {
                throw new RuntimeException('Gagal membuat folder Nextcloud: HTTP ' . $response->status());
            }
        }
    }

    private function isAllowedMkcolResponse(Response $response): bool
    {
        return in_array($response->status(), [200, 201, 204, 405], true);
    }

    private function client()
    {
        return Http::withBasicAuth(
            (string) config('services.nextcloud.username'),
            (string) config('services.nextcloud.password')
        )
            ->timeout((int) config('services.nextcloud.timeout', 30))
            ->retry(1, 250);
    }

    private function urlForPath(string $relativePath): string
    {
        return $this->webdavBaseUrl() . '/' . $this->encodePath($relativePath);
    }

    private function publicUrlForPath(string $relativePath): ?string
    {
        $base = trim((string) config('services.nextcloud.public_base_url'));
        if ($base === '') {
            return null;
        }

        return rtrim($base, '/') . '/' . $this->encodePath($relativePath);
    }

    private function webdavBaseUrl(): string
    {
        $override = trim((string) config('services.nextcloud.webdav_base_url'));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $baseUrl = rtrim((string) config('services.nextcloud.base_url'), '/');
        $username = rawurlencode((string) config('services.nextcloud.username'));

        return $baseUrl . '/remote.php/dav/files/' . $username;
    }

    private function normalizePath(string $path): string
    {
        $root = trim((string) config('services.nextcloud.root_path', 'SIAPS'), '/');
        $path = trim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#/+#', '/', $path) ?: '';
        $path = str_replace(['../', './'], '', $path);

        if ($root === '') {
            return trim($path, '/');
        }

        if ($path === $root || str_starts_with($path, $root . '/')) {
            return trim($path, '/');
        }

        return trim($root . '/' . $path, '/');
    }

    private function encodePath(string $path): string
    {
        return collect(explode('/', trim($path, '/')))
            ->map(static fn(string $segment): string => rawurlencode($segment))
            ->implode('/');
    }

    public function makeSafeFilename(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = Str::limit(Str::slug($basename) ?: 'document', 80, '');

        return $safeBase . '-' . now()->format('YmdHisv') . '-' . Str::lower(Str::random(6))
            . ($extension !== '' ? '.' . $extension : '');
    }
}
