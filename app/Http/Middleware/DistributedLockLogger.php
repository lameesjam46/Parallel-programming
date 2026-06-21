<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DistributedLockLogger
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('Incoming Order Request', [
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
            'product_id'  => $request->product_id,
            'time'        => now()->toDateTimeString()
        ]);

        return $next($request);
    }
}