<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyGatewayToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('hikvision.gateway_token', '');
        $required = (bool) config('hikvision.gateway_token_required', false);
        $providedToken = (string) $request->header('X-Gateway-Token', '');

        $isValid = !empty($expectedToken) && hash_equals($expectedToken, $providedToken);

        if (!$isValid) {
            $deviceId = $request->input('device_id') ?? $request->query('device_id') ?? 'unknown';
            Log::warning('Gateway token missing or invalid', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'device_id' => $deviceId,
                'has_provided_token' => !empty($providedToken),
            ]);

            if ($required) {
                return response()->json([
                    'error' => 'Unauthorized: Invalid or missing gateway token',
                ], 401);
            }
        }

        return $next($request);
    }
}
