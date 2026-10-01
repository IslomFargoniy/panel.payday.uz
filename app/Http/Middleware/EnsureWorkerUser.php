<?php

namespace App\Http\Middleware;

use App\Models\Worker\Worker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkerUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof Worker) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized worker access',
            ], 403);
        }

        return $next($request);
    }
}
