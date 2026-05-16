<?php

namespace Sabbajohn\PulseLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sabbajohn\PulsePhp\Exceptions\ValidationException;
use Sabbajohn\PulsePhp\Exceptions\PulseException;
use Sabbajohn\PulsePhp\PulseClient;

class ProxyController extends Controller
{
    public function __invoke(Request $request, PulseClient $pulse, string $path): JsonResponse
    {
        $path = trim($path, '/');

        abort_unless($this->isAllowed($path), 404);

        $options = [];

        if ($request->query() !== []) {
            $options['query'] = $request->query();
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            $options['json'] = $request->all();
        }

        try {
            return response()->json(
                $pulse->request($request->method(), $path, $options),
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
                'error_type' => class_basename($exception),
            ], 422);
        } catch (PulseException $exception) {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600
                ? $exception->getCode()
                : 502;

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_type' => class_basename($exception),
            ], $status);
        }
    }

    private function isAllowed(string $path): bool
    {
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        foreach ((array) config('pulse.allowed_proxy_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix, '/');

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
