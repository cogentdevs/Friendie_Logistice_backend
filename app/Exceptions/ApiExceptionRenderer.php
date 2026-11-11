<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        // Returning null preserves Laravel's existing web exception handling.
        if (! $request->is('api/v1', 'api/v1/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::error('Validation failed', $exception->status, $exception->errors());
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $message = match ($status) {
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Resource not found',
            405 => 'Method not allowed',
            422 => 'Validation failed',
            429 => 'Too many requests',
            default => $status >= 500 ? 'Server error' : 'Request failed',
        };

        return ApiResponse::error($message, $status, headers: $headers);
    }
}
