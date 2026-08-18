<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Base controller for the customer REST API (/api/v1). Provides the standard
 * { data, meta } success envelope, a consistent error envelope, and helpers
 * to read the authenticated workspace / API key set by AuthenticateApiKey.
 */
abstract class V1Controller extends Controller
{
    /** Current workspace id (set by the api-key middleware). */
    protected function workspaceId(): int
    {
        return (int) (Auth::user()->current_workspace_id ?? 0);
    }

    protected function apiKey(): ?ApiKey
    {
        return request()->attributes->get('api_key');
    }

    /** 200 with a { data, meta } envelope. */
    protected function ok(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $body = ['data' => $data];
        if ($meta) $body['meta'] = $meta;
        return response()->json($body, $status);
    }

    protected function created(mixed $data, array $meta = []): JsonResponse
    {
        return $this->ok($data, $meta, 201);
    }

    /** Standard error envelope. */
    protected function fail(string $code, string $message, int $status = 400, array $details = []): JsonResponse
    {
        $err = ['code' => $code, 'message' => $message];
        if ($details) $err['details'] = $details;
        return response()->json(['error' => $err], $status);
    }
}
