<?php

namespace App\Services;

use App\Exceptions\FaceRecognitionServiceException;
use Illuminate\Support\Facades\Http;

class FaceRecognitionClient
{
    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        try {
            $response = $this->request()
                ->get($this->baseUrl() . '/health');
        } catch (\Throwable $exception) {
            throw new FaceRecognitionServiceException(
                'Face service unavailable: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        return $this->handleResponse($response->json(), $response->status());
    }

    /**
     * @return array<string, mixed>
     */
    public function enroll(string $absolutePath, ?string $filename = null): array
    {
        if (!is_file($absolutePath)) {
            throw new FaceRecognitionServiceException('Enrollment image not found on disk');
        }

        try {
            $response = $this->request()
                ->attach(
                    'image',
                    fopen($absolutePath, 'r'),
                    $filename ?: basename($absolutePath)
                )
                ->post($this->baseUrl() . '/enroll');
        } catch (\Throwable $exception) {
            throw new FaceRecognitionServiceException(
                'Face service unavailable: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        return $this->handleResponse($response->json(), $response->status());
    }

    /**
     * @param array<int, float|int> $templateVector
     * @return array<string, mixed>
     */
    public function verify(
        string $absolutePath,
        array $templateVector,
        float $threshold,
        ?string $filename = null
    ): array {
        if (!is_file($absolutePath)) {
            throw new FaceRecognitionServiceException('Verification image not found on disk');
        }

        try {
            $response = $this->request()
                ->attach(
                    'image',
                    fopen($absolutePath, 'r'),
                    $filename ?: basename($absolutePath)
                )
                ->post($this->baseUrl() . '/verify', [
                    'threshold' => $threshold,
                    'template_vector' => json_encode(array_values($templateVector), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        } catch (\Throwable $exception) {
            throw new FaceRecognitionServiceException(
                'Face service unavailable: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        return $this->handleResponse($response->json(), $response->status());
    }

    private function request()
    {
        $request = Http::acceptJson()
            ->connectTimeout((float) config('attendance.face.connect_timeout', 1.5))
            ->timeout((float) config('attendance.face.request_timeout', 5.0));

        $token = trim((string) config('attendance.face.service_token', ''));
        if ($token !== '') {
            $request = $request->withHeaders([
                'X-Face-Service-Token' => $token,
            ]);
        }

        return $request;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('attendance.face.service_url', 'http://127.0.0.1:9001'), '/');
    }

    /**
     * @param mixed $payload
     * @return array<string, mixed>
     */
    private function handleResponse($payload, int $status): array
    {
        if ($status >= 200 && $status < 300 && is_array($payload)) {
            return $payload;
        }

        $detail = is_array($payload)
            ? (string) ($payload['detail'] ?? $payload['message'] ?? 'Unexpected face service response')
            : 'Unexpected face service response';

        throw new FaceRecognitionServiceException(sprintf(
            'Face service request failed [%d]: %s',
            $status,
            $detail
        ));
    }
}
