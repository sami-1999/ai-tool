<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Always return JSON for API routes
        if ($request->is('api/*')) {

            // Validation errors
            if ($e instanceof ValidationException) {
                return ApiResponse::validation(
                    $e->errors(),
                    $e->getMessage()
                );
            }

            // Model not found
            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return ApiResponse::notFound("$model not found");
            }

            // Authentication / unauthorized
            if ($e instanceof AuthenticationException) {
                return ApiResponse::unauthorized($e->getMessage());
            }

            // HTTP exceptions (like 403, 404)
            if ($e instanceof HttpException) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'HTTP error',
                    $e->getStatusCode()
                );
            }

            // Default: server error
            return ApiResponse::error(
                $e->getMessage() ?: 'Server Error',
                500
            );
        }

        // Non-API requests fallback to default
        return parent::render($request, $e);
    }
}
