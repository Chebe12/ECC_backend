<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Exceptions\RouteNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Helpers\ResponseData;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->alias([
            'jwt.auth' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
            'api' => \Illuminate\Http\Middleware\HandleCors::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth:api' => \Illuminate\Auth\Middleware\Authenticate::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($e instanceof MethodNotAllowedHttpException) {
                return ResponseData::error(
                    [],
                    'This method is not allowed for the requested route',
                    405
                );
            } elseif ($e instanceof NotFoundHttpException) {
                return ResponseData::error(
                    [],
                    'This route is not found',
                    404
                );
            } elseif ($e instanceof ModelNotFoundException) {
                return ResponseData::error(
                    [],
                    'The requested resource could not be found',
                    404
                );
            } elseif (
                $e instanceof TokenExpiredException ||
                $e instanceof TokenInvalidException ||
                $e instanceof JWTException
            ) {
                return ResponseData::error(
                    [],
                    'Please login to be authenticated',
                    401
                );
            } elseif ($e instanceof ValidationException) {
                return ResponseData::error(
                    $e->validator->errors()->all(),
                    'Validation Error',
                    422
                );
            } elseif ($e instanceof AuthenticationException) {
                return ResponseData::error(
                    [],
                    'Please login to be authenticated',
                    401
                );
            } elseif ($e instanceof ThrottleRequestsException) {
                return ResponseData::error(
                    [],
                    'Too many requests. Please slow down.',
                    429
                );
            } elseif ($e instanceof InvalidSignatureException) {
                return ResponseData::error([], 'Invalid signature', 401);
            } elseif ($e instanceof QueryException) {
                return ResponseData::error(
                    [],
                    'A database error occurred. Please try again.',
                    500
                );
            } elseif ($e instanceof \Illuminate\Encryption\MissingAppKeyException) {
                return ResponseData::error(
                    [],
                    'key is missing.',
                    500
                );
            }
        });
    })
    ->create();
