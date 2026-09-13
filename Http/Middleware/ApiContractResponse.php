<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Modules\PriyasaCore\Services\StorefrontApiContract;

/**
 * Normalises API responses and exceptions without changing already-contracted responses.
 * Mount once around the /api/v1 route group after ApiContractHeaders.
 */
final class ApiContractResponse
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (ValidationException $e) {
            return $this->error('validation_error', 'The request could not be validated.', $e->errors(), 422);
        } catch (AuthenticationException $e) {
            return $this->error('authentication_required', 'Authentication is required.', [], 401);
        } catch (ModelNotFoundException $e) {
            return $this->error('not_found', 'The requested resource was not found.', [], 404);
        } catch (HttpExceptionInterface $e) {
            return $this->error('http_error', $e->getMessage() ?: 'The request could not be completed.', [], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);
            return $this->error('server_error', 'An unexpected server error occurred.', [], 500);
        }

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            if (is_array($payload) && isset($payload['success'], $payload['type'], $payload['api_version'])) {
                return $response;
            }
            $contract = app(StorefrontApiContract::class);
            $body = $contract->success('api.response', $payload, [], $response->getStatusCode());
            return response()->json($body, $response->getStatusCode(), $response->headers->all());
        }

        if ($response instanceof Response && str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            $payload = json_decode($response->getContent() ?: 'null', true);
            if (is_array($payload) && isset($payload['success'], $payload['type'], $payload['api_version'])) return $response;
        }

        return $response;
    }

    private function error(string $code, string $message, array $details, int $status): JsonResponse
    {
        $payload = app(StorefrontApiContract::class)->error($code, $message, $details, $status);
        return response()->json($payload, $status);
    }
}
