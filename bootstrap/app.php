<?php

use App\Http\Middleware\OrderConcurrencyLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(

        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
   ->withMiddleware(function ($middleware) {

       $middleware->alias([
           'admin' => \App\Http\Middleware\AdminMiddleware::class,
           'Performance'=> \App\Http\Middleware\PerformanceMonitor::class,
           'lock.logger' => \App\Http\Middleware\DistributedLockLogger::class,
            'log.concurrency' => OrderConcurrencyLogger::class
       ]);


       $middleware->api(append: [
            
        ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
