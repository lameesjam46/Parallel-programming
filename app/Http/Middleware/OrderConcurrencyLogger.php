<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OrderConcurrencyLogger
{
   
    public function handle(Request $request, Closure $next): Response
    {
        $serverPort = $request->server('SERVER_PORT') ?? 'unknown';

        Log::info(" Method={$request->method()} | Path={$request->path()} | Handled By Port={$serverPort}");

       return $next($request);
    }
}