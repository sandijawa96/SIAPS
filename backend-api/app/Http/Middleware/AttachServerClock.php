<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachServerClock
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $clock = $this->serverClockPayload();

        $response->headers->set('X-Server-Now', $clock['server_now']);
        $response->headers->set('X-Server-Epoch-Ms', (string) $clock['server_epoch_ms']);
        $response->headers->set('X-Server-Date', $clock['server_date']);
        $response->headers->set('X-Server-Timezone', $clock['timezone']);

        if ($response instanceof JsonResponse) {
            $this->attachClockToJsonResponse($response, $clock);
        }

        return $response;
    }

    /**
     * @return array<string, int|string>
     */
    private function serverClockPayload(): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $serverNow = now()->setTimezone($timezone);

        return [
            'server_now' => $serverNow->toISOString(),
            'server_epoch_ms' => $serverNow->valueOf(),
            'server_date' => $serverNow->toDateString(),
            'timezone' => $timezone,
        ];
    }

    /**
     * @param array<string, int|string> $clock
     */
    private function attachClockToJsonResponse(JsonResponse $response, array $clock): void
    {
        $payload = $response->getData(true);
        if (!is_array($payload) || !$this->isAssociativeArray($payload)) {
            return;
        }

        $meta = $payload['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $payload['meta'] = array_merge($meta, $clock, [
            'server_clock' => $clock,
        ]);

        $response->setData($payload);
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssociativeArray(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }
}
