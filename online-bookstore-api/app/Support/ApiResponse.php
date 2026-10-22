<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Builds the response envelope every endpoint of the API speaks.
 *
 * The Flutter client unwraps this envelope in exactly one place
 * (`lib/data/models/api_response.dart`), so the shape must never vary:
 *
 *   { "success": true, "message": "Success", "data": { }, "meta": { } }
 */
class ApiResponse
{
    /**
     * A successful response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * A failed response. `$errors` is the Laravel-style field => messages map
     * the client uses to highlight the offending inputs.
     */
    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * A paginated response. `$data` is already-resolved payload (a list of
     * arrays); the pagination block is exposed under `meta`.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $data,
        string $message = 'Success',
    ): JsonResponse {
        return self::success($data, $message, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'total_orders' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ]);
    }
}
