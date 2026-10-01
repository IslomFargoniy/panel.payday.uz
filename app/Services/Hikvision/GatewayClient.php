<?php

namespace App\Services\Hikvision;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GatewayClient
{
    /**
     * Create a PendingRequest pre-configured with the Hikvision gateway baseUrl and X-Gateway-Token header.
     */
    public static function http(): PendingRequest
    {
        $baseUrl = rtrim((string) config('hikvision.gateway_url', 'http://127.0.0.1:7661'), '/');
        $token = trim((string) config('hikvision.gateway_token', ''));
        $timeout = (int) config('hikvision.timeout', 10);

        $request = Http::baseUrl($baseUrl)->timeout($timeout);

        if ($token !== '') {
            $request->withHeaders([
                'X-Gateway-Token' => $token,
            ]);
        }

        return $request;
    }
}
