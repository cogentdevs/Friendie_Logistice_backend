<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success('API is running', [
            'status' => 'ok',
            'api_version' => 'v1',
        ]);
    }
}
