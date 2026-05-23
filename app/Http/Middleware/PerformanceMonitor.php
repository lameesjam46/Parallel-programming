<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

    $response = $next($request); 

    $duration = (microtime(true) - $start) * 1000; 
    
    Log::info("الطلب: {$request->path()} | الزمن: {$duration}ms");

    return $response;
    }
}
