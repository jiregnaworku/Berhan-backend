<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Let Laravel handle these normally (422 with field errors, 401, other 4xx HTTP errors)
            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500)) {
                return null;
            }

            return response()->json([
                'message' => config('app.debug')
                    ? $exception->getMessage()
                    : 'The service is temporarily unavailable. Please try again later.',
            ], 503);
        });
    })->create();