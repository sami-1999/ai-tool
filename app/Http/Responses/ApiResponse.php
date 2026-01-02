<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public static function success(
        mixed $data = null,
        string $message = 'Request successful',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Generic error response
     *
     * @param string $message
     * @param int $status
     * @param mixed $data
     * @return JsonResponse
     */
    public static function error(
        string $message = 'Something went wrong',
        int $status = 400,
        mixed $data = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Validation error response
     *
     * @param array $errors
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public static function validation(
        array $errors,
        string $message = 'Validation failed',
        int $status = 422
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Not found response
     *
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public static function notFound(
        string $message = 'Resource not found',
        int $status = 404
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * Unauthorized response
     *
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
        int $status = 401
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * Forbidden response
     *
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public static function forbidden(
        string $message = 'Forbidden',
        int $status = 403
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
