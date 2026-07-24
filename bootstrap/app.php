<?php

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $wantsJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($wantsJson);

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) use ($wantsJson): ?JsonResponse {
            if (! $wantsJson($request)) {
                return null;
            }

            return Controller::error('The requested resource was not found.', 404);
        });

        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) use ($wantsJson): ?JsonResponse {
            if (! $wantsJson($request)) {
                return null;
            }

            return Controller::error($e->getMessage() ?: 'You are not authorized to perform this action.', 403);
        });
    })->create();
