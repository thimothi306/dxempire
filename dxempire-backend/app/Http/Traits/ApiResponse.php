<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function created(mixed $data, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function paginated(mixed $resource, string $message = 'Success'): JsonResponse
    {
        $data = $resource->toArray(request());

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data['data'],
            'meta'    => [
                'current_page' => $data['current_page'],
                'per_page'     => $data['per_page'],
                'total'        => $data['total'],
                'last_page'    => $data['last_page'],
            ],
        ]);
    }
}
