<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Thin HTTP client for the Python analytics service. */
class AnalyticsClient
{
    private function http()
    {
        return Http::withHeaders(['X-Analytics-Token' => (string) config('silabar.analytics.token')])
            ->acceptJson()->timeout((int) config('silabar.analytics.timeout'));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('silabar.analytics.url'), '/').$path;
    }

    /** @return array{status:string,...} */
    public function run(array $tasks = ['all']): array
    {
        try {
            $res = $this->http()->post($this->url('/run'), ['tasks' => $tasks]);
        } catch (ConnectionException $e) {
            return ['status' => 'unreachable', 'error' => 'Layanan analitik Python tidak bisa dihubungi. Jalankan dulu: '.'uvicorn silabar_analytics.api:app --port 8001'];
        }
        if ($res->failed()) {
            return ['status' => 'error', 'error' => 'Layanan analitik menolak permintaan (HTTP '.$res->status().').'];
        }

        return $res->json();
    }

    public function health(): ?array
    {
        try {
            $res = Http::timeout(3)->get($this->url('/health'));

            return $res->successful() ? $res->json() : null;
        } catch (ConnectionException $e) {
            return null;
        }
    }
}
