<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function ok(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => $meta], $status);
    }

    public static function error(string $message, int $status = 422, array $errors = [], array $meta = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null, 'errors' => $errors, 'meta' => $meta], $status);
    }
}
